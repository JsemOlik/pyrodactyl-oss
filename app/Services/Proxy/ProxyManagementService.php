<?php

namespace Pterodactyl\Services\Proxy;

use Pterodactyl\Models\ServerSubdomain;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProxyManagementService
{
    public function __construct(
        private NginxStreamService $nginxService,
        private HaproxyService $haproxyService
    ) {
    }

    /**
     * Get the appropriate proxy service based on configuration.
     */
    private function getProxyService(): NginxStreamService|HaproxyService
    {
        $proxyType = config('proxy.proxy_type', 'haproxy');
        
        return $proxyType === 'haproxy' ? $this->haproxyService : $this->nginxService;
    }

    /**
     * Check if a proxy port is already in use.
     */
    public function isProxyPortInUse(int $proxyPort, ?int $excludeSubdomainId = null): bool
    {
        $query = ServerSubdomain::where('proxy_port', $proxyPort)
            ->where('is_active', true);

        if ($excludeSubdomainId) {
            $query->where('id', '!=', $excludeSubdomainId);
        }

        return $query->exists();
    }

    /**
     * Create proxy configuration for a subdomain.
     */
    public function createProxy(ServerSubdomain $subdomain): bool
    {
        if (!config('proxy.enabled', false)) {
            Log::info('Proxy functionality is disabled, skipping proxy creation', [
                'subdomain_id' => $subdomain->id,
            ]);
            return false;
        }

        // Only create proxy if proxy_port is set
        if (!$subdomain->proxy_port) {
            Log::info('Subdomain does not have proxy_port set, skipping proxy creation', [
                'subdomain_id' => $subdomain->id,
            ]);
            return false;
        }

        // Check for port conflicts (only for NGINX - HAProxy allows same port for multiple subdomains)
        $proxyType = config('proxy.proxy_type', 'haproxy');
        if ($proxyType === 'nginx' && $this->isProxyPortInUse($subdomain->proxy_port, $subdomain->id)) {
            throw new \Exception("Proxy port {$subdomain->proxy_port} is already in use by another active subdomain. Each subdomain must use a unique proxy port.");
        }

        try {
            $proxyType = config('proxy.proxy_type', 'haproxy');
            
            if ($proxyType === 'haproxy') {
                // HAProxy uses a single config file for all subdomains
                $this->haproxyService->writeConfig();
                $this->haproxyService->reloadHaproxy();
            } else {
                // NGINX uses individual config files per subdomain
                $this->nginxService->writeConfig($subdomain);
                $this->nginxService->reloadNginx();
            }

            Log::info('Proxy created successfully', [
                'subdomain_id' => $subdomain->id,
                'proxy_port' => $subdomain->proxy_port,
                'proxy_type' => $proxyType,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create proxy', [
                'subdomain_id' => $subdomain->id,
                'error' => $e->getMessage(),
            ]);

            // Attempt to clean up on failure (only for NGINX)
            if (config('proxy.proxy_type', 'haproxy') === 'nginx') {
                try {
                    $this->nginxService->deleteConfig($subdomain);
                } catch (\Exception $cleanupException) {
                    Log::warning('Failed to cleanup proxy config after creation failure', [
                        'subdomain_id' => $subdomain->id,
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * Update proxy configuration for a subdomain.
     */
    public function updateProxy(ServerSubdomain $subdomain): bool
    {
        if (!config('proxy.enabled', false)) {
            Log::info('Proxy functionality is disabled, skipping proxy update', [
                'subdomain_id' => $subdomain->id,
            ]);
            return false;
        }

        // If proxy_port was removed, delete the proxy
        if (!$subdomain->proxy_port) {
            return $this->deleteProxy($subdomain);
        }

        // Check for port conflicts (only for NGINX - HAProxy allows same port for multiple subdomains)
        $proxyType = config('proxy.proxy_type', 'haproxy');
        if ($proxyType === 'nginx' && $this->isProxyPortInUse($subdomain->proxy_port, $subdomain->id)) {
            throw new \Exception("Proxy port {$subdomain->proxy_port} is already in use by another active subdomain. Each subdomain must use a unique proxy port.");
        }

        try {
            $proxyType = config('proxy.proxy_type', 'haproxy');
            
            if ($proxyType === 'haproxy') {
                // HAProxy uses a single config file for all subdomains
                $this->haproxyService->writeConfig();
                $this->haproxyService->reloadHaproxy();
            } else {
                // NGINX uses individual config files per subdomain
                $this->nginxService->writeConfig($subdomain);
                $this->nginxService->reloadNginx();
            }

            Log::info('Proxy updated successfully', [
                'subdomain_id' => $subdomain->id,
                'proxy_port' => $subdomain->proxy_port,
                'proxy_type' => $proxyType,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update proxy', [
                'subdomain_id' => $subdomain->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete proxy configuration for a subdomain.
     */
    public function deleteProxy(ServerSubdomain $subdomain): bool
    {
        if (!config('proxy.enabled', false)) {
            Log::info('Proxy functionality is disabled, skipping proxy deletion', [
                'subdomain_id' => $subdomain->id,
            ]);
            return false;
        }

        try {
            $proxyType = config('proxy.proxy_type', 'haproxy');
            
            if ($proxyType === 'haproxy') {
                // HAProxy uses a single config file - regenerate it without this subdomain
                $this->haproxyService->writeConfig();
                $this->haproxyService->reloadHaproxy();
            } else {
                // NGINX uses individual config files per subdomain
                $this->nginxService->deleteConfig($subdomain);
                $this->nginxService->reloadNginx();
            }

            Log::info('Proxy deleted successfully', [
                'subdomain_id' => $subdomain->id,
                'proxy_type' => $proxyType,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete proxy', [
                'subdomain_id' => $subdomain->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Sync all active subdomains with proxy configurations.
     * Useful for initial setup or recovery.
     */
    public function syncAllProxies(): void
    {
        if (!config('proxy.enabled', false)) {
            Log::info('Proxy functionality is disabled, skipping sync');
            return;
        }

        $subdomains = ServerSubdomain::where('is_active', true)
            ->whereNotNull('proxy_port')
            ->with(['server.allocation'])
            ->get();

        $successCount = 0;
        $failureCount = 0;

        $proxyType = config('proxy.proxy_type', 'haproxy');
        
        if ($proxyType === 'haproxy') {
            // HAProxy uses a single config file - generate it once
            try {
                $this->haproxyService->writeConfig();
                $successCount = $subdomains->count();
            } catch (\Exception $e) {
                Log::error('Failed to sync HAProxy config', [
                    'error' => $e->getMessage(),
                ]);
                $failureCount = $subdomains->count();
            }
            
            // Reload HAProxy once
            if ($successCount > 0) {
                try {
                    $this->haproxyService->reloadHaproxy();
                } catch (\Exception $e) {
                    Log::error('Failed to reload HAProxy after sync', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } else {
            // NGINX uses individual config files per subdomain
            foreach ($subdomains as $subdomain) {
                try {
                    $this->nginxService->writeConfig($subdomain);
                    $successCount++;
                } catch (\Exception $e) {
                    Log::error('Failed to sync proxy for subdomain', [
                        'subdomain_id' => $subdomain->id,
                        'error' => $e->getMessage(),
                    ]);
                    $failureCount++;
                }
            }

            // Reload NGINX once after all configs are written
            if ($successCount > 0) {
                try {
                    $this->nginxService->reloadNginx();
                } catch (\Exception $e) {
                    Log::error('Failed to reload NGINX after sync', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        Log::info('Proxy sync completed', [
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ]);
    }
}
