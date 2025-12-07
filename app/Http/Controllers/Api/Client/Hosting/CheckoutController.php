<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Hosting;

use Stripe\Stripe;
use Stripe\Price;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Plan;
use Pterodactyl\Models\User;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Api\Client\Hosting\CheckoutRequest;
use Pterodactyl\Services\Hosting\StripePriceService;
use Pterodactyl\Services\Hosting\ServerProvisioningService;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(
        private StripePriceService $stripePriceService,
        private ServerProvisioningService $serverProvisioningService
    ) {
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * Create a Stripe checkout session for server purchase.
     *
     * @throws \Exception
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Check if server creation is enabled
        $serverCreationEnabled = config('billing.enable_server_creation', true);
        if (!$serverCreationEnabled) {
            return response()->json([
                'errors' => [[
                    'code' => 'ServerCreationDisabled',
                    'status' => '403',
                    'detail' => 'Server creation is currently disabled. Please check back later.',
                ]],
            ], 403);
        }

        // Check if credits are enabled
        $creditsEnabled = config('billing.enable_credits', false);

        try {
            // Determine plan and pricing
            $plan = null;
            $interval = 'month';
            $priceAmount = 0;

            if ($request->has('plan_id')) {
                $plan = Plan::findOrFail($request->input('plan_id'));
                $interval = $plan->interval;
                $priceAmount = $plan->price;
            } else {
                // Custom plan - calculate price
                $memory = $request->input('memory');
                $interval = $request->input('interval', 'month');
                
                // Calculate price (same logic as HostingPlanController)
                $pricePerMonth = ($memory / 1024) * 10; // $10 per GB per month
                
                $discountMonths = match ($interval) {
                    'month' => 0,
                    'quarter' => 1,
                    'half-year' => 2,
                    'year' => 3,
                    default => 0,
                };
                
                $intervalMonths = match ($interval) {
                    'month' => 1,
                    'quarter' => 3,
                    'half-year' => 6,
                    'year' => 12,
                    default => 1,
                };
                
                $priceAmount = round($pricePerMonth * ($intervalMonths - $discountMonths), 2);
            }

            // Determine type from request or plan
            $type = $request->input('type', $plan?->type ?? 'game-server');

            // If credits are enabled, process with credits instead of Stripe
            if ($creditsEnabled) {
                // Refresh user to get latest credits balance
                $user->refresh();
                
                // Check if user has enough credits
                if ($user->credits_balance < $priceAmount) {
                    return response()->json([
                        'errors' => [[
                            'code' => 'InsufficientCredits',
                            'status' => '402',
                            'detail' => 'You do not have enough credits to purchase this server. Please purchase more credits first.',
                        ]],
                    ], 402);
                }

                // Deduct credits and provision server
                return DB::transaction(function () use ($user, $priceAmount, $request, $plan, $interval, $type) {
                    // Deduct credits
                    $user->decrement('credits_balance', $priceAmount);
                    
                    // Build metadata for provisioning
                    $metadata = [
                        'user_id' => (string) $user->id,
                        'type' => $type,
                        'server_name' => $request->input('server_name'),
                        'server_description' => $request->input('server_description', ''),
                    ];

                    if ($type === 'vps') {
                        $metadata['distribution'] = $request->input('distribution', 'ubuntu-server');
                    } else {
                        $metadata['nest_id'] = (string) $request->input('nest_id');
                        $metadata['egg_id'] = (string) $request->input('egg_id');
                    }

                    if ($plan) {
                        $metadata['plan_id'] = (string) $plan->id;
                    } else {
                        $metadata['custom'] = 'true';
                        $metadata['memory'] = (string) $request->input('memory');
                        $metadata['interval'] = $interval;
                    }

                    // Create a mock Stripe session object for provisioning
                    $mockSession = (object) [
                        'id' => 'credits_' . uniqid(),
                        'subscription' => null,
                        'metadata' => $metadata,
                    ];

                    try {
                        // Provision server (will need to handle subscription creation separately for credits)
                        $server = $this->serverProvisioningService->provisionServer($mockSession);
                        
                        Log::info('Server purchased with credits', [
                            'user_id' => $user->id,
                            'server_id' => $server->id,
                            'credits_deducted' => $priceAmount,
                            'remaining_credits' => $user->fresh()->credits_balance,
                        ]);

                        return response()->json([
                            'object' => 'checkout_session',
                            'data' => [
                                'checkout_url' => config('app.url') . '/server/' . $server->uuid,
                                'session_id' => $mockSession->id,
                                'server_uuid' => $server->uuid,
                            ],
                        ]);
                    } catch (\Exception $e) {
                        // Refund credits on error
                        $user->increment('credits_balance', $priceAmount);
                        Log::error('Failed to provision server after credit deduction, credits refunded', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                            'credits_refunded' => $priceAmount,
                        ]);
                        throw $e;
                    }
                });
            }

            // Credits disabled - use Stripe checkout flow
            // Get or create Stripe customer
            $stripeCustomer = $this->getOrCreateStripeCustomer($user);

            $stripePriceId = null;
            if ($plan) {
                // Get or create Stripe Price for this plan
                $stripePriceId = $this->stripePriceService->getOrCreatePriceForPlan($plan);
            } else {
                // Create Stripe Price for custom plan
                $stripePriceId = $this->stripePriceService->createPriceForCustomPlan(
                    $priceAmount,
                    $interval,
                    $request->input('memory')
                );
            }
            
            // Build metadata for webhook handler
            $metadata = [
                'user_id' => (string) $user->id,
                'type' => $type,
                'server_name' => $request->input('server_name'),
                'server_description' => $request->input('server_description', ''),
            ];

            // Add type-specific metadata
            if ($type === 'vps') {
                $metadata['distribution'] = $request->input('distribution', 'ubuntu-server');
            } else {
                $metadata['nest_id'] = (string) $request->input('nest_id');
                $metadata['egg_id'] = (string) $request->input('egg_id');
            }

            if ($plan) {
                $metadata['plan_id'] = (string) $plan->id;
            } else {
                $metadata['custom'] = 'true';
                $metadata['memory'] = (string) $request->input('memory');
                $metadata['interval'] = $interval;
            }

            // Get success and cancel URLs
            $cancelUrl = config('app.url') . '/hosting/checkout?' . http_build_query($request->only(['plan_id', 'custom', 'memory', 'interval', 'nest_id', 'egg_id']));

            // Create Stripe Checkout Session
            $checkoutSession = Session::create([
                'customer' => $stripeCustomer->id,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $stripePriceId,
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => config('app.url') . '/hosting/verifying?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'metadata' => $metadata,
                'subscription_data' => [
                    'metadata' => $metadata,
                ],
                'allow_promotion_codes' => true,
            ]);

            Log::info('Checkout session created', [
                'session_id' => $checkoutSession->id,
                'user_id' => $user->id,
                'plan_id' => $plan?->id,
                'is_custom' => !$plan,
            ]);

            return response()->json([
                'object' => 'checkout_session',
                'data' => [
                    'checkout_url' => $checkoutSession->url,
                    'session_id' => $checkoutSession->id,
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error during checkout session creation', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'errors' => [[
                    'code' => 'StripeApiError',
                    'status' => '500',
                    'detail' => 'An error occurred while creating the checkout session. Please try again later.',
                ]],
            ], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error during checkout session creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'errors' => [[
                    'code' => 'InternalServerError',
                    'status' => '500',
                    'detail' => 'An unexpected error occurred. Please try again later.',
                ]],
            ], 500);
        }
    }

    /**
     * Get or create a Stripe customer for the user.
     */
    private function getOrCreateStripeCustomer(User $user): \Stripe\Customer
    {
        // Check if user already has a Stripe customer ID
        if ($user->stripe_id) {
            try {
                return \Stripe\Customer::retrieve($user->stripe_id);
            } catch (ApiErrorException $e) {
                // Customer doesn't exist in Stripe, create a new one
                Log::warning('Stripe customer ID exists but customer not found in Stripe', [
                    'user_id' => $user->id,
                    'stripe_id' => $user->stripe_id,
                ]);
            }
        }

        // Create new Stripe customer
        $customer = \Stripe\Customer::create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => [
                'user_id' => $user->id,
                'username' => $user->username,
            ],
        ]);

        // Update user with Stripe customer ID
        $user->update([
            'stripe_id' => $customer->id,
        ]);

        return $customer;
    }
}
