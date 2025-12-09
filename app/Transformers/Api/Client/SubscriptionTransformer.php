<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\Subscription;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;

class SubscriptionTransformer extends BaseClientTransformer
{
    /**
     * List of resources that can be included.
     */
    protected array $availableIncludes = ['plan', 'servers'];

    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return 'subscription';
    }

    /**
     * Transform a Subscription model into a representation that can be consumed by the
     * client API.
     */
    public function transform(Subscription $model): array
    {
        $plan = $model->plan;
        $server = $model->servers()->first(); // Get the first server (subscriptions typically have one server)

        // Map Stripe status to our billing status
        $statusMap = [
            'active' => 'active',
            'trialing' => 'trialing',
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'unpaid' => 'past_due',
            'incomplete' => 'incomplete',
            'incomplete_expired' => 'canceled',
            'paused' => 'paused',
        ];

        $billingStatus = $statusMap[$model->stripe_status] ?? 'incomplete';
        
        // Check if subscription is pending cancellation using database fields (much faster)
        // If ends_at is set and in the future, and status is active/trialing, it's pending cancellation
        $isPendingCancellation = false;
        $endsAt = $model->ends_at instanceof \Carbon\Carbon ? $model->ends_at : ($model->ends_at ? \Carbon\Carbon::parse($model->ends_at) : null);
        if (in_array($model->stripe_status, ['active', 'trialing']) && $endsAt && $endsAt->isFuture()) {
            $isPendingCancellation = true;
            $billingStatus = 'pending_cancellation';
        }

        // Calculate next renewal date using database fields (much faster than Stripe API calls)
        $nextRenewalAt = null;
        if ($model->stripe_status === 'active' || $model->stripe_status === 'trialing') {
            // Use next_billing_at if available, otherwise use ends_at for pending cancellations
            $nextBillingAt = $model->next_billing_at instanceof \Carbon\Carbon ? $model->next_billing_at : ($model->next_billing_at ? \Carbon\Carbon::parse($model->next_billing_at) : null);
            if ($isPendingCancellation && $endsAt) {
                $nextRenewalAt = $endsAt->toIso8601String();
            } elseif ($nextBillingAt) {
                $nextRenewalAt = $nextBillingAt->toIso8601String();
            }
        }

        // Get plan price - use plan if available, otherwise calculate from Stripe
        $priceAmount = $plan ? (float) $plan->price : 0;
        $currency = $plan ? strtoupper($plan->currency) : 'USD';
        $interval = $plan ? $plan->interval : 'month';
        $planName = $plan ? $plan->name : 'Custom Plan';

        // If no plan, try to get price from Stripe
        if (!$plan && $model->stripe_price) {
            try {
                \Stripe\Stripe::setApiKey(config('cashier.secret'));
                $stripePrice = \Stripe\Price::retrieve($model->stripe_price);
                $priceAmount = $stripePrice->unit_amount / 100;
                $currency = strtoupper($stripePrice->currency);
                
                // Determine interval from Stripe price
                if ($stripePrice->recurring) {
                    $stripeInterval = $stripePrice->recurring->interval;
                    $intervalCount = $stripePrice->recurring->interval_count ?? 1;
                    
                    if ($stripeInterval === 'month' && $intervalCount === 1) {
                        $interval = 'month';
                    } elseif ($stripeInterval === 'month' && $intervalCount === 3) {
                        $interval = 'quarter';
                    } elseif ($stripeInterval === 'month' && $intervalCount === 6) {
                        $interval = 'half-year';
                    } elseif ($stripeInterval === 'year' && $intervalCount === 1) {
                        $interval = 'year';
                    } else {
                        $interval = 'month';
                    }
                }
            } catch (\Exception $e) {
                // Fallback to defaults
            }
        }

        return [
            'id' => $model->id,
            'stripe_id' => $model->stripe_id,
            'status' => $billingStatus,
            'stripe_status' => $model->stripe_status,
            'plan_name' => $planName,
            'price_amount' => $priceAmount,
            'currency' => $currency,
            'interval' => $interval,
            'server_name' => $server ? $server->name : null,
            'server_uuid' => $server ? $server->uuid : null,
            'next_renewal_at' => $nextRenewalAt,
            'ends_at' => $endsAt ? $endsAt->toIso8601String() : null,
            'trial_ends_at' => $model->trial_ends_at instanceof \Carbon\Carbon ? $model->trial_ends_at->toIso8601String() : ($model->trial_ends_at ? \Carbon\Carbon::parse($model->trial_ends_at)->toIso8601String() : null),
            'can_cancel' => in_array($model->stripe_status, ['active', 'trialing']) && !$isPendingCancellation,
            'can_resume' => $isPendingCancellation || ($model->stripe_status === 'canceled' && $endsAt && $endsAt->isFuture()),
            'created_at' => $this->formatTimestamp($model->created_at),
            'updated_at' => $this->formatTimestamp($model->updated_at),
        ];
    }

    /**
     * Include the plan relationship.
     */
    public function includePlan(Subscription $model): Item
    {
        $model->loadMissing('plan');
        
        if (!$model->plan) {
            return $this->null();
        }

        return $this->item($model->plan, $this->makeTransformer(PlanTransformer::class));
    }

    /**
     * Include the servers relationship.
     */
    public function includeServers(Subscription $model): Collection
    {
        $model->loadMissing('servers');
        
        return $this->collection($model->servers, $this->makeTransformer(ServerTransformer::class));
    }
}

