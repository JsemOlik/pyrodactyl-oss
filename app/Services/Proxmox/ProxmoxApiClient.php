<?php

namespace Pterodactyl\Services\Proxmox;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * Client for interacting with the Proxmox API.
 */
class ProxmoxApiClient
{
    private ?Client $httpClient = null;

    /**
     * Get the HTTP client instance for Proxmox API requests.
     */
    private function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            $baseUrl = config('proxmox.url');
            $apiToken = config('proxmox.api_token');

            if (empty($baseUrl) || empty($apiToken)) {
                throw new \RuntimeException('Proxmox configuration is missing. Please configure Proxmox settings in the admin panel.');
            }

            // Remove trailing slash from base URL
            $baseUrl = rtrim($baseUrl, '/');

            $this->httpClient = new Client([
                'verify' => config('proxmox.verify_ssl', true),
                'base_uri' => $baseUrl,
                'timeout' => config('proxmox.timeout', 30),
                'connect_timeout' => config('proxmox.connect_timeout', 10),
                'headers' => [
                    'Authorization' => 'PVEAPIToken=' . $apiToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]);
        }

        return $this->httpClient;
    }

    /**
     * Make a request to the Proxmox API.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint (e.g., '/api2/json/nodes/pve/qemu')
     * @param array $data Request data (for POST/PUT)
     * @return array Response data
     * @throws ProxmoxApiException
     */
    private function request(string $method, string $endpoint, array $data = []): array
    {
        try {
            $options = [];
            if (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->getHttpClient()->request($method, $endpoint, $options);
            $body = $response->getBody()->getContents();
            $decoded = json_decode($body, true);

            // Proxmox API returns data in 'data' key for successful responses
            if (isset($decoded['data'])) {
                return $decoded['data'];
            }

            return $decoded;
        } catch (GuzzleException $e) {
            Log::error('Proxmox API request failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw new ProxmoxApiException('Proxmox API request failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create a new VM in Proxmox.
     *
     * @param string $node Proxmox node name
     * @param int $vmid VM ID (auto-assigned if 0)
     * @param array $config VM configuration
     * @return array Created VM data
     * @throws ProxmoxApiException
     */
    public function createVm(string $node, int $vmid, array $config): array
    {
        $endpoint = "/api2/json/nodes/{$node}/qemu";
        $data = array_merge(['vmid' => $vmid], $config);

        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Get VM status.
     *
     * @param string $node Proxmox node name
     * @param int $vmid VM ID
     * @return array VM status data
     * @throws ProxmoxApiException
     */
    public function getVmStatus(string $node, int $vmid): array
    {
        $endpoint = "/api2/json/nodes/{$node}/qemu/{$vmid}/status/current";

        return $this->request('GET', $endpoint);
    }

    /**
     * Start a VM.
     *
     * @param string $node Proxmox node name
     * @param int $vmid VM ID
     * @return array Response data
     * @throws ProxmoxApiException
     */
    public function startVm(string $node, int $vmid): array
    {
        $endpoint = "/api2/json/nodes/{$node}/qemu/{$vmid}/status/start";

        return $this->request('POST', $endpoint);
    }

    /**
     * Stop a VM (graceful shutdown).
     *
     * @param string $node Proxmox node name
     * @param int $vmid VM ID
     * @return array Response data
     * @throws ProxmoxApiException
     */
    public function stopVm(string $node, int $vmid): array
    {
        $endpoint = "/api2/json/nodes/{$node}/qemu/{$vmid}/status/stop";

        return $this->request('POST', $endpoint);
    }

    /**
     * Shutdown a VM (graceful).
     *
     * @param string $node Proxmox node name
     * @param int $vmid VM ID
     * @return array Response data
     * @throws ProxmoxApiException
     */
    public function shutdownVm(string $node, int $vmid): array
    {
        $endpoint = "/api2/json/nodes/{$node}/qemu/{$vmid}/status/shutdown";

        return $this->request('POST', $endpoint);
    }

    /**
     * Reboot a VM.
     *
     * @param string $node Proxmox node name
     * @param int $vmid VM ID
     * @return array Response data
     * @throws ProxmoxApiException
     */
    public function rebootVm(string $node, int $vmid): array
    {
        $endpoint = "/api2/json/nodes/{$node}/qemu/{$vmid}/status/reboot";

        return $this->request('POST', $endpoint);
    }

    /**
     * Kill a VM (force stop).
     *
     * @param string $node Proxmox node name
     * @param int $vmid VM ID
     * @return array Response data
     * @throws ProxmoxApiException
     */
    public function killVm(string $node, int $vmid): array
    {
        $endpoint = "/api2/json/nodes/{$node}/qemu/{$vmid}/status/stop";
        $data = ['timeout' => 0]; // Force immediate stop

        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Get VM metrics (RRD data).
     *
     * @param string $node Proxmox node name
     * @param int $vmid VM ID
     * @param string $timeframe Timeframe (e.g., 'hour', 'day', 'week')
     * @return array Metrics data
     * @throws ProxmoxApiException
     */
    public function getVmMetrics(string $node, int $vmid, string $timeframe = 'hour'): array
    {
        $endpoint = "/api2/json/nodes/{$node}/qemu/{$vmid}/rrddata";
        $data = ['timeframe' => $timeframe];

        return $this->request('GET', $endpoint . '?' . http_build_query($data));
    }

    /**
     * Get VM configuration.
     *
     * @param string $node Proxmox node name
     * @param int $vmid VM ID
     * @return array VM configuration
     * @throws ProxmoxApiException
     */
    public function getVmConfig(string $node, int $vmid): array
    {
        $endpoint = "/api2/json/nodes/{$node}/qemu/{$vmid}/config";

        return $this->request('GET', $endpoint);
    }

    /**
     * Update VM configuration.
     *
     * @param string $node Proxmox node name
     * @param int $vmid VM ID
     * @param array $config Configuration changes
     * @return array Response data
     * @throws ProxmoxApiException
     */
    public function updateVmConfig(string $node, int $vmid, array $config): array
    {
        $endpoint = "/api2/json/nodes/{$node}/qemu/{$vmid}/config";

        return $this->request('PUT', $endpoint, $config);
    }

    /**
     * Delete a VM.
     *
     * @param string $node Proxmox node name
     * @param int $vmid VM ID
     * @return array Response data
     * @throws ProxmoxApiException
     */
    public function deleteVm(string $node, int $vmid): array
    {
        $endpoint = "/api2/json/nodes/{$node}/qemu/{$vmid}";

        return $this->request('DELETE', $endpoint);
    }

    /**
     * Get next available VM ID.
     *
     * @param string $node Proxmox node name (not used but kept for consistency)
     * @return int Next available VM ID
     * @throws ProxmoxApiException
     */
    public function getNextVmId(string $node = ''): int
    {
        $endpoint = "/api2/json/cluster/nextid";

        $result = $this->request('GET', $endpoint);
        
        // Proxmox returns the next ID directly in the data field
        return (int) ($result ?? 100);
    }
}

