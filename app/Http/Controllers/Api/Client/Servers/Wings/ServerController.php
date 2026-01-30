<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\Wings;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subscription;
use Pterodactyl\Transformers\Api\Client\ServerTransformer;
use Pterodactyl\Services\Servers\GetUserPermissionsService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\GetServerRequest;
use Stripe\Stripe;
use Stripe\Exception\ApiErrorException;

class ServerController extends ClientApiController
{
    /**
     * ServerController constructor.
     */
    public function __construct(private GetUserPermissionsService $permissionsService)
    {
        parent::__construct();
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * Transform an individual server into a response that can be consumed by a
     * client using the API.
     */
    public function index(GetServerRequest $request, Server $server): array
    {
        // Ensure egg and nest relationships are loaded for dashboard_type accessor
        $server->loadMissing('egg.nest');

        return $this->fractal->item($server)
            ->transformWith($this->getTransformer(ServerTransformer::class))
            ->addMeta([
                'is_server_owner' => $request->user()->id === $server->owner_id,
                'user_permissions' => $this->permissionsService->handle($server, $request->user()),
            ])
            ->toArray();
    }

    /**
     * Get Stripe billing portal URL for the server's subscription.
     */
    public function billingPortal(GetServerRequest $request, Server $server): JsonResponse
    {
        $user = $request->user();

        // Ensure server belongs to user
        if ($server->owner_id !== $user->id) {
            return response()->json([
                'errors' => [[
                    'code' => 'Unauthorized',
                    'status' => '403',
                    'detail' => 'You do not have access to this server.',
                ]],
            ], 403);
        }

        // Check if server has a subscription
        if (!$server->subscription_id) {
            return response()->json([
                'errors' => [[
                    'code' => 'NoSubscription',
                    'status' => '404',
                    'detail' => 'This server does not have an associated subscription.',
                ]],
            ], 404);
        }

        $subscription = Subscription::where('id', $server->subscription_id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            // Get Stripe customer ID - prefer user's stripe_id, but get from subscription if needed
            $stripeCustomerId = $user->stripe_id;
            
            if (!$stripeCustomerId && $subscription->stripe_id && !$subscription->is_credits_based && !str_starts_with($subscription->stripe_id, 'credits_')) {
                // Try to get customer ID from the subscription (skip credits-based subscriptions)
                try {
                    \Stripe\Stripe::setApiKey(config('cashier.secret'));
                    $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_id);
                    $stripeCustomerId = $stripeSubscription->customer;
                    
                    // If we found a customer ID, save it to the user for future use
                    if ($stripeCustomerId && is_string($stripeCustomerId)) {
                        $user->update(['stripe_id' => $stripeCustomerId]);
                        Log::info('Updated user with Stripe customer ID from subscription', [
                            'user_id' => $user->id,
                            'stripe_customer_id' => $stripeCustomerId,
                            'subscription_id' => $subscription->id,
                            'server_id' => $server->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Could not retrieve customer ID from subscription', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscription->id,
                        'server_id' => $server->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            if (!$stripeCustomerId) {
                return response()->json([
                    'errors' => [[
                        'code' => 'NoStripeCustomer',
                        'status' => '400',
                        'detail' => 'No Stripe customer associated with your account. Please contact support.',
                    ]],
                ], 400);
            }

            // Create billing portal session
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $stripeCustomerId,
                'return_url' => config('app.url') . '/server/' . $server->uuid,
            ]);

            return response()->json([
                'url' => $session->url,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Failed to create billing portal session for server', [
                'user_id' => $user->id,
                'server_id' => $server->id,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'errors' => [[
                    'code' => 'StripeApiError',
                    'status' => '500',
                    'detail' => 'Failed to create billing portal session. Please try again or contact support.',
                ]],
            ], 500);
        }
    }
}
