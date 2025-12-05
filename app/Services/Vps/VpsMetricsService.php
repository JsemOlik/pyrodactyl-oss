<?php

namespace Pterodactyl\Services\Vps;

use Pterodactyl\Models\Vps;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Services\Proxmox\ProxmoxApiClient;
use Pterodactyl\Services\Proxmox\ProxmoxApiException;

/**
 * Service for fetching VPS metrics from Proxmox.
 */
class VpsMetricsService
{
    public function __construct(
        private ProxmoxApiClient $proxmoxClient
    ) {
    }

    /**
     * Get VPS metrics (CPU, RAM, disk usage).
     *
     * @param string $timeframe Timeframe for metrics (hour, day, week)
     * @return array Metrics data
     * @throws ProxmoxApiException
     */
    public function getMetrics(Vps $vps, string $timeframe = 'hour'): array
    {
        if (!$vps->proxmox_node || !$vps->proxmox_vm_id) {
            return $this->getEmptyMetrics();
        }

        $cacheKey = "vps_metrics_{$vps->id}_{$timeframe}";
        
        return Cache::remember($cacheKey, 60, function () use ($vps, $timeframe) {
            try {
                $rrdData = $this->proxmoxClient->getVmMetrics(
                    $vps->proxmox_node,
                    $vps->proxmox_vm_id,
                    $timeframe
                );

                return $this->parseMetrics($rrdData, $vps);
            } catch (ProxmoxApiException $e) {
                return $this->getEmptyMetrics();
            }
        });
    }

    /**
     * Parse Proxmox RRD data into a structured format.
     */
    private function parseMetrics(array $rrdData, Vps $vps): array
    {
        // Proxmox RRD data structure varies, but typically includes:
        // - CPU usage percentage
        // - Memory usage in bytes
        // - Disk I/O
        // - Network I/O
        
        $latest = end($rrdData) ?? [];
        
        return [
            'cpu' => [
                'usage_percent' => (float) ($latest['cpu'] ?? 0),
                'cores' => $vps->cpu_cores,
                'sockets' => $vps->cpu_sockets,
            ],
            'memory' => [
                'used_bytes' => (int) ($latest['mem'] ?? 0),
                'total_bytes' => $vps->memory * 1024 * 1024, // Convert MB to bytes
                'usage_percent' => $vps->memory > 0 
                    ? (($latest['mem'] ?? 0) / ($vps->memory * 1024 * 1024)) * 100 
                    : 0,
            ],
            'disk' => [
                'used_bytes' => (int) ($latest['disk'] ?? 0),
                'total_bytes' => $vps->disk * 1024 * 1024, // Convert MB to bytes
                'usage_percent' => $vps->disk > 0 
                    ? (($latest['disk'] ?? 0) / ($vps->disk * 1024 * 1024)) * 100 
                    : 0,
            ],
            'network' => [
                'rx_bytes' => (int) ($latest['netin'] ?? 0),
                'tx_bytes' => (int) ($latest['netout'] ?? 0),
            ],
            'uptime' => (int) ($latest['uptime'] ?? 0),
            'timestamp' => time(),
        ];
    }

    /**
     * Return empty metrics structure.
     */
    private function getEmptyMetrics(): array
    {
        return [
            'cpu' => [
                'usage_percent' => 0,
                'cores' => 0,
                'sockets' => 0,
            ],
            'memory' => [
                'used_bytes' => 0,
                'total_bytes' => 0,
                'usage_percent' => 0,
            ],
            'disk' => [
                'used_bytes' => 0,
                'total_bytes' => 0,
                'usage_percent' => 0,
            ],
            'network' => [
                'rx_bytes' => 0,
                'tx_bytes' => 0,
            ],
            'uptime' => 0,
            'timestamp' => time(),
        ];
    }
}

