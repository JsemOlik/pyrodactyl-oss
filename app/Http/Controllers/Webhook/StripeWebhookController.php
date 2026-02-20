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

use Pterodactyl\Services\Credits\CreditTransactionService;

class StripeWebhookController extends Controller
{
    public function __construct(
        private ServerProvisioningService $serverProvisioningService,

        private CreditTransactionService $creditTransactionService
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
                    $this->handleInvoicePaymentSucceeded($event->data->object);
                    break;

                case 'invoice.payment_failed':
                    Log::warning('Stripe invoice payment failed', [
                        'invoice_id' => $event->data->object->id,
                    ]);
                    break;

                case 'payment_intent.succeeded':
                    Log::info('Received payment_intent.succeeded event', [
                        'payment_intent_id' => $event->data->object->id ?? null,
                    ]);
                    $this->handlePaymentIntentSucceeded($event->data->object);
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

        // Provision the server
        try {

            $this->serverProvisioningService->provisionServer($session);
            Log::info('Server provisioned successfully', [
                'session_id' => $session->id,
                'user_id' => $metadata['user_id'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to provision from webhook', [
                'session_id' => $session->id,
                'user_id' => $metadata['user_id'],
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Refund the payment since server creation failed
            $this->refundFailedProvisioning($session, $e);

            // Re-throw to trigger webhook retry from Stripe (but refund is already processed)
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

        // Check required metadata

        if (empty($metadata['user_id']) || empty($metadata['nest_id']) || empty($metadata['egg_id'])) {
            Log::error('Cannot provision server: missing required metadata in subscription and checkout session', [
                'subscription_id' => $subscription->id,
                'metadata' => $metadata,
            ]);
            return;
        }


        // Provision server using subscription metadata
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


            $this->serverProvisioningService->provisionServer($mockSession);
            Log::info('Server provisioned successfully from subscription metadata', [
                'subscription_id' => $subscription->id,
                'user_id' => $metadata['user_id'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to provision from subscription metadata', [
                'subscription_id' => $subscription->id,
                'user_id' => $metadata['user_id'] ?? null,
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Refund the payment since server creation failed
            $this->refundFailedProvisioningFromSubscription($subscription, $e);

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
            $updateData = [
                'stripe_status' => $subscription->status,
            ];

            // Update ends_at if subscription is being canceled
            if ($subscription->cancel_at) {
                $updateData['ends_at'] = \Carbon\Carbon::createFromTimestamp($subscription->cancel_at);
            } elseif ($subscription->cancel_at_period_end && $subscription->current_period_end) {
                // If cancel_at_period_end is true, use current_period_end as ends_at
                $updateData['ends_at'] = \Carbon\Carbon::createFromTimestamp($subscription->current_period_end);
            }

            // Update next_billing_at if available
            if ($subscription->current_period_end && $subscription->status === 'active') {
                $updateData['next_billing_at'] = \Carbon\Carbon::createFromTimestamp($subscription->current_period_end);
            }

            $subscriptionModel->update($updateData);

            Log::info('Subscription status synced from Stripe webhook', [
                'subscription_id' => $subscriptionModel->id,
                'stripe_status' => $subscription->status,
                'ends_at' => $updateData['ends_at'] ?? null,
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
            'mode' => $session->mode ?? null,
            'metadata' => $session->metadata,
            'payment_status' => $session->payment_status ?? null,
        ]);

        // Convert metadata to array if it's an object
        $rawMetadata = $session->metadata ?? [];
        $metadata = $this->convertMetadataToArray($rawMetadata);

        Log::info('Credits purchase metadata converted', [
            'session_id' => $session->id,
            'metadata' => $metadata,
            'metadata_type' => $metadata['type'] ?? null,
        ]);

        // Check if this is a credits purchase
        if (($metadata['type'] ?? null) !== 'credits_purchase') {
            Log::warning('Payment session is not a credits purchase', [
                'session_id' => $session->id,
                'metadata_type' => $metadata['type'] ?? null,
                'all_metadata' => $metadata,
            ]);
            return;
        }

        if (empty($metadata['user_id'])) {
            Log::error('Credits purchase missing user_id in metadata', [
                'session_id' => $session->id,
                'metadata' => $metadata,
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
                'metadata' => $metadata,
            ]);
            return;
        }

        try {
            $user = \Pterodactyl\Models\User::findOrFail($userId);

            // Add credits using transaction service
            $this->creditTransactionService->recordPurchase(
                $user,
                $amount,
                $session->id,
                [
                    'stripe_session_id' => $session->id,
                    'payment_intent_id' => $session->payment_intent ?? null,
                ]
            );

            Log::info('Credits added to user account', [
                'session_id' => $session->id,
                'user_id' => $userId,
                'amount' => $amount,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add credits to user account', [
                'session_id' => $session->id,
                'user_id' => $userId ?? null,
                'amount' => $amount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger webhook retry
            throw $e;
        }
    }

    /**
     * Handle payment intent succeeded event (for credits purchases).
     */
    private function handlePaymentIntentSucceeded($paymentIntent): void
    {
        Log::info('Processing payment_intent.succeeded', [
            'payment_intent_id' => $paymentIntent->id,
            'customer_id' => $paymentIntent->customer ?? null,
            'amount' => $paymentIntent->amount ?? null,
            'metadata' => $paymentIntent->metadata ?? [],
        ]);

        // Convert payment intent metadata to array
        $rawMetadata = $paymentIntent->metadata ?? [];
        $metadata = $this->convertMetadataToArray($rawMetadata);

        // Check if this payment intent has credits purchase metadata
        if (($metadata['type'] ?? null) === 'credits_purchase') {
            Log::info('Payment intent is a credits purchase, processing directly', [
                'payment_intent_id' => $paymentIntent->id,
                'metadata' => $metadata,
            ]);

            if (!empty($metadata['user_id']) && !empty($metadata['amount'])) {
                $userId = (int) $metadata['user_id'];
                $amount = (float) $metadata['amount'];

                try {
                    $user = \Pterodactyl\Models\User::findOrFail($userId);

                    // Add credits using transaction service
                    $this->creditTransactionService->recordPurchase(
                        $user,
                        $amount,
                        $paymentIntent->id,
                        [
                            'payment_intent_id' => $paymentIntent->id,
                            'stripe_customer_id' => $paymentIntent->customer ?? null,
                        ]
                    );

                    Log::info('Credits added to user account from payment_intent', [
                        'payment_intent_id' => $paymentIntent->id,
                        'user_id' => $userId,
                        'amount' => $amount,
                    ]);
                    return;
                } catch (\Exception $e) {
                    Log::error('Failed to add credits from payment_intent', [
                        'payment_intent_id' => $paymentIntent->id,
                        'user_id' => $userId ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw $e;
                }
            }
        }

        // Try to find the checkout session associated with this payment intent as fallback
        try {
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Search for checkout sessions with this payment intent
            $sessions = \Stripe\Checkout\Session::all([
                'payment_intent' => $paymentIntent->id,
                'limit' => 1,
            ]);

            if (!empty($sessions->data)) {
                $session = $sessions->data[0];
                Log::info('Found checkout session for payment intent', [
                    'payment_intent_id' => $paymentIntent->id,
                    'session_id' => $session->id,
                    'session_mode' => $session->mode ?? null,
                ]);

                // If it's a payment mode session, process it as credits purchase
                if (($session->mode ?? null) === 'payment') {
                    $this->handleCheckoutSessionCompleted($session);
                }
            } else {
                Log::warning('No checkout session found for payment intent and no metadata', [
                    'payment_intent_id' => $paymentIntent->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process payment_intent.succeeded', [
                'payment_intent_id' => $paymentIntent->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't re-throw if we couldn't find session - credits might have been added via metadata
        }
    }

    /**
     * Refund payment when server provisioning fails (from checkout session).
     */
    private function refundFailedProvisioning($session, \Exception $error): void
    {
        try {
            $metadata = $this->convertMetadataToArray($session->metadata ?? []);

            // Check if this is a credits-based purchase (no Stripe payment to refund)
            // Credits-based purchases use mock session IDs starting with 'credits_'
            if (str_starts_with($session->id ?? '', 'credits_')) {
                // Credits-based purchase - refund credits
                if (!empty($metadata['user_id'])) {
                    $user = \Pterodactyl\Models\User::find((int) $metadata['user_id']);
                    if ($user) {
                        // Find the subscription to get the amount
                        $subscription = \Pterodactyl\Models\Subscription::where('stripe_id', $session->subscription ?? null)
                            ->orWhere('stripe_id', 'like', 'credits_%')
                            ->where('user_id', $user->id)
                            ->latest()
                            ->first();

                        if ($subscription && $subscription->billing_amount) {
                            $this->creditTransactionService->recordRefund(
                                $user,
                                (float) $subscription->billing_amount,
                                "Refund for failed server creation from webhook",
                                $subscription->id,
                                ['error' => $error->getMessage(), 'session_id' => $session->id]
                            );

                            Log::info('Credits refunded for failed server provisioning', [
                                'user_id' => $user->id,
                                'amount' => $subscription->billing_amount,
                                'session_id' => $session->id,
                            ]);
                        }
                    }
                }
                return;
            }

            // Also check if subscription is credits-based by looking up the subscription
            if (!empty($session->subscription)) {
                $subscriptionModel = \Pterodactyl\Models\Subscription::where('stripe_id', $session->subscription)->first();
                if ($subscriptionModel && $subscriptionModel->is_credits_based) {
                    // Credits-based subscription - refund credits
                    if (!empty($metadata['user_id'])) {
                        $user = \Pterodactyl\Models\User::find((int) $metadata['user_id']);
                        if ($user && $subscriptionModel->billing_amount) {
                            $this->creditTransactionService->recordRefund(
                                $user,
                                (float) $subscriptionModel->billing_amount,
                                "Refund for failed server creation from webhook",
                                $subscriptionModel->id,
                                ['error' => $error->getMessage(), 'session_id' => $session->id]
                            );

                            Log::info('Credits refunded for failed server provisioning (credits-based subscription)', [
                                'user_id' => $user->id,
                                'amount' => $subscriptionModel->billing_amount,
                                'session_id' => $session->id,
                            ]);
                        }
                    }
                    return;
                }
            }

            // Stripe payment - refund via Stripe API
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Get payment intent from checkout session
            $checkoutSession = \Stripe\Checkout\Session::retrieve($session->id, [
                'expand' => ['payment_intent'],
            ]);

            if ($checkoutSession->payment_intent) {
                $paymentIntent = $checkoutSession->payment_intent;

                // Create refund
                $refund = \Stripe\Refund::create([
                    'payment_intent' => is_string($paymentIntent) ? $paymentIntent : $paymentIntent->id,
                    'reason' => 'requested_by_customer',
                    'metadata' => [
                        'reason' => 'server_provisioning_failed',
                        'session_id' => $session->id,
                        'error' => $error->getMessage(),
                    ],
                ]);

                Log::info('Stripe payment refunded for failed server provisioning', [
                    'session_id' => $session->id,
                    'payment_intent_id' => is_string($paymentIntent) ? $paymentIntent : $paymentIntent->id,
                    'refund_id' => $refund->id,
                    'amount' => $refund->amount / 100,
                ]);
            } else {
                // Try to get payment intent from subscription's latest invoice
                if ($checkoutSession->subscription) {
                    try {
                        $subscription = \Stripe\Subscription::retrieve($checkoutSession->subscription);
                        $invoices = \Stripe\Invoice::all([
                            'subscription' => $subscription->id,
                            'limit' => 1,
                        ]);

                        if (!empty($invoices->data)) {
                            $invoice = $invoices->data[0];
                            if ($invoice->payment_intent) {
                                $refund = \Stripe\Refund::create([
                                    'payment_intent' => is_string($invoice->payment_intent) ? $invoice->payment_intent : $invoice->payment_intent->id,
                                    'reason' => 'requested_by_customer',
                                    'metadata' => [
                                        'reason' => 'server_provisioning_failed',
                                        'session_id' => $session->id,
                                        'error' => $error->getMessage(),
                                    ],
                                ]);

                                Log::info('Stripe payment refunded via invoice for failed server provisioning', [
                                    'session_id' => $session->id,
                                    'refund_id' => $refund->id,
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to refund via subscription invoice', [
                            'session_id' => $session->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to refund payment for failed server provisioning', [
                'session_id' => $session->id ?? null,
                'error' => $e->getMessage(),
                'original_error' => $error->getMessage(),
            ]);
        }
    }

    /**
     * Refund payment when server provisioning fails (from subscription).
     */
    private function refundFailedProvisioningFromSubscription($subscription, \Exception $error): void
    {
        try {
            // Check if this is a credits-based subscription
            $subscriptionModel = \Pterodactyl\Models\Subscription::where('stripe_id', $subscription->id)->first();

            if ($subscriptionModel && $subscriptionModel->is_credits_based) {
                // Credits-based subscription - refund credits
                $user = $subscriptionModel->user;
                if ($user && $subscriptionModel->billing_amount) {
                    $this->creditTransactionService->recordRefund(
                        $user,
                        (float) $subscriptionModel->billing_amount,
                        "Refund for failed server creation from webhook",
                        $subscriptionModel->id,
                        ['error' => $error->getMessage(), 'subscription_id' => $subscription->id]
                    );

                    Log::info('Credits refunded for failed server provisioning (credits-based subscription)', [
                        'user_id' => $user->id,
                        'amount' => $subscriptionModel->billing_amount,
                        'subscription_id' => $subscription->id,
                    ]);
                }
                return;
            }

            // Stripe payment - refund via Stripe API
            \Stripe\Stripe::setApiKey(config('cashier.secret'));

            // Get the latest invoice for this subscription
            $invoices = \Stripe\Invoice::all([
                'subscription' => $subscription->id,
                'limit' => 1,
            ]);

            if (!empty($invoices->data)) {
                $invoice = $invoices->data[0];

                if ($invoice->payment_intent && $invoice->status === 'paid') {
                    $refund = \Stripe\Refund::create([
                        'payment_intent' => is_string($invoice->payment_intent) ? $invoice->payment_intent : $invoice->payment_intent->id,
                        'reason' => 'requested_by_customer',
                        'metadata' => [
                            'reason' => 'server_provisioning_failed',
                            'subscription_id' => $subscription->id,
                            'error' => $error->getMessage(),
                        ],
                    ]);

                    Log::info('Stripe payment refunded for failed server provisioning from subscription', [
                        'subscription_id' => $subscription->id,
                        'refund_id' => $refund->id,
                        'amount' => $refund->amount / 100,
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to refund payment for failed server provisioning from subscription', [
                'subscription_id' => $subscription->id ?? null,
                'error' => $e->getMessage(),
                'original_error' => $error->getMessage(),
            ]);
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

    /**
     * Handle invoice payment succeeded event (subscription renewals).
     */
    private function handleInvoicePaymentSucceeded($invoice): void
    {
        try {
            // Get subscription from invoice
            $subscriptionId = $invoice->subscription ?? null;
            if (!$subscriptionId) {
                Log::info('Invoice payment succeeded but no subscription ID', [
                    'invoice_id' => $invoice->id,
                ]);
                return;
            }

            // Find subscription in database
            $subscriptionModel = \Pterodactyl\Models\Subscription::where('stripe_id', $subscriptionId)->first();
            if (!$subscriptionModel) {
                Log::warning('Invoice payment succeeded but subscription not found in database', [
                    'invoice_id' => $invoice->id,
                    'subscription_id' => $subscriptionId,
                ]);
                return;
            }

            // Skip if this is a credits-based subscription (handled by command)
            if ($subscriptionModel->is_credits_based) {
                return;
            }

            // Update next_billing_at based on billing_interval
            if ($subscriptionModel->billing_interval) {
                $nextBillingAt = match ($subscriptionModel->billing_interval) {
                    'month' => now()->addMonth(),
                    'quarter' => now()->addMonths(3),
                    'half-year' => now()->addMonths(6),
                    'year' => now()->addYear(),
                    default => now()->addMonth(),
                };

                $subscriptionModel->update([
                    'next_billing_at' => $nextBillingAt,
                    'stripe_status' => 'active',
                ]);

                Log::info('Subscription renewal processed - updated next_billing_at', [
                    'subscription_id' => $subscriptionModel->id,
                    'invoice_id' => $invoice->id,
                    'billing_interval' => $subscriptionModel->billing_interval,
                    'next_billing_at' => $nextBillingAt,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle invoice payment succeeded', [
                'invoice_id' => $invoice->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
