<?php

namespace Pterodactyl\Services\Hosting;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Plan;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\Subscription;
use Pterodactyl\Models\Objects\DeploymentObject;
use Pterodactyl\Services\Servers\ServerCreationService;

class ServerProvisioningService
{
    public function __construct(
        private ServerCreationService $serverCreationService
    ) {}

    /**
     * Provision a server after successful payment.
     *
     * @throws \Exception
     */
    public function provisionServer($stripeSession): \Pterodactyl\Models\Server
    {
        // Convert Stripe metadata object to array if needed
        $rawMetadata = $stripeSession->metadata ?? [];
        $metadata = $this->convertMetadataToArray($rawMetadata);
        
        // Extract values (Stripe metadata values are always strings)
        $userId = isset($metadata['user_id']) ? (int) $metadata['user_id'] : null;
        $nestId = isset($metadata['nest_id']) ? (int) $metadata['nest_id'] : null;
        $eggId = isset($metadata['egg_id']) ? (int) $metadata['egg_id'] : null;
        $serverName = $metadata['server_name'] ?? null;
        $serverDescription = $metadata['server_description'] ?? '';

        if (!$userId || !$nestId || !$eggId || !$serverName) {
            throw new \Exception('Missing required metadata in checkout session: ' . json_encode([
                'has_user_id' => !empty($userId),
                'has_nest_id' => !empty($nestId),
                'has_egg_id' => !empty($eggId),
                'has_server_name' => !empty($serverName),
                'metadata_keys' => array_keys($metadata),
            ]));
        }

        $user = User::findOrFail($userId);
        $egg = Egg::findOrFail($eggId);

        // Verify nest matches egg
        if ($egg->nest_id != $nestId) {
            throw new \Exception('Egg does not belong to the specified nest');
        }

        // Get or create subscription
        $subscription = $this->getOrCreateSubscription($stripeSession, $user);

        // Determine server resources from plan or custom plan
        $resources = $this->getServerResources($metadata);

        // Get default docker image and startup command from egg
        $dockerImage = $this->getDefaultDockerImage($egg);
        $startup = $egg->startup ?? '';

        if (empty($dockerImage)) {
            throw new \Exception('No default docker image available for this egg');
        }

        // Get default values for egg variables
        $environment = $this->getDefaultEggVariableValues($egg);

        // Build server creation data
        $serverData = [
            'owner_id' => $user->id,
            'name' => $serverName,
            'description' => $serverDescription ?: '',
            'nest_id' => $nestId,
            'egg_id' => $eggId,
            'memory' => $resources['memory'],
            'swap' => $resources['swap'] ?? 0,
            'disk' => $resources['disk'],
            'io' => $resources['io'] ?? 500,
            'cpu' => $resources['cpu'] ?? 0,
            'image' => $dockerImage,
            'startup' => $startup,
            'environment' => $environment,
            'start_on_completion' => false,
            'skip_scripts' => false,
            'database_limit' => 0,
            'allocation_limit' => 1,
            'backup_limit' => 0,
        ];

        // Create deployment object for automatic allocation
        $deployment = new DeploymentObject();
        $deployment->setDedicated(false);
        $deployment->setLocations([]); // Auto-select from all locations
        $deployment->setPorts([]); // Auto-select port

        // Create the server (ServerCreationService handles its own transaction and Wings call)
        $server = $this->serverCreationService->handle($serverData, $deployment);
        
        // Link server to subscription after creation
        $server->update([
            'subscription_id' => $subscription->id,
        ]);
        
        // Refresh server to ensure all relationships are loaded
        $server->refresh();

        Log::info('Server provisioned from Stripe checkout', [
            'server_id' => $server->id,
            'server_uuid' => $server->uuid,
            'server_uuid_short' => $server->uuidShort,
            'node_id' => $server->node_id,
            'allocation_id' => $server->allocation_id,
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'session_id' => $stripeSession->id,
        ]);
        
        // Verify server is accessible via repository (as Wings would query it)
        try {
            $serverRepository = app(\Pterodactyl\Repositories\Eloquent\ServerRepository::class);
            $verifiedServer = $serverRepository->getByUuid($server->uuid);
            Log::info('Server verified as accessible via repository', [
                'server_id' => $verifiedServer->id,
                'uuid' => $verifiedServer->uuid,
            ]);
        } catch (\Exception $e) {
            Log::error('Server not accessible via repository after creation', [
                'server_id' => $server->id,
                'uuid' => $server->uuid,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Server was created but is not accessible via repository: ' . $e->getMessage());
        }

        return $server;
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
     * Get server resources from plan or custom plan metadata.
     */
    private function getServerResources(array $metadata): array
    {
        // Check if this is a predefined plan
        if (!empty($metadata['plan_id'])) {
            $planId = is_numeric($metadata['plan_id']) ? (int) $metadata['plan_id'] : null;
            
            if ($planId) {
                $plan = Plan::findOrFail($planId);
                
                return [
                    'memory' => $plan->memory ?? 1024,
                    'disk' => $plan->disk ?? 10240,
                    'cpu' => $plan->cpu ?? 0,
                    'io' => $plan->io ?? 500,
                    'swap' => $plan->swap ?? 0,
                ];
            }
        }

        // Custom plan - calculate resources
        $memory = isset($metadata['memory']) ? (int) $metadata['memory'] : 1024;
        
        // Calculate disk based on memory (default: 10x memory)
        $disk = (int) ($memory * 10);
        
        return [
            'memory' => $memory,
            'disk' => $disk,
            'cpu' => 0, // Unlimited CPU for custom plans
            'io' => 500, // Default IO
            'swap' => 0, // No swap by default
        ];
    }

    /**
     * Get the default docker image from an egg.
     */
    private function getDefaultDockerImage(Egg $egg): ?string
    {
        $dockerImages = $egg->docker_images ?? [];
        
        if (empty($dockerImages)) {
            return null;
        }

        // Return the first docker image (usually the default)
        return reset($dockerImages);
    }

    /**
     * Get default values for egg variables.
     * This ensures all variables have values, using their default_value if available.
     */
    private function getDefaultEggVariableValues(Egg $egg): array
    {
        $environment = [];
        
        // Get all variables for this egg (at admin level, so we get all variables)
        $variables = $egg->variables()->get();
        
        foreach ($variables as $variable) {
            // Use default_value if available, otherwise use empty string
            // The VariableValidatorService will validate these values
            $defaultValue = $variable->default_value ?? '';
            $environment[$variable->env_variable] = $defaultValue;
        }
        
        return $environment;
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
