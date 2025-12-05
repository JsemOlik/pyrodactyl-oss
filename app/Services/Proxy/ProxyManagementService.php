<?php

namespace Pterodactyl\Services\Proxy;

use Pterodactyl\Models\ServerSubdomain;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProxyManagementService
{
    public function __construct(
        private NginxStreamService $nginxService
    ) {
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

        try {
            $this->nginxService->writeConfig($subdomain);
            $this->nginxService->reloadNginx();

            Log::info('Proxy created successfully', [
                'subdomain_id' => $subdomain->id,
                'proxy_port' => $subdomain->proxy_port,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create proxy', [
                'subdomain_id' => $subdomain->id,
                'error' => $e->getMessage(),
            ]);

            // Attempt to clean up on failure
            try {
                $this->nginxService->deleteConfig($subdomain);
            } catch (\Exception $cleanupException) {
                Log::warning('Failed to cleanup proxy config after creation failure', [
                    'subdomain_id' => $subdomain->id,
                    'error' => $cleanupException->getMessage(),
                ]);
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

        try {
            $this->nginxService->writeConfig($subdomain);
            $this->nginxService->reloadNginx();

            Log::info('Proxy updated successfully', [
                'subdomain_id' => $subdomain->id,
                'proxy_port' => $subdomain->proxy_port,
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
            $this->nginxService->deleteConfig($subdomain);
            $this->nginxService->reloadNginx();

            Log::info('Proxy deleted successfully', [
                'subdomain_id' => $subdomain->id,
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

        Log::info('Proxy sync completed', [
            'success_count' => $successCount,
            'failure_count' => $failureCount,
        ]);
    }
}
