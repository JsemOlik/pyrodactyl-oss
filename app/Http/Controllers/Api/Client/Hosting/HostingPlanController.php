<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Hosting;

use Illuminate\Http\Request;
use Pterodactyl\Models\Plan;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Container\Container;
use Pterodactyl\Extensions\Spatie\Fractalistic\Fractal;

class HostingPlanController extends Controller
{
    protected Fractal $fractal;

    public function __construct()
    {
        Container::getInstance()->call([$this, 'loadDependencies']);
    }

    public function loadDependencies(Fractal $fractal)
    {
        $this->fractal = $fractal;
    }

    /**
     * Get all active hosting plans (predefined plans only).
     */
    public function index(): array
    {
        $plans = Plan::query()
            ->active()
            ->predefined()
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();

        $transformed = $plans->map(function (Plan $plan) {
            return [
                'object' => 'plan',
                'attributes' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'price' => (float) $plan->price,
                    'currency' => $plan->currency,
                    'interval' => $plan->interval,
                    'memory' => $plan->memory,
                    'disk' => $plan->disk,
                    'cpu' => $plan->cpu,
                    'io' => $plan->io,
                    'swap' => $plan->swap,
                    'is_custom' => $plan->is_custom,
                    'sort_order' => $plan->sort_order,
                    'pricing' => [
                        'monthly' => $plan->getPriceForInterval('month'),
                        'quarterly' => $plan->getPriceForInterval('quarter'),
                        'half_year' => $plan->getPriceForInterval('half-year'),
                        'yearly' => $plan->getPriceForInterval('year'),
                    ],
                    'created_at' => $plan->created_at->toIso8601String(),
                    'updated_at' => $plan->updated_at->toIso8601String(),
                ],
            ];
        });

        return [
            'object' => 'list',
            'data' => $transformed->toArray(),
        ];
    }

    /**
     * Calculate price for a custom plan based on memory.
     */
    public function calculateCustomPlan(Request $request): array
    {
        $request->validate([
            'memory' => 'required|integer|min:512|max:32768', // 512MB to 32GB
            'interval' => 'sometimes|string|in:month,quarter,half-year,year',
        ]);

        $memory = (int) $request->input('memory');
        $interval = $request->input('interval', 'month');

        // Calculate price based on memory (example: $0.01 per MB per month)
        // You can adjust this pricing formula based on your business needs
        $pricePerMonth = ($memory / 1024) * 10; // $10 per GB per month

        // Apply discount based on interval
        $discountMonths = match ($interval) {
            'month' => 0,
            'quarter' => 1,
            'half-year' => 2,
            'year' => 3,
            default => 0,
        };

        $intervalMonths = match ($interval) {
            'month' => 1,
            'quarter' => 3,
            'half-year' => 6,
            'year' => 12,
            default => 1,
        };

        $totalPrice = round($pricePerMonth * ($intervalMonths - $discountMonths), 2);

        return [
            'data' => [
                'memory' => $memory,
                'interval' => $interval,
                'price' => $totalPrice,
                'price_per_month' => round($pricePerMonth, 2),
                'currency' => 'USD',
                'discount_months' => $discountMonths,
            ],
        ];
    }
}
