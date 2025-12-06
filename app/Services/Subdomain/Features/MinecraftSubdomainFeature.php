<?php

namespace Pterodactyl\Services\Subdomain\Features;

use Pterodactyl\Models\Server;
use Pterodactyl\Contracts\Subdomain\SubdomainFeatureInterface;

class MinecraftSubdomainFeature implements SubdomainFeatureInterface
{
    /**
     * Get the feature name.
     */
    public function getFeatureName(): string
    {
        return 'subdomain_minecraft';
    }

    /**
     * Get the DNS records that need to be created for Minecraft.
     */
    public function getDnsRecords(Server $server, string $subdomain, string $domain, ?int $proxyPort = null): array
    {
        $ip = $server->allocation->ip;
        $containerPort = $server->allocation->port;
        $subdomain_split = explode(".", $subdomain);
        $fullDomain = $subdomain_split[0] . '.' . $domain;

        $records = [];

        // A record pointing to the server IP
        $records[] = [
            'name' => $subdomain,
            'type' => 'A',
            'content' => $ip,
            'ttl' => 300,
        ];

        // Determine which port to use for SRV record
        // If using proxy (HAProxy/NGINX), use proxy port; otherwise use container port
        $srvPort = $proxyPort ?? $containerPort;
        
        // SRV record for Minecraft
        // Always create SRV record if:
        // 1. Using proxy (proxy_port is set) - clients need to know the proxy port
        // 2. Container port is not the default 25565 - clients need to know the actual port
        if ($proxyPort !== null || $containerPort != 25565) {
            $records[] = [
                'name' => '_minecraft._tcp.' . $subdomain,
                'type' => 'SRV',
                'content' => [
                    'service' => '_minecraft',
                    'proto' => '_tcp',
                    'priority' => 0,
                    'weight' => 5,
                    'port' => $srvPort,
                    'target' => $fullDomain,
                ],
                'ttl' => 300,
            ];
        }

        return $records;
    }
}

