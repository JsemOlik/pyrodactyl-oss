<?php

namespace Pterodactyl\Models;

/**
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property string $message
 * @property bool $is_internal
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Pterodactyl\Models\Ticket $ticket
 * @property \Pterodactyl\Models\User $user
 */
class TicketReply extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'ticket_replies';

    /**
     * Fields that are mass assignable.
     */
    protected $fillable = [
        'ticket_id',
        'user_id',
        'message',
        'is_internal',
    ];

    /**
     * Cast values to correct type.
     */
    protected $casts = [
        'is_internal' => 'boolean',
    ];

    /**
     * Get the ticket this reply belongs to.
     */
    public function ticket(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who created this reply.
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter public replies (non-internal).
     */
    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    /**
     * Scope to filter internal replies.
     */
    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }
}
