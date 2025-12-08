<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Billing;

use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\User;
use Pterodactyl\Http\Controllers\Controller;

class CreditsController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * Get the current user's credits balance.
     */
    public function balance(): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();
        $user->refresh();

        return response()->json([
            'object' => 'credits_balance',
            'data' => [
                'balance' => (float) $user->credits_balance,
                'currency' => config('cashier.currency', 'usd'),
            ],
        ]);
    }

    /**
     * Create a Stripe checkout session for purchasing credits.
     */
    public function purchase(): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();

        $amount = request()->input('amount');

        if (!$amount || $amount < 1) {
            return response()->json([
                'errors' => [[
                    'code' => 'InvalidAmount',
                    'status' => '400',
                    'detail' => 'Please specify a valid amount to purchase (minimum $1).',
                ]],
            ], 400);
        }

        try {
            // Get or create Stripe customer
            $stripeCustomer = $this->getOrCreateStripeCustomer($user);

            // Create a one-time payment checkout session for credits
            $checkoutSession = Session::create([
                'customer' => $stripeCustomer->id,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => config('cashier.currency', 'usd'),
                        'product_data' => [
                            'name' => 'Account Credits',
                            'description' => 'Purchase account credits to buy servers',
                        ],
                        'unit_amount' => (int) ($amount * 100), // Convert to cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => config('app.url') . '/billing?credits_purchased=true',
                'cancel_url' => config('app.url') . '/billing',
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'type' => 'credits_purchase',
                    'amount' => (string) $amount,
                ],
                'allow_promotion_codes' => true,
            ]);

            Log::info('Credits purchase checkout session created', [
                'session_id' => $checkoutSession->id,
                'user_id' => $user->id,
                'amount' => $amount,
            ]);

            return response()->json([
                'object' => 'checkout_session',
                'data' => [
                    'checkout_url' => $checkoutSession->url,
                    'session_id' => $checkoutSession->id,
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error during credits purchase checkout session creation', [
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
            Log::error('Unexpected error during credits purchase checkout session creation', [
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
