<?php

namespace Pterodactyl\Services\Hosting;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Plan;
use Pterodactyl\Models\Subscription;
use Pterodactyl\Services\Vps\VpsCreationService;

class VpsProvisioningService
{
    public function __construct(
        private VpsCreationService $vpsCreationService
    ) {}

    /**
     * Provision a VPS after successful payment.
     *
     * @throws \Exception
     */
    public function provisionVps($stripeSession): \Pterodactyl\Models\Vps
    {
        // Convert Stripe metadata object to array if needed
        $rawMetadata = $stripeSession->metadata ?? [];
        $metadata = $this->convertMetadataToArray($rawMetadata);
        
        // Extract values (Stripe metadata values are always strings)
        $userId = isset($metadata['user_id']) ? (int) $metadata['user_id'] : null;
        $serverName = $metadata['server_name'] ?? null;
        $serverDescription = $metadata['server_description'] ?? '';
        $distribution = $metadata['distribution'] ?? 'ubuntu-server';

        if (!$userId || !$serverName) {
            throw new \Exception('Missing required metadata in checkout session: ' . json_encode([
                'has_user_id' => !empty($userId),
                'has_server_name' => !empty($serverName),
                'metadata_keys' => array_keys($metadata),
            ]));
        }

        $user = User::findOrFail($userId);

        // Get or create subscription
        $subscription = $this->getOrCreateSubscription($stripeSession, $user);

        // Determine VPS resources from plan or custom plan
        $resources = $this->getVpsResources($metadata);

        // Build VPS creation data
        $vpsData = [
            'owner_id' => $user->id,
            'name' => $serverName,
            'description' => $serverDescription ?: '',
            'memory' => $resources['memory'],
            'disk' => $resources['disk'],
            'cpu_cores' => $resources['cpu_cores'],
            'cpu_sockets' => $resources['cpu_sockets'] ?? 1,
            'distribution' => $distribution,
            'subscription_id' => $subscription->id,
        ];

        // Create the VPS (VpsCreationService handles its own transaction and Proxmox call)
        $vps = $this->vpsCreationService->handle($vpsData);
        
        Log::info('VPS provisioned from Stripe checkout', [
            'vps_id' => $vps->id,
            'vps_uuid' => $vps->uuid,
            'vps_uuid_short' => $vps->uuidShort,
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'session_id' => $stripeSession->id,
        ]);

        return $vps;
    }

    /**
     * Get or create a subscription for the Stripe checkout session.
     */
    private function getOrCreateSubscription($stripeSession, User $user): Subscription
    {
        $subscriptionId = $stripeSession->subscription;
        
        if (!$subscriptionId) {
            throw new \Exception('Checkout session does not have a subscription ID');
        }

        // Check if subscription already exists
        $subscription = Subscription::where('stripe_id', $subscriptionId)->first();

        if ($subscription) {
            return $subscription;
        }

        // Get Stripe subscription details
        \Stripe\Stripe::setApiKey(config('cashier.secret'));
        $stripeSubscription = \Stripe\Subscription::retrieve($subscriptionId);
        
        // Ensure user has Stripe customer ID set
        if (!$user->stripe_id && $stripeSubscription->customer) {
            $user->update(['stripe_id' => $stripeSubscription->customer]);
            Log::info('Updated user with Stripe customer ID from subscription', [
                'user_id' => $user->id,
                'stripe_customer_id' => $stripeSubscription->customer,
                'subscription_id' => $subscriptionId,
            ]);
        }
        
        // Determine plan from metadata or Stripe price
        $metadata = $stripeSession->metadata ?? [];
        $plan = null;
        
        if (!empty($metadata['plan_id'])) {
            $plan = Plan::find($metadata['plan_id']);
        } elseif (!empty($stripeSubscription->items->data[0]->price->id)) {
            // Try to find plan by Stripe price ID
            $plan = Plan::where('stripe_price_id', $stripeSubscription->items->data[0]->price->id)->first();
        }

        // Create subscription record
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => $subscriptionId,
            'stripe_status' => $stripeSubscription->status,
            'stripe_price' => $stripeSubscription->items->data[0]->price->id ?? null,
            'quantity' => $stripeSubscription->items->data[0]->quantity ?? 1,
            'trial_ends_at' => $stripeSubscription->trial_end ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->trial_end) : null,
            'ends_at' => $stripeSubscription->cancel_at ? \Carbon\Carbon::createFromTimestamp($stripeSubscription->cancel_at) : null,
        ]);

        return $subscription;
    }

    /**
     * Get VPS resources from plan or custom plan metadata.
     */
    private function getVpsResources(array $metadata): array
    {
        // Check if this is a predefined plan
        if (!empty($metadata['plan_id'])) {
            $planId = is_numeric($metadata['plan_id']) ? (int) $metadata['plan_id'] : null;
            
            if ($planId) {
                $plan = Plan::findOrFail($planId);
                
                return [
                    'memory' => $plan->memory ?? 1024,
                    'disk' => $plan->disk ?? 10240,
                    'cpu_cores' => $plan->cpu ?? 1, // For VPS, CPU is cores, not percentage
                    'cpu_sockets' => 1, // Default to 1 socket
                ];
            }
        }

        // Custom plan - calculate resources
        $memory = isset($metadata['memory']) ? (int) $metadata['memory'] : 1024;
        
        // Calculate disk based on memory (default: 10x memory)
        $disk = (int) ($memory * 10);
        
        // For custom VPS plans, default to 1 CPU core
        $cpuCores = isset($metadata['cpu_cores']) ? (int) $metadata['cpu_cores'] : 1;
        
        return [
            'memory' => $memory,
            'disk' => $disk,
            'cpu_cores' => $cpuCores,
            'cpu_sockets' => 1,
        ];
    }

    /**
     * Convert Stripe metadata to array format.
     */
    private function convertMetadataToArray($metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_object($metadata)) {
            // Handle Stripe\StripeObject
            if (method_exists($metadata, 'toArray')) {
                return $metadata->toArray();
            }
            
            // Fallback: convert object to array
            return json_decode(json_encode($metadata), true) ?? [];
        }

        return [];
    }
}

