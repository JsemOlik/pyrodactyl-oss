<?php

namespace Pterodactyl\Services\Hosting;

use Stripe\Stripe;
use Stripe\Price;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Pterodactyl\Models\Plan;

class StripePriceService
{
    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    /**
     * Get or create a Stripe Price for a Plan.
     *
     * @throws \Exception
     */
    public function getOrCreatePriceForPlan(Plan $plan): string
    {
        // If plan already has a Stripe Price ID, verify it exists and return it
        if ($plan->stripe_price_id) {
            try {
                $price = Price::retrieve($plan->stripe_price_id);
                // Verify price matches plan
                if ($this->priceMatchesPlan($price, $plan)) {
                    return $plan->stripe_price_id;
                }
                
                // Price doesn't match, create a new one
                Log::warning('Plan Stripe Price ID exists but price doesn\'t match plan', [
                    'plan_id' => $plan->id,
                    'stripe_price_id' => $plan->stripe_price_id,
                ]);
            } catch (ApiErrorException $e) {
                // Price doesn't exist in Stripe, create a new one
                Log::warning('Plan Stripe Price ID exists but price not found in Stripe', [
                    'plan_id' => $plan->id,
                    'stripe_price_id' => $plan->stripe_price_id,
                ]);
            }
        }

        // Create new Stripe Price
        $stripePrice = $this->createPriceForPlan($plan);
        
        // Update plan with Stripe Price ID
        $plan->update([
            'stripe_price_id' => $stripePrice->id,
        ]);

        return $stripePrice->id;
    }

    /**
     * Create a Stripe Price for a Plan.
     *
     * @throws \Exception
     */
    private function createPriceForPlan(Plan $plan): Price
    {
        $recurring = $this->getRecurringParams($plan->interval);
        
        try {
            $price = Price::create([
                'currency' => strtolower($plan->currency),
                'unit_amount' => (int) ($plan->price * 100), // Convert to cents
                'recurring' => $recurring,
                'product_data' => [
                    'name' => $plan->name,
                    'description' => $plan->description ?? "Server hosting plan: {$plan->name}",
                    'metadata' => [
                        'plan_id' => (string) $plan->id,
                        'memory' => (string) ($plan->memory ?? ''),
                        'disk' => (string) ($plan->disk ?? ''),
                        'cpu' => (string) ($plan->cpu ?? ''),
                    ],
                ],
            ]);

            Log::info('Created Stripe Price for plan', [
                'plan_id' => $plan->id,
                'stripe_price_id' => $price->id,
            ]);

            return $price;
        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe Price for plan', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Failed to create Stripe Price: ' . $e->getMessage());
        }
    }

    /**
     * Create a Stripe Price for a custom plan.
     *
     * @throws \Exception
     */
    public function createPriceForCustomPlan(float $priceAmount, string $interval, int $memory): string
    {
        $recurring = $this->getRecurringParams($interval);
        $currency = strtolower(config('cashier.currency', 'usd'));
        
        try {
            $price = Price::create([
                'currency' => $currency,
                'unit_amount' => (int) ($priceAmount * 100), // Convert to cents
                'recurring' => $recurring,
                'product_data' => [
                    'name' => "Custom Plan ({$memory}MB RAM)",
                    'description' => "Custom server hosting plan with {$memory}MB RAM",
                    'metadata' => [
                        'custom' => 'true',
                        'memory' => (string) $memory,
                        'interval' => $interval,
                    ],
                ],
            ]);

            Log::info('Created Stripe Price for custom plan', [
                'stripe_price_id' => $price->id,
                'memory' => $memory,
                'interval' => $interval,
                'price' => $priceAmount,
            ]);

            return $price->id;
        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe Price for custom plan', [
                'error' => $e->getMessage(),
                'memory' => $memory,
                'interval' => $interval,
            ]);

            throw new \Exception('Failed to create Stripe Price: ' . $e->getMessage());
        }
    }

    /**
     * Get recurring parameters for Stripe Price.
     */
    private function getRecurringParams(string $interval): array
    {
        $intervalMap = [
            'month' => ['interval' => 'month'],
            'quarter' => ['interval' => 'month', 'interval_count' => 3],
            'half-year' => ['interval' => 'month', 'interval_count' => 6],
            'year' => ['interval' => 'year'],
        ];

        return $intervalMap[$interval] ?? ['interval' => 'month'];
    }

    /**
     * Check if a Stripe Price matches a Plan.
     */
    private function priceMatchesPlan(Price $price, Plan $plan): bool
    {
        $expectedAmount = (int) ($plan->price * 100);
        $actualAmount = $price->unit_amount;
        
        $expectedCurrency = strtolower($plan->currency);
        $actualCurrency = strtolower($price->currency);
        
        // Check amount and currency match
        if ($expectedAmount !== $actualAmount || $expectedCurrency !== $actualCurrency) {
            return false;
        }

        // Check recurring interval matches
        $expectedInterval = $this->getRecurringParams($plan->interval);
        $actualInterval = [
            'interval' => $price->recurring->interval,
            'interval_count' => $price->recurring->interval_count ?? 1,
        ];

        return $expectedInterval === $actualInterval;
    }
}
