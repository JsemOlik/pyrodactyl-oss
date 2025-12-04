<?php

namespace Pterodactyl\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Query\JoinClause;
use Znck\Eloquent\Traits\BelongsToThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property string|null $external_id
 * @property string $uuid
 * @property string $uuidShort
 * @property int $node_id
 * @property string $name
 * @property string $description
 * @property string|null $status
 * @property bool $skip_scripts
 * @property int $owner_id
 * @property int|null $subscription_id
 * @property string $database_type
 * @property int $memory
 * @property int $overhead_memory
 * @property int $swap
 * @property int $disk
 * @property int $io
 * @property int $cpu
 * @property string|null $threads
 * @property bool $oom_disabled
 * @property bool $exclude_from_resource_calculation
 * @property int $allocation_id
 * @property int $nest_id
 * @property int $egg_id
 * @property string $startup
 * @property string $image
 * @property int $backup_limit
 * @property int|null $backup_storage_limit
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $installed_at
 * @property Allocation|null $allocation
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\Allocation[] $allocations
 * @property int|null $allocations_count
 * @property Egg|null $egg
 * @property Nest $nest
 * @property Node $node
 * @property \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property int|null $notifications_count
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\Schedule[] $schedules
 * @property int|null $schedules_count
 * @property \Pterodactyl\Models\Subscription|null $subscription
 * @property User $user
 * @property \Illuminate\Database\Eloquent\Collection|\Pterodactyl\Models\Backup[] $backups
 * @property int|null $backups_count
 *
 * @mixin \Eloquent
 */
class DatabaseService extends Model
{
    /** @use HasFactory<\Database\Factories\DatabaseServiceFactory> */
    use HasFactory;
    use BelongsToThrough;
    use Notifiable;

    /**
     * The resource name for this model when it is transformed into an
     * API representation using fractal.
     */
    public const RESOURCE_NAME = 'database_service';

    public const STATUS_INSTALLING = 'installing';
    public const STATUS_INSTALL_FAILED = 'install_failed';
    public const STATUS_REINSTALL_FAILED = 'reinstall_failed';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_RESTORING_BACKUP = 'restoring_backup';

    public const DATABASE_TYPE_MYSQL = 'mysql';
    public const DATABASE_TYPE_MARIADB = 'mariadb';
    public const DATABASE_TYPE_POSTGRESQL = 'postgresql';
    public const DATABASE_TYPE_MONGODB = 'mongodb';

    /**
     * The table associated with the model.
     */
    protected $table = 'database_services';

    /**
     * Default values when creating the model.
     */
    protected $attributes = [
        'status' => self::STATUS_INSTALLING,
        'oom_disabled' => true,
        'exclude_from_resource_calculation' => false,
        'database_type' => self::DATABASE_TYPE_MYSQL,
        'installed_at' => null,
    ];

    /**
     * The default relationships to load for all database service models.
     */
    protected $with = ['allocation'];

    /**
     * Fields that are not mass assignable.
     */
    protected $guarded = ['id', self::CREATED_AT, self::UPDATED_AT, 'deleted_at', 'installed_at'];

    public static array $validationRules = [
        'external_id' => 'sometimes|nullable|string|between:1,191|unique:database_services',
        'owner_id' => 'required|integer|exists:users,id',
        'name' => 'required|string|min:1|max:191',
        'node_id' => 'required|exists:nodes,id',
        'description' => 'string',
        'status' => 'nullable|string',
        'database_type' => 'required|string|in:mysql,mariadb,postgresql,mongodb',
        'memory' => 'required|numeric|min:0',
        'overhead_memory' => 'sometimes|numeric|min:0',
        'swap' => 'required|numeric|min:-1',
        'io' => 'required|numeric|between:10,1000',
        'cpu' => 'required|numeric|min:0',
        'threads' => 'nullable|regex:/^[0-9-,]+$/',
        'oom_disabled' => 'sometimes|boolean',
        'exclude_from_resource_calculation' => 'sometimes|boolean',
        'disk' => 'required|numeric|min:0',
        'allocation_id' => 'required|bail|unique:database_services|exists:allocations,id',
        'nest_id' => 'required|exists:nests,id',
        'egg_id' => 'required|exists:eggs,id',
        'startup' => 'required|string',
        'skip_scripts' => 'sometimes|boolean',
        'image' => ['required', 'string', 'max:191', 'regex:/^~?[\w\.\/\-:@ ]*$/'],
        'backup_limit' => 'nullable|integer|min:0',
        'backup_storage_limit' => 'nullable|integer|min:0',
    ];

