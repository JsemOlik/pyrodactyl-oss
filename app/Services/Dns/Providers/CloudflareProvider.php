<?php

namespace Pterodactyl\Services\Dns\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Pterodactyl\Contracts\Dns\DnsProviderInterface;
use Pterodactyl\Exceptions\Dns\DnsProviderException;
use Illuminate\Support\Facades\Log;

use Pterodactyl\Services\Dns;


class CloudflareProvider implements DnsProviderInterface
{
    private Client $client;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;

        // Only initialize the client if we have an API token
        if (!empty($config['api_token'])) {
            $this->client = new Client([
                'base_uri' => 'https://api.cloudflare.com/client/v4/',
                'headers' => [
                    'Authorization' => 'Bearer ' . $config['api_token'],
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 30,
            ]);
        }
    }

    /**
     * Test the connection to Cloudflare API.
     */
    public function testConnection(): bool
    {
        if (!isset($this->client)) {
            throw DnsProviderException::invalidConfiguration('cloudflare', 'api_token');
        }

        try {
            $response = $this->client->get('user/tokens/verify');
            $data = json_decode($response->getBody()->getContents(), true);

            if (!$data['success']) {
                throw DnsProviderException::authenticationFailed('cloudflare');
            }

            return true;
        } catch (GuzzleException $e) {
            throw DnsProviderException::connectionFailed('cloudflare', $e->getMessage());
        }
    }

