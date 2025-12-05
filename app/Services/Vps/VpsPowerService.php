<?php

namespace Pterodactyl\Services\Vps;

use Pterodactyl\Models\Vps;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Services\Proxmox\ProxmoxApiClient;
use Pterodactyl\Services\Proxmox\ProxmoxApiException;

/**
 * Service for managing VPS power operations.
 */
class VpsPowerService
{
    public function __construct(
        private ProxmoxApiClient $proxmoxClient
    ) {
    }

    /**
     * Start a VPS.
     *
     * @throws ProxmoxApiException
     */
    public function start(Vps $vps): void
    {
        if (!$vps->proxmox_node || !$vps->proxmox_vm_id) {
            throw new \RuntimeException('VPS is not properly configured with Proxmox information.');
        }

        try {
            $this->proxmoxClient->startVm($vps->proxmox_node, $vps->proxmox_vm_id);
            
            $vps->update(['status' => Vps::STATUS_STARTING]);
            
            Log::info('VPS started', [
                'vps_id' => $vps->id,
                'vps_uuid' => $vps->uuid,
                'proxmox_vm_id' => $vps->proxmox_vm_id,
            ]);
        } catch (ProxmoxApiException $e) {
            Log::error('Failed to start VPS', [
                'vps_id' => $vps->id,
                'vps_uuid' => $vps->uuid,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Stop a VPS (graceful shutdown).
     *
     * @throws ProxmoxApiException
     */
    public function stop(Vps $vps): void
    {
        if (!$vps->proxmox_node || !$vps->proxmox_vm_id) {
            throw new \RuntimeException('VPS is not properly configured with Proxmox information.');
        }

        try {
            $this->proxmoxClient->stopVm($vps->proxmox_node, $vps->proxmox_vm_id);
            
            $vps->update(['status' => Vps::STATUS_STOPPING]);
            
            Log::info('VPS stopped', [
                'vps_id' => $vps->id,
                'vps_uuid' => $vps->uuid,
                'proxmox_vm_id' => $vps->proxmox_vm_id,
            ]);
        } catch (ProxmoxApiException $e) {
            Log::error('Failed to stop VPS', [
                'vps_id' => $vps->id,
                'vps_uuid' => $vps->uuid,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Reboot a VPS.
     *
     * @throws ProxmoxApiException
     */
    public function reboot(Vps $vps): void
    {
        if (!$vps->proxmox_node || !$vps->proxmox_vm_id) {
            throw new \RuntimeException('VPS is not properly configured with Proxmox information.');
        }

        try {
            $this->proxmoxClient->rebootVm($vps->proxmox_node, $vps->proxmox_vm_id);
            
            $vps->update(['status' => Vps::STATUS_REBOOTING]);
            
            Log::info('VPS rebooted', [
                'vps_id' => $vps->id,
                'vps_uuid' => $vps->uuid,
                'proxmox_vm_id' => $vps->proxmox_vm_id,
            ]);
        } catch (ProxmoxApiException $e) {
            Log::error('Failed to reboot VPS', [
                'vps_id' => $vps->id,
                'vps_uuid' => $vps->uuid,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Kill a VPS (force stop).
     *
     * @throws ProxmoxApiException
     */
    public function kill(Vps $vps): void
    {
        if (!$vps->proxmox_node || !$vps->proxmox_vm_id) {
            throw new \RuntimeException('VPS is not properly configured with Proxmox information.');
        }

        try {
            $this->proxmoxClient->killVm($vps->proxmox_node, $vps->proxmox_vm_id);
            
            $vps->update(['status' => Vps::STATUS_STOPPED]);
            
            Log::info('VPS killed', [
                'vps_id' => $vps->id,
                'vps_uuid' => $vps->uuid,
                'proxmox_vm_id' => $vps->proxmox_vm_id,
            ]);
        } catch (ProxmoxApiException $e) {
            Log::error('Failed to kill VPS', [
                'vps_id' => $vps->id,
                'vps_uuid' => $vps->uuid,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Update VPS status from Proxmox.
     *
     * @throws ProxmoxApiException
     */
    public function updateStatus(Vps $vps): void
    {
        if (!$vps->proxmox_node || !$vps->proxmox_vm_id) {
            return;
        }

        try {
            $status = $this->proxmoxClient->getVmStatus($vps->proxmox_node, $vps->proxmox_vm_id);
            
            // Map Proxmox status to our status constants
            $proxmoxStatus = $status['status'] ?? 'unknown';
            $vpsStatus = match ($proxmoxStatus) {
                'running' => Vps::STATUS_RUNNING,
                'stopped' => Vps::STATUS_STOPPED,
                default => $vps->status,
            };
            
            if ($vpsStatus !== $vps->status) {
                $vps->update(['status' => $vpsStatus]);
            }
        } catch (ProxmoxApiException $e) {
            Log::warning('Failed to update VPS status from Proxmox', [
                'vps_id' => $vps->id,
                'vps_uuid' => $vps->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

