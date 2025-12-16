<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Hosting;

use Stripe\Stripe;
use Stripe\Price;
use Stripe\Coupon;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Plan;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Subscription;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Http\Requests\Api\Client\Hosting\CheckoutRequest;
use Pterodactyl\Services\Hosting\StripePriceService;
use Pterodactyl\Services\Hosting\ServerProvisioningService;
use Pterodactyl\Services\Credits\CreditTransactionService;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(
        private StripePriceService $stripePriceService,
        private ServerProvisioningService $serverProvisioningService,
        private CreditTransactionService $creditTransactionService,
        private SettingsRepositoryInterface $settings
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
            $interval = $request->input('interval', 'month'); // Use selected interval from user
            $priceAmount = 0;

            // Determine type from request or plan
            $type = $request->input('type', $plan?->type ?? 'game-server');

            // Get category-specific billing discounts
            $billingDiscounts = json_decode($this->settings->get('settings::billing:period_discounts', json_encode([])), true);
            if (!is_array($billingDiscounts)) {
                $billingDiscounts = [];
            }
            $categoryDiscounts = $billingDiscounts[$type] ?? [
                'month' => 0,
                'quarter' => 5,
                'half-year' => 10,
                'year' => 20,
            ];

            if ($request->has('plan_id')) {
                $plan = Plan::findOrFail($request->input('plan_id'));
                
                // Get base monthly price from plan
                $baseMonthlyPrice = $plan->getPriceForInterval('month');
                
                // Calculate price for selected interval with category discount
                $intervalMonths = match ($interval) {
                    'month' => 1,
                    'quarter' => 3,
                    'half-year' => 6,
                    'year' => 12,
                    default => 1,
                };
                
                // Calculate base price for interval
                $basePrice = $baseMonthlyPrice * $intervalMonths;
                
                // Apply category-specific discount
                $discountPercent = match ($interval) {
                    'month' => $categoryDiscounts['month'] ?? 0,
                    'quarter' => $categoryDiscounts['quarter'] ?? 0,
                    'half-year' => $categoryDiscounts['half-year'] ?? 0,
                    'year' => $categoryDiscounts['year'] ?? 0,
                    default => 0,
                };
                
                $priceAmount = round($basePrice * (1 - ($discountPercent / 100)), 2);
                
                // Apply first month discount if available (only for first payment)
                if ($plan->first_month_sales_percentage && $plan->first_month_sales_percentage > 0) {
                    $firstMonthDiscount = $plan->first_month_sales_percentage / 100;
                    $priceAmount = round($priceAmount * (1 - $firstMonthDiscount), 2);
                }
            } else {
                // Custom plan - calculate price
                $memory = $request->input('memory');
                
                // Calculate price (same logic as HostingPlanController)
                $pricePerMonth = ($memory / 1024) * 10; // $10 per GB per month
                
                $intervalMonths = match ($interval) {
                    'month' => 1,
                    'quarter' => 3,
                    'half-year' => 6,
                    'year' => 12,
                    default => 1,
                };
                
                // Calculate base price for interval
                $basePrice = $pricePerMonth * $intervalMonths;
                
                // Apply category-specific discount
                $discountPercent = match ($interval) {
                    'month' => $categoryDiscounts['month'] ?? 0,
                    'quarter' => $categoryDiscounts['quarter'] ?? 0,
                    'half-year' => $categoryDiscounts['half-year'] ?? 0,
                    'year' => $categoryDiscounts['year'] ?? 0,
                    default => 0,
                };
                
                $priceAmount = round($basePrice * (1 - ($discountPercent / 100)), 2);
            }

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

                // Build metadata for provisioning
                $metadata = [
                    'user_id' => (string) $user->id,
                    'type' => $type,
                    'server_name' => $request->input('server_name'),
                    'server_description' => $request->input('server_description', ''),
                ];
                
                // Add subdomain info if provided
                if ($request->has('subdomain') && $request->has('domain_id')) {
                    $metadata['subdomain'] = $request->input('subdomain');
                    $metadata['domain_id'] = (string) $request->input('domain_id');
                }

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

                // Calculate next billing date based on selected interval
                $nextBillingAt = match($interval) {
                    'month' => now()->addMonth(),
                    'quarter' => now()->addMonths(3),
                    'half-year' => now()->addMonths(6),
                    'year' => now()->addYear(),
                    default => now()->addMonth(),
                };

                // Create subscription first (before any operations that might fail)
                $subscription = null;
                try {
                    // Prepare subscription metadata
                    $subscriptionMetadata = [];
                    if ($request->has('subdomain') && $request->has('domain_id')) {
                        $subscriptionMetadata['subdomain'] = $request->input('subdomain');
                        $subscriptionMetadata['domain_id'] = (int) $request->input('domain_id');
                    }
                    
                    $subscription = \Pterodactyl\Models\Subscription::create([
                        'user_id' => $user->id,
                        'type' => 'default',
                        'stripe_id' => 'credits_' . uniqid(),
                        'stripe_status' => 'active',
                        'stripe_price' => $plan?->stripe_price_id,
                        'quantity' => 1,
                        'trial_ends_at' => null,
                        'ends_at' => null,
                        'next_billing_at' => $nextBillingAt,
                        'billing_interval' => $interval, // Store selected interval
                        'billing_amount' => $priceAmount, // Store discounted price for recurring billing
                        'is_credits_based' => true,
                        'metadata' => !empty($subscriptionMetadata) ? $subscriptionMetadata : null,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to create subscription for credits purchase', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                    
                    return response()->json([
                        'errors' => [[
                            'code' => 'SubscriptionCreationFailed',
                            'status' => '500',
                            'detail' => 'Failed to create subscription. Please try again later.',
                        ]],
                    ], 500);
                }

                // Deduct credits and provision server
                // Note: We don't wrap this in a transaction because ServerCreationService
                // handles its own transaction. We'll handle rollback manually if needed.
                try {
                    // Deduct credits using transaction service
                    $this->creditTransactionService->recordDeduction(
                        $user,
                        $priceAmount,
                        "Server purchase - {$request->input('server_name')}",
                        $subscription->id,
                        [
                            'plan_id' => $plan?->id,
                            'server_type' => $type,
                            'interval' => $interval,
                        ]
                    );
                    
                    // Refresh user to ensure we have the latest balance
                    $user->refresh();

                    // Create a mock Stripe session object for provisioning
                    $mockSession = (object) [
                        'id' => 'credits_' . uniqid(),
                        'subscription' => $subscription->stripe_id,
                        'metadata' => $metadata,
                    ];

                    // Provision server (this handles its own transaction and Wings call)
                    // The server will be fully committed before Wings is called
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
                    // Refund credits on error using transaction service
                    $this->creditTransactionService->recordRefund(
                        $user,
                        $priceAmount,
                        "Refund for failed server creation - {$request->input('server_name')}",
                        $subscription->id,
                        ['error' => $e->getMessage()]
                    );
                    
                    // Delete the subscription if server creation failed
                    try {
                        $subscription->delete();
                    } catch (\Exception $subException) {
                        Log::warning('Failed to delete subscription after server creation failure', [
                            'subscription_id' => $subscription->id,
                            'error' => $subException->getMessage(),
                        ]);
                    }
                    
                    Log::error('Failed to provision server after credit deduction, credits refunded', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                        'credits_refunded' => $priceAmount,
                    ]);
                    
                    // Return a user-friendly error message
                    return response()->json([
                        'errors' => [[
                            'code' => 'ServerProvisioningFailed',
                            'status' => '500',
                            'detail' => 'Failed to provision server. Your credits have been refunded. Please try again later or contact support if the issue persists.',
                        ]],
                    ], 500);
                }
            }

            // Credits disabled - use Stripe checkout flow
            // Get or create Stripe customer
            $stripeCustomer = $this->getOrCreateStripeCustomer($user);

            // Calculate actual price to charge (with first month discount if applicable)
            $actualPriceAmount = $priceAmount;
            if ($plan && $plan->first_month_sales_percentage && $plan->first_month_sales_percentage > 0) {
                $discount = $plan->first_month_sales_percentage / 100;
                $actualPriceAmount = round($plan->price * (1 - $discount), 2);
            }

            $stripePriceId = null;
            if ($plan) {
                // Create Stripe Price with selected interval and discounted price
                // We need to create a new price for the selected interval, not use plan's default interval
                $stripePriceId = $this->stripePriceService->createPriceForPlanWithInterval(
                    $plan,
                    $interval,
                    $priceAmount
                );
            } else {
                // Create Stripe Price for custom plan
                $stripePriceId = $this->stripePriceService->createPriceForCustomPlan(
                    $priceAmount, // Use discounted price for recurring
                    $interval,
                    $request->input('memory')
                );
            }
            
            // Create coupon for first month discount if applicable
            $couponId = null;
            if ($plan && $plan->first_month_sales_percentage && $plan->first_month_sales_percentage > 0) {
                try {
                    $coupon = Coupon::create([
                        'percent_off' => $plan->first_month_sales_percentage,
                        'duration' => 'once', // Only applies to first invoice
                        'name' => 'First Month Discount',
                        'metadata' => [
                            'plan_id' => (string) $plan->id,
                            'type' => 'first_month_discount',
                        ],
                    ]);
                    $couponId = $coupon->id;
                } catch (ApiErrorException $e) {
                    Log::warning('Failed to create Stripe coupon for first month discount', [
                        'plan_id' => $plan->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue without coupon - user will be charged full price
                }
            }
            
            // Build metadata for webhook handler
            $metadata = [
                'user_id' => (string) $user->id,
                'type' => $type,
                'server_name' => $request->input('server_name'),
                'server_description' => $request->input('server_description', ''),
            ];
            
            // Add subdomain info if provided
            if ($request->has('subdomain') && $request->has('domain_id')) {
                $metadata['subdomain'] = $request->input('subdomain');
                $metadata['domain_id'] = (string) $request->input('domain_id');
            }

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

            // Build checkout session parameters
            $sessionParams = [
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
            ];
            
            // Add coupon if we have a first month discount
            if ($couponId) {
                $sessionParams['discounts'] = [[
                    'coupon' => $couponId,
                ]];
            }

            // Create Stripe Checkout Session
            $checkoutSession = Session::create($sessionParams);

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
