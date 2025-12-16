<?php

namespace Pterodactyl\Http\Controllers\Admin\Billing;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Plan;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PlansController extends Controller
{
    public function __construct(
        private ViewFactory $view,
        private SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * Display the plans management page.
     */
    public function index(): View
    {
        return $this->view->make('admin.billing.plans');
    }

    /**
     * Get all plans for the admin table.
     */
    public function getPlans(Request $request): JsonResponse
    {
        $type = $request->input('type', 'game-server');
        
        $plans = Plan::query()
            ->where('type', $type)
            ->orderBy('sort_order')
            ->orderBy('price')
            ->get();
        
        return response()->json([
            'object' => 'list',
            'data' => $plans->map(function (Plan $plan) {
                return [
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
                    'is_active' => $plan->is_active,
                    'is_most_popular' => $plan->is_most_popular,
                    'sort_order' => $plan->sort_order,
                    'type' => $plan->type ?? 'game-server',
                    'stripe_price_id' => $plan->stripe_price_id,
                    'created_at' => $plan->created_at->toIso8601String(),
                    'updated_at' => $plan->updated_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Create a new plan.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:191',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'sales_percentage' => 'nullable|numeric|min:0|max:100',
            'first_month_sales_percentage' => 'nullable|numeric|min:0|max:100',
            'currency' => 'required|string|size:3',
            'interval' => 'required|string|in:month,quarter,half-year,year',
            'type' => 'required|string|max:50',
            'memory' => 'nullable|integer|min:0',
            'disk' => 'nullable|integer|min:0',
            'cpu' => 'nullable|integer|min:0',
            'io' => 'nullable|integer|min:10|max:1000',
            'swap' => 'nullable|integer|min:-1',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        try {
            $plan = Plan::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'price' => $request->input('price'),
                'sales_percentage' => $request->input('sales_percentage'),
                'first_month_sales_percentage' => $request->input('first_month_sales_percentage'),
                'currency' => $request->input('currency'),
                'interval' => $request->input('interval'),
                'type' => $request->input('type'),
                'memory' => $request->input('memory'),
                'disk' => $request->input('disk'),
                'cpu' => $request->input('cpu'),
                'io' => $request->input('io'),
                'swap' => $request->input('swap'),
                'is_custom' => false,
                'is_active' => $request->input('is_active', true),
                'is_most_popular' => $request->input('is_most_popular', false),
                'sort_order' => $request->input('sort_order', 0),
            ]);

            Log::info('Admin created new plan', [
                'admin_id' => auth()->id(),
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
            ]);

            return response()->json([
                'object' => 'plan',
                'data' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => (float) $plan->price,
                    'sales_percentage' => $plan->sales_percentage ? (float) $plan->sales_percentage : null,
                    'first_month_sales_percentage' => $plan->first_month_sales_percentage ? (float) $plan->first_month_sales_percentage : null,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create plan', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'errors' => ['Failed to create plan: ' . $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Update a plan.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:191',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'sales_percentage' => 'nullable|numeric|min:0|max:100',
            'first_month_sales_percentage' => 'nullable|numeric|min:0|max:100',
            'currency' => 'sometimes|required|string|size:3',
            'interval' => 'sometimes|required|string|in:month,quarter,half-year,year',
            'memory' => 'nullable|integer|min:0',
            'disk' => 'nullable|integer|min:0',
            'cpu' => 'nullable|integer|min:0',
            'io' => 'nullable|integer|min:10|max:1000',
            'swap' => 'nullable|integer|min:-1',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        try {
            $plan->fill($request->only([
                'name',
                'description',
                'price',
                'sales_percentage',
                'first_month_sales_percentage',
                'currency',
                'interval',
                'memory',
                'disk',
                'cpu',
                'io',
                'swap',
                'is_active',
                'is_most_popular',
                'sort_order',
            ]));
            
            $plan->save();

            Log::info('Admin updated plan', [
                'admin_id' => auth()->id(),
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
            ]);

            return response()->json([
                'object' => 'plan',
                'data' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => (float) $plan->price,
                    'sales_percentage' => $plan->sales_percentage ? (float) $plan->sales_percentage : null,
                    'first_month_sales_percentage' => $plan->first_month_sales_percentage ? (float) $plan->first_month_sales_percentage : null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update plan', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'errors' => ['Failed to update plan: ' . $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Delete a plan.
     */
    public function destroy(int $id): JsonResponse
    {
        $plan = Plan::findOrFail($id);

        try {
            // Check if plan has active subscriptions
            $activeSubscriptions = $plan->subscriptions()
                ->where('stripe_status', 'active')
                ->count();

            if ($activeSubscriptions > 0) {
                return response()->json([
                    'errors' => ['Cannot delete plan with active subscriptions. Please cancel or transfer all subscriptions first.'],
                ], 422);
            }

            $planName = $plan->name;
            $plan->delete();

            Log::info('Admin deleted plan', [
                'admin_id' => auth()->id(),
                'plan_id' => $id,
                'plan_name' => $planName,
            ]);

            return response()->json([
                'object' => 'plan',
                'data' => [
                    'id' => $id,
                    'message' => 'Plan deleted successfully.',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete plan', [
                'plan_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'errors' => ['Failed to delete plan: ' . $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Get all plan categories.
     */
    public function getCategories(): JsonResponse
    {
        $categories = $this->settings->get('settings::billing:plan_categories', json_encode([
            ['name' => 'Game', 'slug' => 'game-server'],
            ['name' => 'VPS', 'slug' => 'vps'],
        ]));
        
        $decoded = json_decode($categories, true);
        if (!is_array($decoded)) {
            $decoded = [
                ['name' => 'Game', 'slug' => 'game-server'],
                ['name' => 'VPS', 'slug' => 'vps'],
            ];
        }
        
        return response()->json([
            'object' => 'list',
            'data' => $decoded,
        ]);
    }

    /**
     * Create or update plan categories.
     */
    public function updateCategories(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'categories' => 'required|array',
            'categories.*.name' => 'required|string|max:50',
            'categories.*.slug' => 'required|string|max:50|regex:/^[a-z0-9-]+$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->all(),
            ], 422);
        }

        try {
            $categories = $request->input('categories');
            $this->settings->set('settings::billing:plan_categories', json_encode($categories));

            Log::info('Admin updated plan categories', [
                'admin_id' => auth()->id(),
                'categories' => $categories,
            ]);

            return response()->json([
                'object' => 'list',
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update categories', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'errors' => ['Failed to update categories: ' . $e->getMessage()],
            ], 500);
        }
    }
}