    /**
     * Create a DNS record.
     */
    public function createRecord(string $domain, string $name, string $type, $content, int $ttl = 300): string
    {
        $zoneId = $this->getZoneId($domain);

        // Normalize name to be relative to the zone (Cloudflare prefers relative names)
        $name = $this->normalizeRecordName($name, $domain);

        try {
            $payload = [
                'type' => strtoupper($type),
                'name' => $name,
                'ttl' => $ttl,
            ];

            // Handle different content types
            if (is_array($content)) {
                // For SRV records, Cloudflare expects specific format in 'data' field
                if (strtoupper($type) === 'SRV') {
                    // Extract values with proper defaults - SRV targets should be relative to zone
                    $service = isset($content['service']) ? (string) $content['service'] : '';
                    $proto = isset($content['proto']) ? (string) $content['proto'] : '';
                    $priority = isset($content['priority']) ? (int) $content['priority'] : 0;
                    $weight = isset($content['weight']) ? (int) $content['weight'] : 5;
                    $port = isset($content['port']) ? (int) $content['port'] : 0;
                    
                    // Target should be normalized to be relative to the zone
                    $target = '';
                    if (!empty($content['target'])) {
                        $target = $this->normalizeRecordName((string) $content['target'], $domain);
                    }
                    
                    // If target is empty, fall back to using the name without the service prefix
                    if (empty($target)) {
                        // Extract the base subdomain from the SRV name (e.g., "_ts3._udp.teamspeak3" -> "teamspeak3")
                        $target = preg_replace('/^_[^._]+\._[^._]+\./', '', $name);
                        if (empty($target)) {
                            $target = $name;
                        }
                    }
                    
                    // Ensure all required fields are present - Cloudflare requires weight, port, and target
                    $payload['data'] = [
                        'service' => $service,
                        'proto' => $proto,
                        'name' => $target,
                        'priority' => $priority,
                        'weight' => $weight,
                        'port' => $port,
                        'target' => $target,
                    ];
                } else {
                    // For other structured data types
                    $payload['data'] = $content;
                    if (isset($content['content'])) {
                        $payload['content'] = $content['content'];
                    }
                }
            } else {
                // For simple records like A, CNAME
                $payload['content'] = $content;
            }

            // Log SRV payload for debugging
            if (strtoupper($type) === 'SRV') {
                Log::debug('Creating SRV record', [
                    'domain' => $domain,
                    'name' => $name,
                    'payload' => $payload,
                ]);
            }

            $response = $this->client->post("zones/{$zoneId}/dns_records", [
                'json' => $payload
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!$data['success']) {
                // Extract error messages from Cloudflare API response
                $errors = $data['errors'] ?? [];
                $errorMessages = array_map(fn($err) => $err['message'] ?? 'Unknown error', $errors);
                $errorMessage = !empty($errorMessages) 
                    ? implode('; ', $errorMessages) 
                    : 'DNS provider rejected the record creation request.';
                
                Log::error('Cloudflare API rejected record creation', [
                    'domain' => $domain,
                    'name' => $name,
                    'type' => $type,
                    'payload' => $payload,
                    'errors' => $errors,
                ]);
                
                throw new \Exception($errorMessage);
            }

            return $data['result']['id'];
        } catch (GuzzleException $e) {
            // Extract actual error message from Guzzle response if available
            $errorMessage = 'DNS service temporarily unavailable.';
            
            if ($e->hasResponse()) {
                try {
                    $response = $e->getResponse();
                    $body = json_decode($response->getBody()->getContents(), true);
                    
                    if (isset($body['errors']) && is_array($body['errors'])) {
                        $errorMessages = array_map(fn($err) => $err['message'] ?? 'Unknown error', $body['errors']);
                        $errorMessage = implode('; ', $errorMessages);
                    } elseif (isset($body['error'])) {
                        $errorMessage = $body['error'];
                    }
                    
                    Log::error('Cloudflare API error during record creation', [
                        'domain' => $domain,
                        'name' => $name,
                        'type' => $type,
                        'status_code' => $response->getStatusCode(),
                        'error' => $errorMessage,
                        'response_body' => $body,
                    ]);
                } catch (\Exception $parseException) {
                    Log::error('Failed to parse Cloudflare API error response', [
                        'domain' => $domain,
                        'name' => $name,
                        'guzzle_message' => $e->getMessage(),
                        'parse_error' => $parseException->getMessage(),
                    ]);
                }
            } else {
                Log::error('Cloudflare API connection error', [
                    'domain' => $domain,
                    'name' => $name,
                    'error' => $e->getMessage(),
                ]);
            }
            
            throw DnsProviderException::recordCreationFailed($domain, $name, $errorMessage);
        }
    }

    /**
     * Update a DNS record.
     */
    public function updateRecord(string $domain, string $recordId, $content, ?int $ttl = null): bool
    {
        $zoneId = $this->getZoneId($domain);

        try {
            // Get existing record to determine type
            $existingRecord = $this->getRecord($domain, $recordId);
            $recordType = strtoupper($existingRecord['type'] ?? 'A');
            
            $payload = [];

            // Handle different content types
            if (is_array($content)) {
                // For SRV records, Cloudflare expects specific format in 'data' field
                if ($recordType === 'SRV') {
                    $payload['data'] = [
                        'service' => $content['service'] ?? '',
                        'proto' => $content['proto'] ?? '',
                        'name' => $content['target'] ?? $existingRecord['name'] ?? '',
                        'priority' => $content['priority'] ?? 0,
                        'weight' => $content['weight'] ?? 5,
                        'port' => $content['port'] ?? 0,
                        'target' => $content['target'] ?? $existingRecord['name'] ?? '',
                    ];
                } else {
                    // For other structured data types
                    $payload['data'] = $content;
                    if (isset($content['content'])) {
                        $payload['content'] = $content['content'];
                    }
                }
            } else {
                // For simple records like A, CNAME
                $payload['content'] = $content;
            }

            if ($ttl !== null) {
                $payload['ttl'] = $ttl;
            }

            $response = $this->client->patch("zones/{$zoneId}/dns_records/{$recordId}", [
                'json' => $payload
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!$data['success']) {
                // Extract error messages from Cloudflare API response
                $errors = $data['errors'] ?? [];
                $errorMessages = array_map(fn($err) => $err['message'] ?? 'Unknown error', $errors);
                $errorMessage = !empty($errorMessages) 
                    ? implode('; ', $errorMessages) 
                    : 'DNS provider rejected the record update request.';
                
                Log::error('Cloudflare API rejected record update', [
                    'domain' => $domain,
                    'record_id' => $recordId,
                    'payload' => $payload,
                    'errors' => $errors,
                ]);
                
                throw new \Exception($errorMessage);
            }

            return true;
        } catch (GuzzleException $e) {
            // Extract actual error message from Guzzle response if available
            $errorMessage = 'DNS service temporarily unavailable.';
            
            if ($e->hasResponse()) {
                try {
                    $response = $e->getResponse();
                    $body = json_decode($response->getBody()->getContents(), true);
                    
                    if (isset($body['errors']) && is_array($body['errors'])) {
                        $errorMessages = array_map(fn($err) => $err['message'] ?? 'Unknown error', $body['errors']);
                        $errorMessage = implode('; ', $errorMessages);
                    } elseif (isset($body['error'])) {
                        $errorMessage = $body['error'];
                    }
                    
                    Log::error('Cloudflare API error during record update', [
                        'domain' => $domain,
                        'record_id' => $recordId,
                        'status_code' => $response->getStatusCode(),
                        'error' => $errorMessage,
                        'response_body' => $body,
                    ]);
                } catch (\Exception $parseException) {
                    Log::error('Failed to parse Cloudflare API error response', [
                        'domain' => $domain,
                        'record_id' => $recordId,
                        'guzzle_message' => $e->getMessage(),
                        'parse_error' => $parseException->getMessage(),
                    ]);
                }
            } else {
                Log::error('Cloudflare API connection error during update', [
                    'domain' => $domain,
                    'record_id' => $recordId,
                    'error' => $e->getMessage(),
                ]);
            }
            
            throw DnsProviderException::recordUpdateFailed($domain, [$recordId], $errorMessage);
        }
    }

    /**
     * Delete a DNS record.
     */
    public function deleteRecord(string $domain, string $recordId): void
    {
        $zoneId = $this->getZoneId($domain);

        try {
            $response = $this->client->delete("zones/{$zoneId}/dns_records/{$recordId}");
            $data = json_decode($response->getBody()->getContents(), true);

            if (!$data['success']) {
                throw new \Exception('DNS provider rejected the record deletion request.');
            }
        } catch (GuzzleException $e) {
            throw DnsProviderException::recordDeletionFailed($domain, [$recordId], 'DNS service temporarily unavailable.');
        }
    }

    /**
     * Get a specific DNS record.
     */
    public function getRecord(string $domain, string $recordId): array
    {
        $zoneId = $this->getZoneId($domain);

        try {
            $response = $this->client->get("zones/{$zoneId}/dns_records/{$recordId}");
            $data = json_decode($response->getBody()->getContents(), true);

            if (!$data['success']) {
                throw new \Exception("DNS record not found or inaccessible.");
            }

            return $data['result'];
        } catch (GuzzleException $e) {
            throw DnsProviderException::connectionFailed('cloudflare', 'DNS service temporarily unavailable.');
        }
    }

    /**
     * List existing DNS records for a domain.
     */
    public function listRecords(string $domain, ?string $name = null, ?string $type = null): array
    {
        $zoneId = $this->getZoneId($domain);

        try {
            $params = [];
            if ($name) {
                $params['name'] = $name;
            }
            if ($type) {
                $params['type'] = strtoupper($type);
            }

            $response = $this->client->get("zones/{$zoneId}/dns_records", [
                'query' => $params
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!$data['success']) {
                throw new \Exception('Failed to retrieve DNS records.');
            }

            return $data['result'];
        } catch (GuzzleException $e) {
            throw DnsProviderException::connectionFailed('cloudflare', 'DNS service temporarily unavailable.');
        }
    }

    /**
     * Get the configuration schema for Cloudflare.
     */
    public function getConfigurationSchema(): array
    {
        return [
            'api_token' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Cloudflare API Token with Zone:Edit permissions',
                'sensitive' => true,
            ],
            'zone_id' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Cloudflare Zone ID',
                'sensitive' => false,
            ],
        ];
    }

    /**
     * Validate the provider configuration.
     */
    public function validateConfiguration(array $config): bool
    {
        if (empty($config['api_token'])) {
            throw DnsProviderException::invalidConfiguration('cloudflare', 'api_token');
        }

        return true;
    }

    /**
     * Get the supported record types for Cloudflare.
     */
    public function getSupportedRecordTypes(): array
    {
        return ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'NS', 'PTR', 'CAA'];
    }

