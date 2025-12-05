<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Pterodactyl\Models\Allocation.
 *
 * @property int $id
 * @property int $node_id
 * @property string $ip
 * @property string|null $ip_alias
 * @property int $port
 * @property int|null $server_id
 * @property string|null $notes
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property string $alias
 * @property bool $has_alias
 * @property Server|null $server
 * @property Node $node
 * @property string $hashid
 *
 * @method static \Database\Factories\AllocationFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|Allocation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Allocation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Allocation query()
 * @method static \Illuminate\Database\Eloquent\Builder|Allocation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Allocation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Allocation whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Allocation whereIpAlias($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Allocation whereNodeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Allocation whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Allocation wherePort($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Allocation whereServerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Allocation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Allocation extends Model
{
    /** @use HasFactory<\Database\Factories\AllocationFactory> */
    use HasFactory;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'allocation';

    /**
     * The table associated with the model.
     */
    protected $table = 'allocations';

    /**
     * Fields that are not mass assignable.
     */
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * Cast values to correct type.
     */
    protected $casts = [
        'node_id' => 'integer',
        'port' => 'integer',
        'server_id' => 'integer',
        'restriction_type' => 'string',
    ];

    public static array $validationRules = [
        'node_id' => 'required|exists:nodes,id',
        'ip' => 'required|ip',
        'port' => 'required|numeric|between:1024,65535',
        'ip_alias' => 'nullable|string',
        'server_id' => 'nullable|exists:servers,id',
        'notes' => 'nullable|string|max:256',
    ];

    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Return a hashid encoded string to represent the ID of the allocation.
     */
    public function getHashidAttribute(): string
    {
        return app()->make('hashids')->encode($this->id);
    }

    /**
     * Accessor to automatically provide the IP alias if defined.
     */
    public function getAliasAttribute(?string $value): string
    {
        return (is_null($this->ip_alias)) ? $this->ip : $this->ip_alias;
    }

    /**
     * Accessor to quickly determine if this allocation has an alias.
     */
    public function getHasAliasAttribute(?string $value): bool
    {
        return !is_null($this->ip_alias);
    }

    public function toString(): string
    {
        return sprintf('%s:%s', $this->ip, $this->port);
    }

    /**
     * Gets information for the server associated with this allocation.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Return the Node model associated with this allocation.
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * Gets all nests that are allowed to use this allocation.
     * If this relationship is empty, the allocation is available to all nests.
     */
    public function allowedNests(): BelongsToMany
    {
        return $this->belongsToMany(Nest::class, 'allocation_nest');
    }

    /**
     * Gets all eggs that are allowed to use this allocation.
     * If this relationship is empty, the allocation is available to all eggs.
     */
    public function allowedEggs(): BelongsToMany
    {
        return $this->belongsToMany(Egg::class, 'allocation_egg');
    }

    /**
     * Checks if this allocation is allowed for a specific nest.
     * Returns true if no restrictions are set (empty allowed_nests), or if the nest matches the restriction type.
     */
    public function isAllowedForNest(int $nestId): bool
    {
        $restrictionType = $this->restriction_type ?? 'none';
        
        if ($restrictionType === 'none') {
            return true;
        }

        if (!$this->relationLoaded('allowedNests')) {
            $this->load('allowedNests');
        }

        $restrictedNests = $this->allowedNests;
        if ($restrictedNests->isEmpty()) {
            // No specific nest restrictions, check if we're in whitelist or blacklist mode
            if ($restrictionType === 'whitelist') {
                // Whitelist with no entries = nothing allowed (shouldn't happen but be safe)
                return false;
            }
            // Blacklist with no entries = everything allowed
            return true;
        }

        $isInList = $restrictedNests->contains('id', $nestId);
        
        if ($restrictionType === 'whitelist') {
            return $isInList; // Only allowed if in whitelist
        } else { // blacklist
            return !$isInList; // Not allowed if in blacklist
        }
    }

    /**
     * Checks if this allocation is allowed for a specific egg.
     * Returns true if no restrictions are set (empty allowed_eggs), or if the egg matches the restriction type.
     */
    public function isAllowedForEgg(int $eggId): bool
    {
        $restrictionType = $this->restriction_type ?? 'none';
        
        if ($restrictionType === 'none') {
            return true;
        }

        if (!$this->relationLoaded('allowedEggs')) {
            $this->load('allowedEggs');
        }

        $restrictedEggs = $this->allowedEggs;
        if ($restrictedEggs->isEmpty()) {
            // No specific egg restrictions, check if we're in whitelist or blacklist mode
            if ($restrictionType === 'whitelist') {
                // Whitelist with no entries = nothing allowed (shouldn't happen but be safe)
                return false;
            }
            // Blacklist with no entries = everything allowed
            return true;
        }

        $isInList = $restrictedEggs->contains('id', $eggId);
        
        if ($restrictionType === 'whitelist') {
            return $isInList; // Only allowed if in whitelist
        } else { // blacklist
            return !$isInList; // Not allowed if in blacklist
        }
    }

    /**
     * Checks if this allocation is allowed for a server with the given nest and egg.
     * The allocation must be allowed for both the nest AND the egg if restrictions exist.
     */
    public function isAllowedForServer(int $nestId, int $eggId): bool
    {
        return $this->isAllowedForNest($nestId) && $this->isAllowedForEgg($eggId);
    }
}
