<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Hosting;

use Illuminate\Http\Request;
use Pterodactyl\Models\Plan;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Illuminate\Container\Container;
use Pterodactyl\Extensions\Spatie\Fractalistic\Fractal;

class HostingPlanController extends Controller
{
    protected Fractal $fractal;
    protected SettingsRepositoryInterface $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        Container::getInstance()->call([$this, 'loadDependencies']);
        $this->settings = $settings;
    }

    public function loadDependencies(Fractal $fractal)
    {
        $this->fractal = $fractal;
    }

    /**
     * Get all active hosting plans (predefined plans only).
     */
    public function index(Request $request): array
    {
        $type = $request->query('type', 'game-server'); // Default to game-server for backward compatibility
        
        $plans = Plan::query()
            ->active()
            ->predefined()
            ->where('type', $type)
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
                    'sales_percentage' => $plan->sales_percentage ? (float) $plan->sales_percentage : null,
                    'first_month_sales_percentage' => $plan->first_month_sales_percentage ? (float) $plan->first_month_sales_percentage : null,
                    'currency' => $plan->currency,
                    'interval' => $plan->interval,
                    'memory' => $plan->memory,
                    'disk' => $plan->disk,
                    'cpu' => $plan->cpu,
                    'io' => $plan->io,
                    'swap' => $plan->swap,
                    'is_custom' => $plan->is_custom,
                    'is_most_popular' => $plan->is_most_popular,
                    'sort_order' => $plan->sort_order,
                    'type' => $plan->type ?? 'game-server',
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

    /**
     * Get server creation status and disabled message if applicable.
     */
    public function serverCreationStatus(): array
    {
        $enabled = config('billing.enable_server_creation', true);
        $message = config('billing.server_creation_disabled_message', '');
        $statusPageUrl = config('billing.status_page_url', '');
        $showStatusButton = config('billing.show_status_page_button', false);
        $showLogo = config('billing.show_logo_on_disabled_page', true);

        return [
            'data' => [
                'enabled' => $enabled,
                'disabled_message' => $message,
                'status_page_url' => $statusPageUrl,
                'show_status_page_button' => $showStatusButton,
                'show_logo' => $showLogo,
            ],
        ];
    }

    /**
     * Get all plan categories.
     */
    public function getCategories(): array
    {
        $categories = $this->settings->get('settings::billing:plan_categories', json_encode([
            ['name' => 'Game', 'slug' => 'game-server'],
            ,
        ]));
        
        $decoded = json_decode($categories, true);
        if (!is_array($decoded)) {
            $decoded = [
                ['name' => 'Game', 'slug' => 'game-server'],
                ,
            ];
        }
        
        return [
            'object' => 'list',
            'data' => $decoded,
        ];
    }

    /**
     * Get billing period discounts for all categories.
     */
    public function getBillingDiscounts(): array
    {
        $discounts = $this->settings->get('settings::billing:period_discounts', json_encode([]));
        
        $decoded = json_decode($discounts, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        
        return [
            'object' => 'discounts',
            'data' => $decoded,
        ];
    }
}
