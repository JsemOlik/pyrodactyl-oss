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

class StripeWebhookController extends Controller
{
    public function __construct(
        private ServerProvisioningService $provisioningService
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

        // Handle the event
        switch ($event->type) {
            case 'checkout.session.completed':
                $this->handleCheckoutSessionCompleted($event->data->object);
                break;

            case 'customer.subscription.created':
                Log::info('Stripe subscription created', [
                    'subscription_id' => $event->data->object->id,
                ]);
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

        // Only process if this is a subscription checkout (not a one-time payment)
        if ($session->mode !== 'subscription') {
            Log::warning('Checkout session is not a subscription', [
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

        // Provision the server
        try {
            $this->provisioningService->provisionServer($session);
            Log::info('Server provisioned successfully', [
                'session_id' => $session->id,
                'user_id' => $metadata['user_id'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to provision server from webhook', [
                'session_id' => $session->id,
                'user_id' => $metadata['user_id'],
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Re-throw to trigger webhook retry from Stripe
            throw $e;
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
}
