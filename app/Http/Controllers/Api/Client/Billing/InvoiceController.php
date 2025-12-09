<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Billing;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Stripe\Stripe;
use Stripe\Exception\ApiErrorException;

class InvoiceController extends ClientApiController
{
    public function __construct()
    {
        parent::__construct();
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * Get all invoices for the authenticated user.
     */
    public function index(Request $request): array
    {
        $user = $request->user();

        // Get Stripe customer ID
        $stripeCustomerId = $user->stripe_id;
        
        if (!$stripeCustomerId) {
            // Try to get customer ID from subscriptions (skip credits-based subscriptions)
            $subscription = $user->subscriptions()
                ->where('is_credits_based', false)
                ->whereNotNull('stripe_id')
                ->where('stripe_id', 'not like', 'credits_%')
                ->first();
            if ($subscription && $subscription->stripe_id) {
                try {
                    \Stripe\Stripe::setApiKey(config('cashier.secret'));
                    $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_id);
                    $stripeCustomerId = $stripeSubscription->customer;
                    
                    // Save it for future use
                    if ($stripeCustomerId && is_string($stripeCustomerId)) {
                        $user->update(['stripe_id' => $stripeCustomerId]);
                    }
                } catch (\Exception $e) {
                    Log::error('Could not retrieve customer ID from subscription', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (!$stripeCustomerId) {
            return ['data' => []];
        }

        try {
            // Fetch invoices from Stripe
            $stripeInvoices = \Stripe\Invoice::all([
                'customer' => $stripeCustomerId,
                'limit' => 100,
            ]);

            $invoices = [];
            foreach ($stripeInvoices->data as $stripeInvoice) {
                // Use total amount (for unpaid invoices) or amount_paid (for paid invoices)
                $amount = $stripeInvoice->status === 'paid' 
                    ? ($stripeInvoice->amount_paid / 100)
                    : ($stripeInvoice->total / 100);
                
                $invoices[] = [
                    'id' => $stripeInvoice->id,
                    'number' => $stripeInvoice->number,
                    'date' => \Carbon\Carbon::createFromTimestamp($stripeInvoice->created)->toIso8601String(),
                    'amount' => $amount,
                    'currency' => strtoupper($stripeInvoice->currency),
                    'status' => $stripeInvoice->status,
                    'download_url' => $stripeInvoice->invoice_pdf ?? null,
                    'hosted_invoice_url' => $stripeInvoice->hosted_invoice_url ?? null,
                ];
            }

            return ['data' => $invoices];
        } catch (ApiErrorException $e) {
            Log::error('Failed to fetch invoices from Stripe', [
                'user_id' => $user->id,
                'stripe_customer_id' => $stripeCustomerId,
                'error' => $e->getMessage(),
            ]);

            return ['data' => []];
        }
    }
}

