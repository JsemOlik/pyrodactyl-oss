<?php

namespace Pterodactyl\Services\Vps;

use Ramsey\Uuid\Uuid;
use Pterodactyl\Models\Vps;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Services\Proxmox\ProxmoxApiClient;
use Pterodactyl\Services\Proxmox\ProxmoxApiException;

/**
 * Service for creating VPS servers.
 */
class VpsCreationService
{
    public function __construct(
        private ConnectionInterface $connection,
        private ProxmoxApiClient $proxmoxClient
    ) {
    }

    /**
     * Create a new VPS server.
     *
     * @param array $data VPS creation data
     * @return Vps Created VPS instance
     * @throws \Throwable
     */
    public function handle(array $data): Vps
    {
        return $this->connection->transaction(function () use ($data) {
            // Generate UUID
            $uuid = $this->generateUniqueUuid();
            $uuidShort = substr($uuid, 0, 8);

            // Get Proxmox configuration from settings
            $proxmoxNode = $data['proxmox_node'] ?? config('proxmox.node');
            $proxmoxStorage = $data['proxmox_storage'] ?? config('proxmox.storage');
            $template = $data['template'] ?? config('proxmox.template');

            if (empty($proxmoxNode) || empty($proxmoxStorage) || empty($template)) {
                throw new \RuntimeException('Proxmox configuration is incomplete. Please configure Proxmox settings in the admin panel.');
            }

            // Create VPS record in database first
            $vps = Vps::create([
                'uuid' => $uuid,
                'uuidShort' => $uuidShort,
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'status' => Vps::STATUS_CREATING,
                'owner_id' => $data['owner_id'],
                'subscription_id' => $data['subscription_id'] ?? null,
                'memory' => $data['memory'],
                'disk' => $data['disk'],
                'cpu_cores' => $data['cpu_cores'],
                'cpu_sockets' => $data['cpu_sockets'] ?? 1,
                'distribution' => $data['distribution'] ?? 'ubuntu-server',
                'proxmox_node' => $proxmoxNode,
                'proxmox_storage' => $proxmoxStorage,
            ]);

            try {
                // Get next available VM ID from Proxmox
                $vmId = $this->proxmoxClient->getNextVmId($proxmoxNode);
                
                // Build VM configuration for Proxmox
                $vmConfig = $this->buildVmConfig($data, $template, $proxmoxStorage, $vmId);

                // Create VM in Proxmox
                $this->proxmoxClient->createVm($proxmoxNode, $vmId, $vmConfig);

                // Update VPS with Proxmox VM ID
                $vps->update([
                    'proxmox_vm_id' => $vmId,
                    'status' => Vps::STATUS_RUNNING, // VM is created and should start
                ]);

                Log::info('VPS created successfully', [
                    'vps_id' => $vps->id,
                    'vps_uuid' => $vps->uuid,
                    'proxmox_vm_id' => $vmId,
                    'proxmox_node' => $proxmoxNode,
                ]);

                return $vps;
            } catch (ProxmoxApiException $e) {
                Log::error('Failed to create VPS in Proxmox', [
                    'vps_id' => $vps->id,
                    'vps_uuid' => $vps->uuid,
                    'error' => $e->getMessage(),
                ]);

                // Update VPS status to error
                $vps->update(['status' => Vps::STATUS_ERROR]);

                throw new \RuntimeException('Failed to create VPS in Proxmox: ' . $e->getMessage(), 0, $e);
            }
        }, 5);
    }

    /**
     * Build VM configuration for Proxmox API.
     */
    private function buildVmConfig(array $data, string $template, string $storage, int $vmId): array
    {
        // Convert memory from MB to bytes for Proxmox
        $memoryBytes = ($data['memory'] ?? 1024) * 1024 * 1024;
        
        // Convert disk from MB to GB for Proxmox
        $diskGb = round(($data['disk'] ?? 10240) / 1024, 2);

        // Sanitize VM name to be DNS-compatible (Proxmox requirement)
        $vmName = $this->sanitizeVmName($data['name'] ?? 'vps-' . $vmId);

        // Build cloud-init configuration
        $config = [
            'vmid' => $vmId,
            'name' => $vmName,
            'memory' => $memoryBytes,
            'cores' => $data['cpu_cores'] ?? 1,
            'sockets' => $data['cpu_sockets'] ?? 1,
            'net0' => 'virtio,bridge=vmbr0,firewall=1',
            'scsi0' => "{$storage}:{$diskGb},format=raw",
            'ide2' => "{$storage}:cloudinit",
            'boot' => 'order=scsi0',
            'agent' => '1',
            'onboot' => 1,
        ];

        // Add template if provided
        if (!empty($template)) {
            // Check if template is a storage path or template ID
            if (str_contains($template, ':')) {
                // Template is in format "storage:template-name"
                $config['scsi0'] = "{$template},format=raw";
            } else {
                // Assume template is a template ID in the storage
                $config['scsi0'] = "{$storage}:{$template},format=raw";
            }
        }

        return $config;
    }

    /**
     * Generate a unique UUID for the VPS.
     */
    private function generateUniqueUuid(): string
    {
        do {
            $uuid = Uuid::uuid4()->toString();
            $exists = Vps::where('uuid', $uuid)
                ->orWhere('uuidShort', substr($uuid, 0, 8))
                ->exists();
        } while ($exists);

        return $uuid;
    }

    /**
     * Sanitize VM name to be DNS-compatible for Proxmox.
     * Proxmox requires VM names to be valid DNS names (alphanumeric and hyphens only).
     *
     * @param string $name Original VM name
     * @return string Sanitized DNS-compatible name
     */
    private function sanitizeVmName(string $name): string
    {
        // Convert to lowercase
        $sanitized = strtolower(trim($name));
        
        // Replace spaces and invalid characters with hyphens
        $sanitized = preg_replace('/[^a-z0-9-]/', '-', $sanitized);
        
        // Remove multiple consecutive hyphens
        $sanitized = preg_replace('/-+/', '-', $sanitized);
        
        // Remove leading/trailing hyphens
        $sanitized = trim($sanitized, '-');
        
        // Ensure it's not empty and has a max length (Proxmox typically allows up to 64 chars)
        if (empty($sanitized)) {
            $sanitized = 'vps';
        }
        
        // Limit to 64 characters (Proxmox limit)
        if (strlen($sanitized) > 64) {
            $sanitized = substr($sanitized, 0, 64);
            $sanitized = rtrim($sanitized, '-');
        }
        
        return $sanitized;
    }
}

