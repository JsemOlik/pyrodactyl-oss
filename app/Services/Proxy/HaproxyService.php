<?php

namespace Pterodactyl\Services\Proxy;

use Pterodactyl\Models\ServerSubdomain;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class HaproxyService
{
    /**
     * Generate HAProxy configuration for a subdomain.
     * 
     * This generates a backend configuration that will be included in the main HAProxy config.
     */
    public function generateBackendConfig(ServerSubdomain $subdomain): string
    {
        // Ensure relationships are loaded
        if (!$subdomain->relationLoaded('server')) {
            $subdomain->load('server.allocation');
        }
        
        $server = $subdomain->server;
        if (!$server) {
            throw new \Exception('Subdomain does not have a server.');
        }
        
        $allocation = $server->allocation;
        if (!$allocation) {
            throw new \Exception('Server does not have an allocation.');
        }

        // For HAProxy, we need to connect to the container.
        // If the allocation IP is 0.0.0.0, use localhost since the container is on the same host.
        $allocationIp = $allocation->ip;
        $containerIp = ($allocationIp === '0.0.0.0' || $allocationIp === '::') ? '127.0.0.1' : $allocationIp;
        $containerPort = $allocation->port;
        
        // Ensure domain is loaded before accessing full_domain
        if (!$subdomain->relationLoaded('domain')) {
            $subdomain->load('domain');
        }
        
        // Get hostname with error handling
        try {
            $hostname = $subdomain->full_domain;
        } catch (\RuntimeException $e) {
            throw new \Exception("Subdomain {$subdomain->id} has no domain relationship: {$e->getMessage()}");
        }

        $config = <<<HAPROXY
# Backend for subdomain: {$hostname}
# Subdomain ID: {$subdomain->id}
# Server ID: {$server->id}
# Generated: {$subdomain->updated_at->toIso8601String()}
# Routes: {$hostname} -> {$containerIp}:{$containerPort}

backend subdomain_{$subdomain->id}_backend
    mode tcp
    option tcplog
    # Enable TCP keep-alive to maintain connection
    option clitcpka
    option srvtcpka
    # No health check - Minecraft requires proper handshake, simple TCP checks fail
    # HAProxy will still route to the server even if marked as DOWN
    # Ensure full connection flow - don't interfere with packet forwarding
    server container_{$subdomain->id} {$containerIp}:{$containerPort}
    # Note: Removing 'check' disables health checks - connections will always work

HAPROXY;

        return $config;
    }

    /**
     * Generate the complete HAProxy configuration including all active subdomains.
     */
    public function generateFullConfig(): string
    {
        $subdomains = \Pterodactyl\Models\ServerSubdomain::where('is_active', true)
            ->whereNotNull('proxy_port')
            ->with(['server.allocation', 'domain'])
            ->orderBy('id') // Consistent ordering for ACL rule generation
            ->get()
            ->filter(function ($subdomain) {
                // Filter out subdomains with missing relationships
                return $subdomain->server && $subdomain->server->allocation && $subdomain->domain;
            })
            ->values(); // Re-index the collection after filtering

        $defaultProxyPort = config('proxy.default_proxy_port', 25565);
        $inspectDelay = config('proxy.haproxy_inspect_delay', 2);
        $defaultBackend = config('proxy.haproxy_default_backend');

        // Build frontend ACL rules and backend definitions
        // Note: In Phase 3, we'll use Lua script to extract hostname from Minecraft packet
        // For now, we set up the structure with placeholder ACLs
        $frontendAcls = '';
        $backendDefs = '';

        if ($subdomains->isEmpty()) {
            $frontendAcls = "    # No subdomains configured yet\n";
            $frontendAcls .= "    # Add a subdomain with proxy_port to enable routing\n";
        } else {
            foreach ($subdomains as $subdomain) {
                // Ensure relationships are loaded (should already be loaded from with(), but double-check)
                if (!$subdomain->relationLoaded('domain') || !$subdomain->domain) {
                    $subdomain->load('domain');
                }
                
                // Ensure server and allocation are loaded
                if (!$subdomain->relationLoaded('server') || !$subdomain->server) {
                    $subdomain->load('server.allocation');
                }
                
                // Skip if relationships are still missing
                if (!$subdomain->domain || !$subdomain->server || !$subdomain->server->allocation) {
                    Log::warning('Skipping subdomain due to missing relationships', [
                        'subdomain_id' => $subdomain->id,
                    ]);
                    continue;
                }
                
                // Use the accessor, but ensure domain is loaded first
                // Wrap in try-catch to handle any exceptions from the accessor
                try {
                    $hostname = $subdomain->full_domain;
                } catch (\RuntimeException $e) {
                    Log::warning('Skipping subdomain - failed to get full domain', [
                        'subdomain_id' => $subdomain->id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
                
                if (empty($hostname)) {
                    Log::warning('Skipping subdomain with empty hostname', [
                        'subdomain_id' => $subdomain->id,
                    ]);
                    continue;
                }
                $backendName = "subdomain_{$subdomain->id}_backend";
                
                // Add ACL rules for this hostname
                // Use exact string matching with case-insensitive flag
                // Escape the hostname to prevent issues with special characters
                $escapedHostname = addcslashes($hostname, '\\"');
                $frontendAcls .= "    # ACL for {$hostname} (subdomain ID: {$subdomain->id})\n";
                // Use both transaction variable (set by Lua action) and Lua fetch for redundancy
                // HAProxy will use the first matching rule - this provides fallback if one method fails
                $frontendAcls .= "    use_backend {$backendName} if { var(txn.minecraft_hostname) -i -m str \"{$escapedHostname}\" }\n";
                $frontendAcls .= "    use_backend {$backendName} if { lua.minecraft_hostname -i -m str \"{$escapedHostname}\" }\n";
                
                // Generate backend config
                $backendDefs .= $this->generateBackendConfig($subdomain);
            }
        }

        // Default backend (if configured and exists)
        $defaultBackendLine = '';
        if ($defaultBackend) {
            // Verify the default backend actually exists in our backends
            $backendExists = false;
            foreach ($subdomains as $subdomain) {
                if ("subdomain_{$subdomain->id}_backend" === $defaultBackend) {
                    $backendExists = true;
                    break;
                }
            }
            
            if ($backendExists) {
                $defaultBackendLine = "    default_backend {$defaultBackend}\n";
                Log::info('Using configured default backend', [
                    'default_backend' => $defaultBackend,
                ]);
            } else {
                // Default backend doesn't exist, log warning but don't use it
                $availableBackends = [];
                foreach ($subdomains as $subdomain) {
                    $availableBackends[] = "subdomain_{$subdomain->id}_backend";
                }
                Log::warning('Default backend specified but does not exist - connections without matching hostname will be rejected', [
                    'default_backend' => $defaultBackend,
                    'available_backends' => $availableBackends,
                ]);
            }
        }
        
        // If no default backend configured, reject unmatched connections
        // This prevents routing unknown connections to the wrong server
        if (empty($defaultBackendLine)) {
            $defaultBackendLine = "    # No default backend - connections that don't match any subdomain will be rejected\n";
            $defaultBackendLine .= "    # Configure PROXY_HAPROXY_DEFAULT_BACKEND in .env to set a default backend\n";
        }

        $config = <<<HAPROXY
# HAProxy Configuration for Pyrodactyl Subdomain Proxy
# Auto-generated - Do not edit manually
# Generated: {$this->getCurrentTimestamp()}
# 
# WARNING: This file is automatically generated. Manual edits will be overwritten.
# To customize, modify the Laravel configuration files instead.

global
    log /dev/log local0
    log /dev/log local1 notice
    chroot /var/lib/haproxy
    stats socket /run/haproxy/admin.sock mode 660 level admin
    stats timeout 30s
    user haproxy
    group haproxy
    daemon
    maxconn 4096
    # Lua script for Minecraft protocol parsing
    lua-load /etc/haproxy/minecraft_parser.lua

defaults
    log global
    mode tcp
    option tcplog
    option dontlognull
    # Enable TCP keep-alive to maintain connection after initial handshake
    option clitcpka
    option srvtcpka
    timeout connect 5s
    timeout client 50s
    timeout server 50s
    errorfile 400 /etc/haproxy/errors/400.http
    errorfile 403 /etc/haproxy/errors/403.http
    errorfile 408 /etc/haproxy/errors/408.http
    errorfile 500 /etc/haproxy/errors/500.http
    errorfile 502 /etc/haproxy/errors/502.http
    errorfile 503 /etc/haproxy/errors/503.http
    errorfile 504 /etc/haproxy/errors/504.http

# Frontend for Minecraft proxy
frontend minecraft_frontend
    bind :::{$defaultProxyPort} v4v6
    bind *:{$defaultProxyPort}
    mode tcp
    option tcplog
    
    # Inspect first packet for hostname extraction
    # The inspect-delay allows HAProxy to wait for data before processing
    # This ensures the Minecraft handshake packet is available for inspection
    # Reduced to 2s for better performance (Minecraft clients typically send handshake immediately)
    tcp-request inspect-delay {$inspectDelay}s
    
    # Accept the content ONLY for the first packet (when hostname not yet extracted)
    # This makes data available for Lua script to read via dup()
    # After hostname is extracted, subsequent packets flow normally without inspection
    # Check if variable doesn't exist (first packet) using -f (found) check
    tcp-request content accept if !{ var(sess.hostname_extracted) -f }
    
    # Extract hostname using Lua action and store in variable
    # The Lua script uses dup() which creates a copy without consuming the data
    # This must run AFTER accept so data is available, but BEFORE routing rules
    # The original packet data remains intact and will be forwarded to the backend
    # Only process if hostname hasn't been extracted yet
    tcp-request content lua.extract_minecraft_hostname if !{ var(sess.hostname_extracted) -f }
    
    # Mark hostname as extracted to prevent re-processing subsequent packets
    # This ensures only the first packet is inspected, rest flow normally
    # Set session variable so it persists across all packets in this connection
    tcp-request content set-var(sess.hostname_extracted) bool(true) if !{ var(sess.hostname_extracted) -f }
    
    # Route based on extracted hostname
    # ACL rules are evaluated in order - first matching rule wins
    # Each subdomain has two rules: one for var(txn.minecraft_hostname) and one for lua.minecraft_hostname
    # This ensures routing works even if one method fails
{$frontendAcls}
{$defaultBackendLine}
# Backend definitions
{$backendDefs}
HAPROXY;

        return $config;
    }

    /**
     * Write Lua script to file.
     * 
     * Note: HAProxy uses chroot, so the Lua script path in the config must be relative to the chroot.
     * The chroot is typically /var/lib/haproxy, so we write the script there.
     * Uses sudo to write to protected directories.
     */
    public function writeLuaScript(): bool
    {
        if (!config('proxy.enabled', false)) {
            Log::info('Proxy functionality is disabled, skipping Lua script write');
            return false;
        }

        // HAProxy chroot is /var/lib/haproxy, so we need to place the script there
        // The path in the config should be relative to chroot: /etc/haproxy/minecraft_parser.lua
        // But we write it to the actual filesystem: /var/lib/haproxy/etc/haproxy/minecraft_parser.lua
        // OR we can write it outside chroot and use absolute path (if chroot allows)
        // Let's use the simpler approach: write to /etc/haproxy/ and reference it with absolute path
        // But HAProxy with chroot needs it inside chroot, so we'll write to both locations
        
        $luaScriptPath = '/etc/haproxy/minecraft_parser.lua';
        $chrootLuaScriptPath = '/var/lib/haproxy/etc/haproxy/minecraft_parser.lua';
        
        try {
            $luaScriptContent = File::get(resource_path('haproxy/minecraft_parser.lua'));
        } catch (\Exception $e) {
            Log::error('Failed to read Lua script source', [
                'source' => resource_path('haproxy/minecraft_parser.lua'),
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to read Lua script source: {$e->getMessage()}");
        }

        try {
            // Write to temporary file first (web server can write to storage)
            $tempFile = storage_path('app/haproxy_lua_script_temp.lua');
            File::put($tempFile, $luaScriptContent);
            
            // Use sudo to copy to /etc/haproxy/ (for reference and if chroot is disabled)
            $luaScriptDir = dirname($luaScriptPath);
            $createDirCmd = "sudo mkdir -p {$luaScriptDir}";
            $copyCmd = "sudo cp {$tempFile} {$luaScriptPath}";
            $chmodCmd = "sudo chmod 644 {$luaScriptPath}";
            
            exec("{$createDirCmd} 2>&1", $output1, $returnCode1);
            if ($returnCode1 !== 0) {
                throw new \Exception("Failed to create directory: " . implode("\n", $output1));
            }
            
            exec("{$copyCmd} 2>&1", $output2, $returnCode2);
            if ($returnCode2 !== 0) {
                throw new \Exception("Failed to copy Lua script: " . implode("\n", $output2));
            }
            
            exec("{$chmodCmd} 2>&1", $output3, $returnCode3);
            if ($returnCode3 !== 0) {
                throw new \Exception("Failed to set permissions: " . implode("\n", $output3));
            }

            // Also write to chroot directory (HAProxy with chroot needs it here)
            $chrootLuaScriptDir = dirname($chrootLuaScriptPath);
            $createChrootDirCmd = "sudo mkdir -p {$chrootLuaScriptDir}";
            $copyChrootCmd = "sudo cp {$tempFile} {$chrootLuaScriptPath}";
            $chmodChrootCmd = "sudo chmod 644 {$chrootLuaScriptPath}";
            
            exec("{$createChrootDirCmd} 2>&1", $output4, $returnCode4);
            if ($returnCode4 !== 0) {
                throw new \Exception("Failed to create chroot directory: " . implode("\n", $output4));
            }
            
            exec("{$copyChrootCmd} 2>&1", $output5, $returnCode5);
            if ($returnCode5 !== 0) {
                throw new \Exception("Failed to copy Lua script to chroot: " . implode("\n", $output5));
            }
            
            exec("{$chmodChrootCmd} 2>&1", $output6, $returnCode6);
            if ($returnCode6 !== 0) {
                throw new \Exception("Failed to set chroot permissions: " . implode("\n", $output6));
            }
            
            // Clean up temp file
            File::delete($tempFile);

            Log::info('HAProxy Lua script written', [
                'script_file' => $luaScriptPath,
                'chroot_script_file' => $chrootLuaScriptPath,
            ]);

            return true;
        } catch (\Exception $e) {
            // Clean up temp file if it exists
            if (isset($tempFile) && File::exists($tempFile)) {
                File::delete($tempFile);
            }
            
            Log::error('Failed to write HAProxy Lua script', [
                'script_file' => $luaScriptPath,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to write HAProxy Lua script: {$e->getMessage()}");
        }
    }

    /**
     * Write HAProxy configuration to file.
     */
    public function writeConfig(): bool
    {
        if (!config('proxy.enabled', false)) {
            Log::info('Proxy functionality is disabled, skipping HAProxy config write');
            return false;
        }

        // First, write the Lua script
        try {
            $this->writeLuaScript();
        } catch (\Exception $e) {
            Log::warning('Failed to write Lua script, continuing with config generation', [
                'error' => $e->getMessage(),
            ]);
            // Don't fail completely - config can be written without Lua script (will fail validation though)
        }

        $configPath = config('proxy.haproxy_config_path', '/etc/haproxy/haproxy.cfg');

        // Ensure directory exists (use sudo)
        $configDir = dirname($configPath);
        if (!is_dir($configDir)) {
            $createDirCmd = "sudo mkdir -p {$configDir}";
            exec("{$createDirCmd} 2>&1", $dirOutput, $dirReturnCode);
            if ($dirReturnCode !== 0) {
                Log::error('Failed to create HAProxy config directory', [
                    'path' => $configDir,
                    'error' => implode("\n", $dirOutput),
                ]);
                throw new \Exception("Failed to create HAProxy config directory: " . implode("\n", $dirOutput));
            }
        }

        try {
            // Generate config with error handling
            try {
                $config = $this->generateFullConfig();
            } catch (\Exception $e) {
                Log::error('Failed to generate HAProxy config', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new \Exception("Failed to generate HAProxy config: {$e->getMessage()}", 0, $e);
            }
            
            // Validate config before writing
            if (!$this->validateConfig($config)) {
                throw new \Exception('HAProxy configuration validation failed');
            }
            
            // Backup existing config if it exists and is different
            // Use sudo to check and backup
            if (file_exists($configPath)) {
                $existingConfig = file_get_contents($configPath);
                // Only backup if it's not our auto-generated config
                if (!str_contains($existingConfig, '# HAProxy Configuration for Pyrodactyl Subdomain Proxy')) {
                    $backupPath = $configPath . '.backup.' . date('Y-m-d_H-i-s');
                    exec("sudo cp {$configPath} {$backupPath} 2>&1", $backupOutput, $backupReturnCode);
                    if ($backupReturnCode === 0) {
                        Log::info('Backed up existing HAProxy config', [
                            'backup_file' => $backupPath,
                        ]);
                    }
                }
            }
            
            // Write config to temp file first (web server can write to storage)
            $tempConfigFile = storage_path('app/haproxy_config_temp.cfg');
            File::put($tempConfigFile, $config);
            
            // Use sudo to copy to final location
            $copyCmd = "sudo cp {$tempConfigFile} {$configPath}";
            $chmodCmd = "sudo chmod 644 {$configPath}";
            
            exec("{$copyCmd} 2>&1", $copyOutput, $copyReturnCode);
            if ($copyReturnCode !== 0) {
                throw new \Exception("Failed to copy HAProxy config: " . implode("\n", $copyOutput));
            }
            
            exec("{$chmodCmd} 2>&1", $chmodOutput, $chmodReturnCode);
            if ($chmodReturnCode !== 0) {
                throw new \Exception("Failed to set HAProxy config permissions: " . implode("\n", $chmodOutput));
            }
            
            // Clean up temp file
            File::delete($tempConfigFile);

            Log::info('HAProxy config written', [
                'config_file' => $configPath,
            ]);

            return true;
        } catch (\Exception $e) {
            // Clean up temp file if it exists
            if (isset($tempConfigFile) && File::exists($tempConfigFile)) {
                File::delete($tempConfigFile);
            }
            
            Log::error('Failed to write HAProxy config', [
                'config_file' => $configPath,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to write HAProxy config: {$e->getMessage()}");
        }
    }

    /**
     * Reload HAProxy configuration.
     */
    public function reloadHaproxy(): bool
    {
        if (!config('proxy.enabled', false)) {
            Log::info('Proxy functionality is disabled, skipping HAProxy reload');
            return false;
        }

        $command = config('proxy.haproxy_reload_command', 'sudo systemctl reload haproxy');

        try {
            $output = [];
            $returnCode = 0;
            
            // Use exec to capture output
            exec($command . ' 2>&1', $output, $returnCode);
            $output = implode("\n", $output);

            if ($returnCode === 0) {
                Log::info('HAProxy reloaded successfully', [
                    'command' => $command,
                ]);
                return true;
            } else {
                Log::error('HAProxy reload failed', [
                    'command' => $command,
                    'return_code' => $returnCode,
                    'output' => $output,
                ]);
                throw new \Exception("HAProxy reload failed: {$output}");
            }
        } catch (\Exception $e) {
            Log::error('Exception during HAProxy reload', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to reload HAProxy: {$e->getMessage()}");
        }
    }

    /**
     * Validate HAProxy configuration syntax.
     */
    public function validateConfig(?string $config = null): bool
    {
        $configPath = config('proxy.haproxy_config_path', '/etc/haproxy/haproxy.cfg');

        // If config is provided, write to temp file for validation
        if ($config !== null) {
            $tempFile = tempnam(sys_get_temp_dir(), 'haproxy_validate_');
            File::put($tempFile, $config);
            $configPath = $tempFile;
        }

        try {
            $command = "haproxy -c -f {$configPath} 2>&1";
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);
            $output = implode("\n", $output);

            // Clean up temp file if we created one
            if ($config !== null && File::exists($tempFile)) {
                File::delete($tempFile);
            }

            if ($returnCode === 0) {
                return true;
            } else {
                Log::warning('HAProxy config validation failed', [
                    'output' => $output,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            // Clean up temp file on error
            if ($config !== null && isset($tempFile) && File::exists($tempFile)) {
                File::delete($tempFile);
            }
            Log::warning('Exception during HAProxy config validation', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get current timestamp in ISO 8601 format.
     */
    private function getCurrentTimestamp(): string
    {
        return (new \DateTime())->format(\DateTime::ATOM);
    }
}
