<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\Vps;

class VpsTransformer extends BaseClientTransformer
{
    protected array $defaultIncludes = [];

    protected array $availableIncludes = ['subscription'];

    public function getResourceName(): string
    {
        return Vps::RESOURCE_NAME;
    }

    /**
     * Transform a VPS model into a representation that can be returned
     * to a client.
     */
    public function transform(Vps $vps): array
    {
        return [
            'identifier' => $vps->uuidShort,
            'internal_id' => $vps->id,
            'uuid' => $vps->uuid,
            'name' => $vps->name,
            'description' => $vps->description,
            'status' => $vps->status,
            'is_suspended' => $vps->isSuspended(),
            'is_running' => $vps->isRunning(),
            'is_stopped' => $vps->isStopped(),
            'is_installed' => $vps->isInstalled(),
            'limits' => [
                'memory' => $vps->memory,
                'disk' => $vps->disk,
                'cpu_cores' => $vps->cpu_cores,
                'cpu_sockets' => $vps->cpu_sockets,
            ],
            'proxmox' => [
                'vm_id' => $vps->proxmox_vm_id,
                'node' => $vps->proxmox_node,
                'storage' => $vps->proxmox_storage,
            ],
            'network' => [
                'ip_address' => $vps->ip_address,
                'ipv6_address' => $vps->ipv6_address,
            ],
            'distribution' => $vps->distribution,
            'installed_at' => $vps->installed_at?->toIso8601String(),
            'created_at' => $vps->created_at->toIso8601String(),
            'updated_at' => $vps->updated_at->toIso8601String(),
        ];
    }

    /**
     * Returns the subscription associated with this VPS.
     */
    public function includeSubscription(Vps $vps): \League\Fractal\Resource\Item|\League\Fractal\Resource\NullResource
    {
        if (!$vps->subscription) {
            return $this->null();
        }

        return $this->item(
            $vps->subscription,
            $this->makeTransformer(SubscriptionTransformer::class),
            'subscription'
        );
    }
}

