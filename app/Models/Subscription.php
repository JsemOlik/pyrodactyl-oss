<?php

namespace Pterodactyl\Models;

use Laravel\Cashier\Subscription as CashierSubscription;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $stripe_id
 * @property string $stripe_status
 * @property string|null $stripe_price
 * @property int|null $quantity
 * @property \Illuminate\Support\Carbon|null $trial_ends_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property \Illuminate\Support\Carbon|null $next_billing_at
 * @property string|null $billing_interval
 * @property float|null $billing_amount
 * @property bool $is_credits_based
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Pterodactyl\Models\User $user
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\Server[] $servers
 * @property int|null $servers_count
 * @property \Pterodactyl\Models\Plan|null $plan
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\CreditTransaction[] $creditTransactions
 */
class Subscription extends CashierSubscription
{
    /**
     * The table associated with the model.
     */
    protected $table = 'subscriptions';

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata' => 'array',
        'ends_at' => 'datetime',
        'next_billing_at' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Get the user that owns the subscription.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all servers associated with this subscription.
     */
    public function servers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Server::class);
    }

    /**
     * Get the plan associated with this subscription.
     */
    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class, 'stripe_price', 'stripe_price_id');
    }

    /**
     * Get all credit transactions for this subscription.
     */
    public function creditTransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * Check if this subscription is due for billing.
     */
    public function isDueForBilling(): bool
    {
        if (!$this->is_credits_based || !$this->next_billing_at) {
            return false;
        }

        return $this->next_billing_at->isPast() && $this->stripe_status === 'active';
    }

    /**
     * Calculate the next billing date based on the billing interval.
     */
    public function calculateNextBillingDate(): ?\Carbon\Carbon
    {
        if (!$this->billing_interval) {
            return null;
        }

        $now = now();
        $months = match ($this->billing_interval) {
            'month' => 1,
            'quarter' => 3,
            'half-year' => 6,
            'year' => 12,
            default => 1,
        };

        return $now->copy()->addMonths($months);
    }

    /**
     * Get the monthly price for this subscription.
     *
     * @return array{monthly_price: float|null, currency: string, billing_cycle: string}
     */
    public function getMonthlyPriceInfo(): array
    {
        // If we have a plan, use it
        if ($this->plan) {
            $plan = $this->plan;
            $intervalMonths = match ($plan->interval) {
                'month' => 1,
                'quarter' => 3,
                'half-year' => 6,
                'year' => 12,
                default => 1,
            };
            $monthlyPrice = $plan->price / $intervalMonths;

            $billingCycle = match ($plan->interval) {
                'month' => 'Monthly',
                'quarter' => 'Quarterly',
                'half-year' => 'Half-Yearly',
                'year' => 'Yearly',
                default => $plan->interval,
            };

            return [
                'monthly_price' => (float) $monthlyPrice,
                'currency' => strtoupper($plan->currency),
                'billing_cycle' => $billingCycle,
            ];
        }

        // If no plan but we have stripe_price, try to get from Stripe
        if ($this->stripe_price) {
            try {
                \Stripe\Stripe::setApiKey(config('cashier.secret'));
                $stripePrice = \Stripe\Price::retrieve($this->stripe_price);
                $priceAmount = $stripePrice->unit_amount / 100;
                $currency = strtoupper($stripePrice->currency);

                // Determine interval from Stripe price
                $interval = 'month';
                $monthlyPrice = $priceAmount;

                if ($stripePrice->recurring) {
                    $stripeInterval = $stripePrice->recurring->interval;
                    $intervalCount = $stripePrice->recurring->interval_count ?? 1;

                    if ($stripeInterval === 'month' && $intervalCount === 1) {
                        $interval = 'month';
                        $monthlyPrice = $priceAmount;
                    } elseif ($stripeInterval === 'month' && $intervalCount === 3) {
                        $interval = 'quarter';
                        $monthlyPrice = $priceAmount / 3;
                    } elseif ($stripeInterval === 'month' && $intervalCount === 6) {
                        $interval = 'half-year';
                        $monthlyPrice = $priceAmount / 6;
                    } elseif ($stripeInterval === 'year' && $intervalCount === 1) {
                        $interval = 'year';
                        $monthlyPrice = $priceAmount / 12;
                    }

                    $billingCycle = match ($interval) {
                        'month' => 'Monthly',
                        'quarter' => 'Quarterly',
                        'half-year' => 'Half-Yearly',
                        'year' => 'Yearly',
                        default => $interval,
                    };
                } else {
                    $billingCycle = 'One-time';
                }

                return [
                    'monthly_price' => (float) $monthlyPrice,
                    'currency' => $currency,
                    'billing_cycle' => $billingCycle,
                ];
            } catch (\Exception $e) {
                // Fallback if Stripe API call fails
                return [
                    'monthly_price' => null,
                    'currency' => 'USD',
                    'billing_cycle' => 'Unknown',
                ];
            }
        }

        // No subscription info available
        return [
            'monthly_price' => null,
            'currency' => 'USD',
            'billing_cycle' => 'N/A',
        ];
    }

    /**
     * Get subscription status and next billing date.
     *
     * @return array{status: string, next_billing_date: \Carbon\Carbon|null, is_pending_cancellation: bool}
     */
    public function getSubscriptionStatusInfo(): array
    {
        // Map Stripe status to our billing status
        $statusMap = [
            'active' => 'active',
            'trialing' => 'active',
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'unpaid' => 'past_due',
            'incomplete' => 'incomplete',
            'incomplete_expired' => 'canceled',
            'paused' => 'paused',
        ];

        $billingStatus = $statusMap[$this->stripe_status] ?? 'incomplete';

        // Check if subscription is pending cancellation
        $isPendingCancellation = false;
        $nextBillingDate = null;

        if (in_array($this->stripe_status, ['active', 'trialing'])) {
            try {
                \Stripe\Stripe::setApiKey(config('cashier.secret'));
                $stripeSubscription = \Stripe\Subscription::retrieve($this->stripe_id);

                // Check if subscription is scheduled to cancel at period end
                if ($stripeSubscription->cancel_at_period_end ?? false) {
                    $isPendingCancellation = true;
                    $billingStatus = 'pending_cancellation';
                }

                // Get next billing date (current_period_end)
                if ($stripeSubscription->current_period_end) {
                    $nextBillingDate = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                }
            } catch (\Exception $e) {
                // If we can't check, use ends_at as fallback
                if ($this->ends_at && $this->ends_at->isFuture() && in_array($this->stripe_status, ['active', 'trialing'])) {
                    $isPendingCancellation = true;
                    $billingStatus = 'pending_cancellation';
                    $nextBillingDate = $this->ends_at;
                }
            }
        }

        return [
            'status' => $billingStatus,
            'next_billing_date' => $nextBillingDate,
            'is_pending_cancellation' => $isPendingCancellation,
        ];
    }
}
