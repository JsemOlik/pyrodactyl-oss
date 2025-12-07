<?php

namespace Pterodactyl\Http\Controllers\Webhook;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Hosting\ServerProvisioningService;
use Pterodactyl\Services\Hosting\VpsProvisioningService;

class StripeWebhookController extends Controller
{
    public function __construct(
        private ServerProvisioningService $serverProvisioningService,
        private VpsProvisioningService $vpsProvisioningService
    ) {
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * Handle incoming Stripe webhook events.
     */
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('cashier.webhook.secret');

        if (empty($webhookSecret)) {
            Log::error('Stripe webhook secret not configured');
            return response('Webhook secret not configured', 500);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $webhookSecret,
                config('cashier.webhook.tolerance', 300)
            );
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe webhook: Invalid payload', [
                'error' => $e->getMessage(),
            ]);
            return response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe webhook: Invalid signature', [
                'error' => $e->getMessage(),
            ]);
            return response('Invalid signature', 400);
        }

        // Handle the event with error handling
        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    Log::info('Received checkout.session.completed event', [
                        'session_id' => $event->data->object->id ?? null,
                    ]);
                    $this->handleCheckoutSessionCompleted($event->data->object);
                    break;

                case 'customer.subscription.created':
                    $this->handleSubscriptionCreated($event->data->object);
                    break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event->data->object);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event->data->object);
                break;

            case 'invoice.payment_succeeded':
                Log::info('Stripe invoice payment succeeded', [
                    'invoice_id' => $event->data->object->id,
                ]);
                break;

            case 'invoice.payment_failed':
                Log::warning('Stripe invoice payment failed', [
                    'invoice_id' => $event->data->object->id,
                ]);
                break;

                default:
                    Log::info('Unhandled Stripe webhook event', [
                        'type' => $event->type,
                    ]);
            }
        } catch (\Exception $e) {
            Log::error('Error handling Stripe webhook event', [
                'event_type' => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return 200 to acknowledge receipt, but log the error
            // Stripe will retry on 5xx errors, but we've already logged the issue
            return response('Webhook received with errors', 200);
        }

        return response('Webhook received', 200);
    }

    /**
     * Handle checkout session completed event.
     */
    private function handleCheckoutSessionCompleted($session): void
    {
        Log::info('Stripe checkout session completed', [
            'session_id' => $session->id,
            'customer_id' => $session->customer,
            'subscription_id' => $session->subscription,
            'metadata' => $session->metadata,
        ]);

        // Handle one-time payments (credits purchases)
        if ($session->mode === 'payment') {
            $this->handleCreditsPurchase($session);
            return;
        }

        // Only process if this is a subscription checkout
        if ($session->mode !== 'subscription') {
            Log::warning('Checkout session is not a subscription or payment', [
                'session_id' => $session->id,
                'mode' => $session->mode,
            ]);
            return;
        }

        // Extract metadata from the session
        $metadata = $session->metadata ?? [];
        
        if (empty($metadata['user_id'])) {
            Log::error('Checkout session missing user_id in metadata', [
                'session_id' => $session->id,
            ]);
            return;
        }

        // Determine provisioning type from metadata
        $type = $metadata['type'] ?? 'game-server';
        
        // Provision the server or VPS based on type
        try {
            if ($type === 'vps') {
                $this->vpsProvisioningService->provisionVps($session);
                Log::info('VPS provisioned successfully', [
                    'session_id' => $session->id,
                    'user_id' => $metadata['user_id'],
                ]);
            } else {
                $this->serverProvisioningService->provisionServer($session);
                Log::info('Server provisioned successfully', [
                    'session_id' => $session->id,
                    'user_id' => $metadata['user_id'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to provision from webhook', [
                'session_id' => $session->id,
                'user_id' => $metadata['user_id'],
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Re-throw to trigger webhook retry from Stripe
            throw $e;
        }
    }

    /**
     * Handle subscription created event.
     * This is called when a new subscription is created, which happens after checkout.
     * We use this as the primary trigger for server provisioning since checkout.session.completed
     * may not always be reliable or may not be configured in the webhook.
     */
    private function handleSubscriptionCreated($subscription): void
    {
        Log::info('Stripe subscription created', [
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer,
            'metadata' => $subscription->metadata ?? [],
        ]);

        // Check if server was already provisioned (idempotency check)
        $existingSubscription = \Pterodactyl\Models\Subscription::where('stripe_id', $subscription->id)->first();
        
        if ($existingSubscription && $existingSubscription->servers()->exists()) {
            Log::info('Subscription already has servers, skipping provisioning', [
                'subscription_id' => $subscription->id,
            ]);
            return;
        }

        // Retrieve subscription fresh from Stripe to ensure we have all metadata
        try {
            \Stripe\Stripe::setApiKey(config('cashier.secret'));
            $subscription = \Stripe\Subscription::retrieve($subscription->id, ['expand' => ['default_payment_method']]);
        } catch (\Exception $e) {
            Log::warning('Could not retrieve subscription from Stripe, using webhook data', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Always try to get checkout session first (most reliable source of metadata)
        $provisioned = $this->tryProvisionFromCheckoutSession($subscription);
        
        if ($provisioned) {
            return; // Successfully provisioned from checkout session
        }

        // Fallback: Try to use subscription metadata
        $rawMetadata = $subscription->metadata ?? [];
        $metadata = $this->convertMetadataToArray($rawMetadata);
        
        Log::info('Subscription metadata retrieved', [
            'subscription_id' => $subscription->id,
            'metadata_keys' => is_array($metadata) ? array_keys($metadata) : 'not_array',
            'has_user_id' => !empty($metadata['user_id']),
            'has_nest_id' => !empty($metadata['nest_id']),
            'has_egg_id' => !empty($metadata['egg_id']),
        ]);

        // Determine provisioning type from metadata
        $type = $metadata['type'] ?? 'game-server';
        
        // Check required metadata based on type
        if ($type === 'vps') {
            if (empty($metadata['user_id']) || empty($metadata['server_name'])) {
                Log::error('Cannot provision VPS: missing required metadata in subscription and checkout session', [
                    'subscription_id' => $subscription->id,
                    'metadata' => $metadata,
                ]);
                return;
            }
        } else {
            if (empty($metadata['user_id']) || empty($metadata['nest_id']) || empty($metadata['egg_id'])) {
                Log::error('Cannot provision server: missing required metadata in subscription and checkout session', [
                    'subscription_id' => $subscription->id,
                    'metadata' => $metadata,
                ]);
                return;
            }
        }

        // Provision server or VPS using subscription metadata
        Log::info('Attempting to provision using subscription metadata', [
            'subscription_id' => $subscription->id,
            'user_id' => $metadata['user_id'],
            'type' => $type,
        ]);

        try {
            // Create a session-like object from subscription for provisioning service
            $mockSession = (object) [
                'id' => 'sub_' . $subscription->id,
                'customer' => $subscription->customer,
                'subscription' => $subscription->id,
                'mode' => 'subscription',
                'metadata' => $metadata,
            ];

            if ($type === 'vps') {
                $this->vpsProvisioningService->provisionVps($mockSession);
                Log::info('VPS provisioned successfully from subscription metadata', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $metadata['user_id'],
                ]);
            } else {
                $this->serverProvisioningService->provisionServer($mockSession);
                Log::info('Server provisioned successfully from subscription metadata', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $metadata['user_id'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to provision from subscription metadata', [
                'subscription_id' => $subscription->id,
                'user_id' => $metadata['user_id'] ?? null,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Re-throw to trigger webhook retry
            throw $e;
        }
    }

    /**
     * Try to provision server by retrieving the checkout session.
     * Returns true if provisioning was attempted (successfully or not), false if session not found.
     */
    private function tryProvisionFromCheckoutSession($subscription): bool
    {
        try {
            \Stripe\Stripe::setApiKey(config('cashier.secret'));
            
            // Find checkout sessions for this subscription
            $sessions = \Stripe\Checkout\Session::all([
                'subscription' => $subscription->id,
                'limit' => 1,
            ]);
            
            if (!empty($sessions->data)) {
                $session = $sessions->data[0];
                Log::info('Found checkout session for subscription, provisioning from session', [
                    'subscription_id' => $subscription->id,
                    'session_id' => $session->id,
                    'session_metadata' => $session->metadata ?? [],
                ]);
                
                $this->handleCheckoutSessionCompleted($session);
                return true;
            } else {
                Log::warning('No checkout session found for subscription', [
                    'subscription_id' => $subscription->id,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Failed to retrieve checkout session for provisioning', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Don't re-throw - allow fallback to subscription metadata
            return false;
        }
    }

    /**
     * Handle subscription updated event.
     */
    private function handleSubscriptionUpdated($subscription): void
    {
        Log::info('Stripe subscription updated', [
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
        ]);

        // Update subscription status in database
        $subscriptionModel = \Pterodactyl\Models\Subscription::where('stripe_id', $subscription->id)->first();
        
        if ($subscriptionModel) {
            $subscriptionModel->update([
                'stripe_status' => $subscription->status,
                'ends_at' => $subscription->cancel_at ? \Carbon\Carbon::createFromTimestamp($subscription->cancel_at) : null,
            ]);
        }
    }

    /**
     * Handle subscription deleted/cancelled event.
     */
    private function handleSubscriptionDeleted($subscription): void
    {
        Log::info('Stripe subscription deleted', [
            'subscription_id' => $subscription->id,
        ]);

        // Update subscription status in database
        $subscriptionModel = \Pterodactyl\Models\Subscription::where('stripe_id', $subscription->id)->first();
        
        if ($subscriptionModel) {
            $subscriptionModel->update([
                'stripe_status' => $subscription->status,
                'ends_at' => now(),
            ]);
        }
    }

    /**
     * Handle credits purchase (one-time payment).
     */
    private function handleCreditsPurchase($session): void
    {
        Log::info('Processing credits purchase', [
            'session_id' => $session->id,
            'metadata' => $session->metadata,
        ]);

        $metadata = $session->metadata ?? [];
        
        // Check if this is a credits purchase
        if (($metadata['type'] ?? null) !== 'credits_purchase') {
            Log::warning('Payment session is not a credits purchase', [
                'session_id' => $session->id,
                'metadata_type' => $metadata['type'] ?? null,
            ]);
            return;
        }

        if (empty($metadata['user_id'])) {
            Log::error('Credits purchase missing user_id in metadata', [
                'session_id' => $session->id,
            ]);
            return;
        }

        $userId = (int) $metadata['user_id'];
        $amount = isset($metadata['amount']) ? (float) $metadata['amount'] : null;

        if (!$amount || $amount <= 0) {
            Log::error('Invalid credits purchase amount', [
                'session_id' => $session->id,
                'user_id' => $userId,
                'amount' => $amount,
            ]);
            return;
        }

        try {
            $user = \Pterodactyl\Models\User::findOrFail($userId);
            
            // Add credits to user account
            $user->increment('credits_balance', $amount);
            
            Log::info('Credits added to user account', [
                'session_id' => $session->id,
                'user_id' => $userId,
                'amount' => $amount,
                'new_balance' => $user->fresh()->credits_balance,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add credits to user account', [
                'session_id' => $session->id,
                'user_id' => $userId,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Re-throw to trigger webhook retry
            throw $e;
        }
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
