<?php

namespace Pterodactyl\Models;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property float $amount
 * @property float $balance_before
 * @property float $balance_after
 * @property string|null $description
 * @property int|null $subscription_id
 * @property string|null $reference_id
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Pterodactyl\Models\User $user
 * @property \Pterodactyl\Models\Subscription|null $subscription
 */
class CreditTransaction extends Model
{
    /**
     * Transaction types
     */
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_DEDUCTION = 'deduction';
    public const TYPE_REFUND = 'refund';
    public const TYPE_RENEWAL = 'renewal';
    public const TYPE_ADJUSTMENT = 'adjustment';

    /**
     * The table associated with the model.
     */
    protected $table = 'credit_transactions';

    /**
     * Fields that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'subscription_id',
        'reference_id',
        'metadata',
    ];

    /**
     * Cast values to correct type.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns this transaction.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription associated with this transaction.
     */
    public function subscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Scope to filter by transaction type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get transactions for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get recent transactions.
     */
    public function scopeRecent($query, int $limit = 50)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
