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

class CheckoutController extends Controller
{
    public function __construct(
        private StripePriceService $stripePriceService
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

        try {
            // Get or create Stripe customer
            $stripeCustomer = $this->getOrCreateStripeCustomer($user);

            // Determine plan and pricing
            $plan = null;
            $stripePriceId = null;
            $interval = 'month';
            $priceAmount = 0;

            if ($request->has('plan_id')) {
                $plan = Plan::findOrFail($request->input('plan_id'));
                $interval = $plan->interval;
                
                // Get or create Stripe Price for this plan
                $stripePriceId = $this->stripePriceService->getOrCreatePriceForPlan($plan);
                $priceAmount = $plan->price;
            } else {
                // Custom plan - calculate price and create Stripe Price on-the-fly
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
                
                // Create Stripe Price for custom plan
                $stripePriceId = $this->stripePriceService->createPriceForCustomPlan(
                    $priceAmount,
                    $interval,
                    $memory
                );
            }

            // Build metadata for webhook handler
            $metadata = [
                'user_id' => $user->id,
                'nest_id' => $request->input('nest_id'),
                'egg_id' => $request->input('egg_id'),
                'server_name' => $request->input('server_name'),
                'server_description' => $request->input('server_description', ''),
            ];

            if ($plan) {
                $metadata['plan_id'] = $plan->id;
            } else {
                $metadata['custom'] = 'true';
                $metadata['memory'] = $request->input('memory');
                $metadata['interval'] = $interval;
            }

            // Get success and cancel URLs
            $cancelUrl = config('app.url') . '/hosting/checkout?' . http_build_query($request->only(['plan_id', 'custom', 'memory', 'interval', 'nest_id', 'egg_id']));

            // Create Stripe Checkout Session
            // Stripe will replace {CHECKOUT_SESSION_ID} with the actual session ID in the success_url
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
