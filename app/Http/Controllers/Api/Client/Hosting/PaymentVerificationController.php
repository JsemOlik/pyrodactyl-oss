<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Hosting;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Subscription;
use Pterodactyl\Models\Server;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;

class PaymentVerificationController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * Check payment status by Stripe checkout session ID.
     */
    public function check(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id');
        
        if (!$sessionId) {
            return response()->json([
                'errors' => [[
                    'code' => 'ValidationError',
                    'status' => '400',
                    'detail' => 'Session ID is required.',
                ]],
            ], 400);
        }

        try {
            // Retrieve the checkout session from Stripe
            $stripeSession = Session::retrieve($sessionId);
            
            // Check if payment was successful
            if ($stripeSession->payment_status !== 'paid') {
                return response()->json([
                    'status' => 'pending',
                    'payment_status' => $stripeSession->payment_status,
                    'message' => 'Payment is still being processed.',
                ]);
            }

            // Check if subscription exists in our database
            $subscriptionId = $stripeSession->subscription;
            
            if (!$subscriptionId) {
                return response()->json([
                    'status' => 'processing',
                    'message' => 'Payment confirmed. Setting up your server...',
                ]);
            }

            $subscription = Subscription::where('stripe_id', $subscriptionId)->first();
            
            if (!$subscription) {
                return response()->json([
                    'status' => 'processing',
                    'message' => 'Payment confirmed. Setting up your server...',
                ]);
            }

            // Check if server has been created for this subscription
            $server = Server::where('subscription_id', $subscription->id)
                ->where('owner_id', $request->user()->id)
                ->first();

            if ($server) {
                return response()->json([
                    'status' => 'completed',
                    'message' => 'Server created successfully!',
                    'server' => [
                        'id' => $server->id,
                        'uuid' => $server->uuid,
                        'name' => $server->name,
                    ],
                ]);
            }

            return response()->json([
                'status' => 'processing',
                'message' => 'Payment confirmed. Setting up your server...',
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe API error during payment verification', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'errors' => [[
                    'code' => 'StripeApiError',
                    'status' => '500',
                    'detail' => 'Unable to verify payment status. Please contact support.',
                ]],
            ], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error during payment verification', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'errors' => [[
                    'code' => 'InternalServerError',
                    'status' => '500',
                    'detail' => 'An unexpected error occurred. Please contact support.',
                ]],
            ], 500);
        }
    }
}