    /**
     * Cast values to correct type.
     */
    protected function casts(): array
    {
        return [
            'node_id' => 'integer',
            'skip_scripts' => 'boolean',
            'owner_id' => 'integer',
            'subscription_id' => 'integer',
            'memory' => 'integer',
            'overhead_memory' => 'integer',
            'swap' => 'integer',
            'disk' => 'integer',
            'io' => 'integer',
            'cpu' => 'integer',
            'oom_disabled' => 'boolean',
            'exclude_from_resource_calculation' => 'boolean',
            'allocation_id' => 'integer',
            'nest_id' => 'integer',
            'egg_id' => 'integer',
            'backup_limit' => 'integer',
            'backup_storage_limit' => 'integer',
            self::CREATED_AT => 'datetime',
            self::UPDATED_AT => 'datetime',
            'deleted_at' => 'datetime',
            'installed_at' => 'datetime',
        ];
    }

    /**
     * Returns the format for database service allocations when communicating with the Daemon.
     */
    public function getAllocationMappings(): array
    {
        return $this->allocations->where('node_id', $this->node_id)->groupBy('ip')->map(function ($item) {
            return $item->pluck('port');
        })->toArray();
    }

    public function isInstalled(): bool
    {
        return $this->status !== self::STATUS_INSTALLING && $this->status !== self::STATUS_INSTALL_FAILED;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Checks if the database service has a custom docker image set by an administrator.
     */
    public function hasCustomDockerImage(): bool
    {
        if (!$this->egg || !is_array($this->egg->docker_images) || empty($this->egg->docker_images)) {
            return false;
        }

        return !in_array($this->image, array_values($this->egg->docker_images));
    }

    /**
     * Gets the default docker image from the egg specification.
     */
    public function getDefaultDockerImage(): string
    {
        if (!$this->egg || !is_array($this->egg->docker_images) || empty($this->egg->docker_images)) {
            throw new \RuntimeException('Database service egg has no docker images configured.');
        }

        $eggDockerImages = $this->egg->docker_images;
        $defaultImage = reset($eggDockerImages);

        if (empty($defaultImage)) {
            throw new \RuntimeException('Database service egg has no valid default docker image.');
        }

        return $defaultImage;
    }

    /**
     * Gets the user who owns the database service.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Gets the subscription associated with this database service.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Gets the default allocation for a database service.
     */
    public function allocation(): HasOne
    {
        return $this->hasOne(Allocation::class, 'id', 'allocation_id');
    }

    /**
     * Gets all allocations associated with this database service.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class, 'server_id');
    }

    /**
     * Gets information for the nest associated with this database service.
     */
    public function nest(): BelongsTo
    {
        return $this->belongsTo(Nest::class);
    }

    /**
     * Gets information for the egg associated with this database service.
     */
    public function egg(): HasOne
    {
        return $this->hasOne(Egg::class, 'id', 'egg_id');
    }

    /**
     * Gets information for the service variables associated with this database service.
     */
    public function variables(): HasMany
    {
        return $this->hasMany(EggVariable::class, 'egg_id', 'egg_id')
            ->select(['egg_variables.*', 'server_variables.variable_value as server_value'])
            ->leftJoin('server_variables', function (JoinClause $join) {
                $join->on('server_variables.variable_id', 'egg_variables.id')
                    ->where('server_variables.server_id', $this->id);
            });
    }

    /**
     * Gets information for the node associated with this database service.
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * Gets information for the schedules associated with this database service.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Returns the location that a database service belongs to.
     *
     * @throws \Exception
     */
    public function location(): \Znck\Eloquent\Relations\BelongsToThrough
    {
        return $this->belongsToThrough(Location::class, Node::class);
    }

    /**
     * Gets backups associated with this database service.
     */
    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    /**
     * Check if this database service has a backup storage limit configured.
     */
    public function hasBackupStorageLimit(): bool
    {
        return !is_null($this->backup_storage_limit) && $this->backup_storage_limit > 0;
    }

    /**
     * Get the backup storage limit in bytes.
     */
    public function getBackupStorageLimitBytes(): int
    {
        return $this->backup_storage_limit ?? 0;
    }
}

