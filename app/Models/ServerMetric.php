<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMetric extends Model
{
    /**
     * Timestamps are not used on this model; we store an explicit timestamp column.
     */
    public $timestamps = false;

    protected $table = 'server_metrics';

    protected $fillable = [
        'server_id',
        'timestamp',
        'cpu',
        'memory_bytes',
        'network_rx_bytes',
        'network_tx_bytes',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'cpu' => 'float',
        'memory_bytes' => 'integer',
        'network_rx_bytes' => 'integer',
        'network_tx_bytes' => 'integer',
    ];
}
