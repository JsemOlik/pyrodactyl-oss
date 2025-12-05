<?php

namespace Pterodactyl\Observers;

use Pterodactyl\Models\ServerSubdomain;
use Pterodactyl\Services\Proxy\ProxyManagementService;
use Illuminate\Support\Facades\Log;

class ServerSubdomainObserver
{
    public function __construct(
        private ProxyManagementService $proxyService
    ) {
    }

    /**
     * Handle the ServerSubdomain "created" event.
     */
    public function created(ServerSubdomain $subdomain): void
    {
        if (!$subdomain->proxy_port) {
            return;
        }

        try {
            $this->proxyService->createProxy($subdomain);
        } catch (\Exception $e) {
            // Log error but don't fail the subdomain creation
            Log::error('Failed to create proxy after subdomain creation', [
                'subdomain_id' => $subdomain->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the ServerSubdomain "updated" event.
     */
    public function updated(ServerSubdomain $subdomain): void
    {
        // Check if proxy_port or allocation changed
        if ($subdomain->wasChanged('proxy_port') || $subdomain->wasChanged('is_active')) {
            try {
                if ($subdomain->is_active && $subdomain->proxy_port) {
                    $this->proxyService->updateProxy($subdomain);
                } else {
                    // If subdomain is deactivated or proxy_port removed, delete proxy
                    $this->proxyService->deleteProxy($subdomain);
                }
            } catch (\Exception $e) {
                // Log error but don't fail the subdomain update
                Log::error('Failed to update proxy after subdomain update', [
                    'subdomain_id' => $subdomain->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Also check if server allocation changed (need to reload server relationship)
        if ($subdomain->relationLoaded('server') && $subdomain->server->relationLoaded('allocation')) {
            $allocation = $subdomain->server->allocation;
            if ($allocation && ($allocation->wasChanged('ip') || $allocation->wasChanged('port') || $allocation->wasChanged('ip_alias'))) {
                try {
                    if ($subdomain->is_active && $subdomain->proxy_port) {
                        $this->proxyService->updateProxy($subdomain);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to update proxy after allocation change', [
                        'subdomain_id' => $subdomain->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Handle the ServerSubdomain "deleted" event.
     */
    public function deleted(ServerSubdomain $subdomain): void
    {
        try {
            $this->proxyService->deleteProxy($subdomain);
        } catch (\Exception $e) {
            // Log error but don't fail the subdomain deletion
            Log::error('Failed to delete proxy after subdomain deletion', [
                'subdomain_id' => $subdomain->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
