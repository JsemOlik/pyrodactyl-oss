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

        // Calculate next renewal date
        $nextRenewalAt = null;
        if ($model->stripe_status === 'active' || $model->stripe_status === 'trialing') {
            try {
                \Stripe\Stripe::setApiKey(config('cashier.secret'));
                $stripeSubscription = \Stripe\Subscription::retrieve($model->stripe_id);
                $nextRenewalAt = $stripeSubscription->current_period_end ? 
                    \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end)->toIso8601String() : 
                    null;
            } catch (\Exception $e) {
                // If we can't get Stripe data, use ends_at or null
                $nextRenewalAt = $model->ends_at ? $model->ends_at->toIso8601String() : null;
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
            'ends_at' => $model->ends_at ? $model->ends_at->toIso8601String() : null,
            'trial_ends_at' => $model->trial_ends_at ? $model->trial_ends_at->toIso8601String() : null,
            'can_cancel' => in_array($model->stripe_status, ['active', 'trialing']),
            'can_resume' => $model->stripe_status === 'canceled' && $model->ends_at && $model->ends_at->isFuture(),
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

