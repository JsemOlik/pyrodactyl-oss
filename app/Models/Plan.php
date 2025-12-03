<?php

namespace Pterodactyl\Models;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $stripe_price_id
 * @property float $price
 * @property string $currency
 * @property string $interval
 * @property int|null $memory
 * @property int|null $disk
 * @property int|null $cpu
 * @property int|null $io
 * @property int|null $swap
 * @property bool $is_custom
 * @property bool $is_active
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\Subscription[] $subscriptions
 */
class Plan extends Model
{
    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'plan';

    /**
     * The table associated with the model.
     */
    protected $table = 'plans';

    /**
     * Fields that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'stripe_price_id',
        'price',
        'currency',
        'interval',
        'memory',
        'disk',
        'cpu',
        'io',
        'swap',
        'is_custom',
        'is_active',
        'sort_order',
    ];

    /**
     * Cast values to correct type.
     */
    protected $casts = [
        'price' => 'decimal:2',
        'memory' => 'integer',
        'disk' => 'integer',
        'cpu' => 'integer',
        'io' => 'integer',
        'swap' => 'integer',
        'is_custom' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Rules verifying that the data being stored matches the expectations of the database.
     */
    public static array $validationRules = [
        'name' => 'required|string|max:191',
        'description' => 'nullable|string',
        'stripe_price_id' => 'nullable|string|max:191|unique:plans,stripe_price_id',
        'price' => 'required|numeric|min:0',
        'currency' => 'required|string|size:3',
        'interval' => 'required|string|in:month,quarter,half-year,year',
        'memory' => 'nullable|integer|min:0',
        'disk' => 'nullable|integer|min:0',
        'cpu' => 'nullable|integer|min:0',
        'io' => 'nullable|integer|min:10|max:1000',
        'swap' => 'nullable|integer|min:-1',
        'is_custom' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer|min:0',
    ];

    /**
     * Get all subscriptions for this plan.
     */
    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class, 'stripe_price', 'stripe_price_id');
    }

    /**
     * Scope to get only active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only predefined plans (not custom).
     */
    public function scopePredefined($query)
    {
        return $query->where('is_custom', false);
    }

    /**
     * Scope to get only custom plans.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_custom', true);
    }

    /**
     * Calculate the price for a specific billing interval with discount.
     *
     * @param string $interval
     * @return float
     */
    public function getPriceForInterval(string $interval): float
    {
        $basePrice = $this->price;
        $baseIntervalMonths = $this->getIntervalMonths($this->interval);
        $targetIntervalMonths = $this->getIntervalMonths($interval);

        // Calculate the price per month
        $pricePerMonth = $basePrice / $baseIntervalMonths;

        // Apply discount based on interval
        $discountMonths = $this->getDiscountMonths($interval);
        $totalMonths = $targetIntervalMonths - $discountMonths;

        return round($pricePerMonth * $totalMonths, 2);
    }

    /**
     * Get the number of months for an interval.
     */
    protected function getIntervalMonths(string $interval): int
    {
        return match ($interval) {
            'month' => 1,
            'quarter' => 3,
            'half-year' => 6,
            'year' => 12,
            default => 1,
        };
    }

    /**
     * Get the discount months for an interval.
     */
    protected function getDiscountMonths(string $interval): int
    {
        return match ($interval) {
            'month' => 0,
            'quarter' => 1,
            'half-year' => 2,
            'year' => 3,
            default => 0,
        };
    }
}

