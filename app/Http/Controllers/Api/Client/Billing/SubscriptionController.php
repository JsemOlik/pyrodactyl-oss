<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Billing;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Models\Subscription;
use Pterodactyl\Transformers\Api\Client\SubscriptionTransformer;
use Stripe\Stripe;
use Stripe\Exception\ApiErrorException;

class SubscriptionController extends ClientApiController
{
    public function __construct()
    {
        parent::__construct();
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * Get all subscriptions for the authenticated user.
     */
    public function index(Request $request): array
    {
        $user = $request->user();
        
        $subscriptions = Subscription::where('user_id', $user->id)
            ->with(['plan', 'servers'])
            ->orderBy('created_at', 'desc')
            ->get();

        $transformer = $this->getTransformer(SubscriptionTransformer::class);

        return $this->fractal->collection($subscriptions)
            ->transformWith($transformer)
            ->toArray();
    }

    /**
     * Get a single subscription by ID.
     */
    public function view(Request $request, int $subscription): array
    {
        $user = $request->user();
        
        $subscriptionModel = Subscription::where('id', $subscription)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $subscriptionModel->loadMissing(['plan', 'servers']);
        
        $transformer = $this->getTransformer(SubscriptionTransformer::class);

        return $this->fractal->item($subscriptionModel)
            ->transformWith($transformer)
            ->toArray();
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(Request $request, int $subscription): JsonResponse
    {
        $user = $request->user();
        
        $subscriptionModel = Subscription::where('id', $subscription)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Validate that subscription can be canceled
        if (!in_array($subscriptionModel->stripe_status, ['active', 'trialing'])) {
            return response()->json([
                'errors' => [[
                    'code' => 'InvalidStatus',
                    'status' => '400',
                    'detail' => 'This subscription cannot be canceled in its current state.',
                ]],
            ], 400);
        }

        $immediate = $request->input('immediate', false);

        try {
            $stripeSubscription = \Stripe\Subscription::retrieve($subscriptionModel->stripe_id);
            
            if ($immediate) {
                // Cancel immediately
                $stripeSubscription->cancel();
                
                // Delete the associated server(s)
                $servers = $subscriptionModel->servers;
                foreach ($servers as $server) {
                    try {
                        $deletionService = app(\Pterodactyl\Services\Servers\ServerDeletionService::class);
                        $deletionService->handle($server);
                        Log::info('Server deleted due to immediate subscription cancellation', [
                            'server_id' => $server->id,
                            'subscription_id' => $subscriptionModel->id,
                            'user_id' => $user->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to delete server during immediate cancellation', [
                            'server_id' => $server->id,
                            'subscription_id' => $subscriptionModel->id,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue with cancellation even if server deletion fails
                    }
                }
                
                // Update local subscription
                $subscriptionModel->refresh();

                Log::info('Subscription canceled immediately', [
                    'subscription_id' => $subscriptionModel->id,
                    'stripe_id' => $subscriptionModel->stripe_id,
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'message' => 'Subscription has been canceled immediately and the server has been deleted.',
                ]);
            } else {
                // Cancel at period end (don't immediately cancel)
                $stripeSubscription->cancel_at_period_end = true;
                $stripeSubscription->save();

                // Update local subscription
                $subscriptionModel->refresh();

                Log::info('Subscription canceled at period end', [
                    'subscription_id' => $subscriptionModel->id,
                    'stripe_id' => $subscriptionModel->stripe_id,
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'message' => 'Subscription will be canceled at the end of the billing period.',
                    'ends_at' => $subscriptionModel->ends_at ? $subscriptionModel->ends_at->toIso8601String() : null,
                ]);
            }
        } catch (ApiErrorException $e) {
            Log::error('Failed to cancel subscription', [
                'subscription_id' => $subscriptionModel->id,
                'immediate' => $immediate,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'errors' => [[
                    'code' => 'StripeApiError',
                    'status' => '500',
                    'detail' => 'Failed to cancel subscription. Please try again or contact support.',
                ]],
            ], 500);
        }
    }

    /**
     * Resume a canceled subscription.
     */
    public function resume(Request $request, int $subscription): JsonResponse
    {
        $user = $request->user();
        
        $subscriptionModel = Subscription::where('id', $subscription)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Validate that subscription can be resumed
        if ($subscriptionModel->stripe_status !== 'canceled' || !$subscriptionModel->ends_at || $subscriptionModel->ends_at->isPast()) {
            return response()->json([
                'errors' => [[
                    'code' => 'InvalidStatus',
                    'status' => '400',
                    'detail' => 'This subscription cannot be resumed.',
                ]],
            ], 400);
        }

        try {
            // Resume the subscription
            $stripeSubscription = \Stripe\Subscription::retrieve($subscriptionModel->stripe_id);
            $stripeSubscription->cancel_at_period_end = false;
            $stripeSubscription->save();

            // Update local subscription
            $subscriptionModel->refresh();

            Log::info('Subscription resumed', [
                'subscription_id' => $subscriptionModel->id,
                'stripe_id' => $subscriptionModel->stripe_id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Subscription has been resumed.',
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Failed to resume subscription', [
                'subscription_id' => $subscriptionModel->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'errors' => [[
                    'code' => 'StripeApiError',
                    'status' => '500',
                    'detail' => 'Failed to resume subscription. Please try again or contact support.',
                ]],
            ], 500);
        }
    }

    /**
     * Get Stripe billing portal URL for managing subscription.
     */
    public function billingPortal(Request $request, int $subscription): JsonResponse
    {
        $user = $request->user();
        
        $subscriptionModel = Subscription::where('id', $subscription)
            ->where('user_id', $user->id)
            ->firstOrFail();

        try {
            // Get Stripe customer ID - prefer user's stripe_id, but get from subscription if needed
            $stripeCustomerId = $user->stripe_id;
            
            if (!$stripeCustomerId) {
                // Try to get customer ID from the subscription
                try {
                    $stripeSubscription = \Stripe\Subscription::retrieve($subscriptionModel->stripe_id);
                    $stripeCustomerId = $stripeSubscription->customer;
                    
                    // If we found a customer ID, save it to the user for future use
                    if ($stripeCustomerId && is_string($stripeCustomerId)) {
                        $user->update(['stripe_id' => $stripeCustomerId]);
                        Log::info('Updated user with Stripe customer ID from subscription', [
                            'user_id' => $user->id,
                            'stripe_customer_id' => $stripeCustomerId,
                            'subscription_id' => $subscriptionModel->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Could not retrieve customer ID from subscription', [
                        'user_id' => $user->id,
                        'subscription_id' => $subscriptionModel->id,
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
                'return_url' => config('app.url') . '/billing',
            ]);

            return response()->json([
                'url' => $session->url,
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Failed to create billing portal session', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionModel->id,
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