    /**
     * Normalize DNS record name to be relative to the zone.
     * Cloudflare API prefers relative names (e.g., "subdomain" instead of "subdomain.example.com").
     */
    private function normalizeRecordName(string $name, string $domain): string
    {
        // Remove trailing dot if present
        $name = rtrim($name, '.');
        $domain = rtrim($domain, '.');

        // If name ends with the domain, remove it to make it relative
        $domainPattern = '.' . $domain;
        if (str_ends_with($name, $domainPattern)) {
            return substr($name, 0, -strlen($domainPattern));
        }

        // If name exactly matches the domain, return '@' (zone apex)
        if ($name === $domain) {
            return '@';
        }

        return $name;
    }

    /**
     * Get the zone ID for a domain.
     */
    private function getZoneId(string $domain): string
    {
        // Use provided zone_id if available
        if (!empty($this->config['zone_id'])) {
            return $this->config['zone_id'];
        }

        // Fall back to auto-discovery
        try {
            $response = $this->client->get('zones', [
                'query' => ['name' => $domain]
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (!$data['success'] || empty($data['result'])) {
                throw new \Exception("Domain zone not found or inaccessible.");
            }

            return $data['result'][0]['id'];
        } catch (GuzzleException $e) {
            throw DnsProviderException::connectionFailed('cloudflare', 'DNS service temporarily unavailable.');
        }
    }
}
