<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * \Pterodactyl\Models\Vps.
 *
 * @property int $id
 * @property string $uuid
 * @property string $uuidShort
 * @property string $name
 * @property string|null $description
 * @property string|null $status
 * @property int $owner_id
 * @property int|null $subscription_id
 * @property int $memory
 * @property int $disk
 * @property int $cpu_cores
 * @property int $cpu_sockets
 * @property int|null $proxmox_vm_id
 * @property string|null $proxmox_node
 * @property string|null $proxmox_storage
 * @property string|null $ip_address
 * @property string|null $ipv6_address
 * @property string $distribution
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $installed_at
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\ActivityLog[] $activity
 * @property int|null $activity_count
 * @property User $user
 * @property \Pterodactyl\Models\Subscription|null $subscription
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Vps newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Vps newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Vps query()
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereCpuCores($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereCpuSockets($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereDistribution($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereInstalledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereIpv6Address($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereMemory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereProxmoxNode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereProxmoxStorage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereProxmoxVmId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereSubscriptionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Vps whereUuidShort($value)
 *
 * @mixin \Eloquent
 */
class Vps extends Model
{
    /** @use HasFactory<\Database\Factories\VpsFactory> */
    use HasFactory;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'vps';

    /**
     * VPS status constants.
     */
    public const STATUS_CREATING = 'creating';
    public const STATUS_CREATE_FAILED = 'create_failed';
    public const STATUS_RUNNING = 'running';
    public const STATUS_STOPPED = 'stopped';
    public const STATUS_STARTING = 'starting';
    public const STATUS_STOPPING = 'stopping';
    public const STATUS_REBOOTING = 'rebooting';
    public const STATUS_ERROR = 'error';
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * The table associated with the model.
     */
    protected $table = 'vpss';

    /**
     * Default values when creating the model.
     */
    protected $attributes = [
        'status' => self::STATUS_CREATING,
        'distribution' => 'ubuntu-server',
        'cpu_sockets' => 1,
        'installed_at' => null,
    ];

    /**
     * Fields that are not mass assignable.
     */
    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT, 'installed_at'];

    /**
     * Validation rules for the model.
     */
    public static array $validationRules = [
        'uuid' => 'required|string|size:36|unique:vpss',
        'uuidShort' => 'required|string|size:8|unique:vpss',
        'owner_id' => 'required|integer|exists:users,id',
        'name' => 'required|string|min:1|max:191',
        'description' => 'nullable|string',
        'status' => 'nullable|string',
        'memory' => 'required|integer|min:512',
        'disk' => 'required|integer|min:1024',
        'cpu_cores' => 'required|integer|min:1|max:128',
        'cpu_sockets' => 'sometimes|integer|min:1|max:8',
        'proxmox_vm_id' => 'nullable|integer|unique:vpss',
        'proxmox_node' => 'nullable|string|max:191',
        'proxmox_storage' => 'nullable|string|max:191',
        'ip_address' => 'nullable|ip',
        'ipv6_address' => 'nullable|ipv6',
        'distribution' => 'required|string|max:191',
        'subscription_id' => 'nullable|integer|exists:subscriptions,id',
    ];

    /**
     * Cast values to correct type.
     */
    protected $casts = [
        'owner_id' => 'integer',
        'subscription_id' => 'integer',
        'memory' => 'integer',
        'disk' => 'integer',
        'cpu_cores' => 'integer',
        'cpu_sockets' => 'integer',
        'proxmox_vm_id' => 'integer',
        self::CREATED_AT => 'datetime',
        self::UPDATED_AT => 'datetime',
        'installed_at' => 'datetime',
    ];

    /**
     * Check if the VPS is installed and ready.
     */
    public function isInstalled(): bool
    {
        return $this->status !== self::STATUS_CREATING && $this->status !== self::STATUS_CREATE_FAILED;
    }

    /**
     * Check if the VPS is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Check if the VPS is running.
     */
    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Check if the VPS is stopped.
     */
    public function isStopped(): bool
    {
        return $this->status === self::STATUS_STOPPED;
    }

    /**
     * Gets the user who owns the VPS.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Gets the subscription associated with this VPS.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Returns all of the activity log entries where the VPS is the subject.
     */
    public function activity(): MorphToMany
    {
        return $this->morphToMany(ActivityLog::class, 'subject', 'activity_log_subjects');
    }
}

