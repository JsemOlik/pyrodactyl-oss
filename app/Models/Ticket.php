<?php

namespace Pterodactyl\Models;

/**
 * @property int $id
 * @property int $user_id
 * @property string $subject
 * @property string $description
 * @property string $category
 * @property string $status
 * @property string $priority
 * @property int|null $server_id
 * @property int|null $subscription_id
 * @property int|null $assigned_to
 * @property \Carbon\Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Pterodactyl\Models\User $user
 * @property \Pterodactyl\Models\Server|null $server
 * @property \Pterodactyl\Models\Subscription|null $subscription
 * @property \Pterodactyl\Models\User|null $assignedTo
 * @property \Pterodactyl\Models\User|null $resolvedBy
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\TicketReply[] $replies
 * @property int|null $replies_count
 */
class Ticket extends Model
{
    /**
     * Ticket categories
     */
    public const CATEGORY_BILLING = 'billing';
    public const CATEGORY_TECHNICAL = 'technical';
    public const CATEGORY_GENERAL = 'general';
    public const CATEGORY_OTHER = 'other';

    /**
     * Ticket statuses
     */
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    /**
     * Ticket priorities
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /**
     * The table associated with the model.
     */
    protected $table = 'tickets';

    /**
     * Fields that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'subject',
        'description',
        'category',
        'status',
        'priority',
        'server_id',
        'subscription_id',
        'assigned_to',
        'resolved_at',
        'resolved_by',
    ];

    /**
     * Cast values to correct type.
     */
    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the user that created this ticket.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the server associated with this ticket.
     */
    public function server(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get the subscription associated with this ticket.
     */
    public function subscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the admin user assigned to this ticket.
     */
    public function assignedTo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who resolved this ticket.
     */
    public function resolvedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get all replies for this ticket.
     */
    public function replies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TicketReply::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get public replies (non-internal) for this ticket.
     */
    public function publicReplies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TicketReply::class)->where('is_internal', false)->orderBy('created_at', 'asc');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeOfStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeOfCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to filter by priority.
     */
    public function scopeOfPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope to get tickets for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get tickets assigned to a specific admin.
     */
    public function scopeAssignedTo($query, int $adminId)
    {
        return $query->where('assigned_to', $adminId);
    }

    /**
     * Scope to get unassigned tickets.
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * Check if ticket is resolved.
     */
    public function isResolved(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }

    /**
     * Mark ticket as resolved.
     */
    public function markAsResolved(?User $resolvedBy = null): void
    {
        $this->status = self::STATUS_RESOLVED;
        $this->resolved_at = now();
        if ($resolvedBy) {
            $this->resolved_by = $resolvedBy->id;
        }
        $this->save();
    }
}
