<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerMetric;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\GetServerRequest;

class ServerMetricsController extends ClientApiController
{
    /**
     * Return historical utilization metrics for a server within a given time window.
     */
    public function index(GetServerRequest $request, Server $server): array
    {
        $timeframe = $request->query('window', '5m');

        $map = [
            '5m' => 5,
            '15m' => 15,
            '1h' => 60,
            '6h' => 360,
            '24h' => 1440,
        ];

        if (!array_key_exists($timeframe, $map)) {
            abort(422, 'Invalid window parameter.');
        }

        $minutes = $map[$timeframe];

        $to = CarbonImmutable::now();
        $from = $to->subMinutes($minutes);

        $metrics = ServerMetric::query()
            ->where('server_id', $server->id)
            ->whereBetween('timestamp', [$from, $to])
            ->orderBy('timestamp')
            ->get([
                'timestamp',
                'cpu',
                'memory_bytes',
                'network_rx_bytes',
                'network_tx_bytes',
            ]);

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'points' => $metrics->map(function (ServerMetric $metric) {
                return [
                    't' => $metric->timestamp->toIso8601String(),
                    'cpu' => $metric->cpu,
                    'memory_bytes' => $metric->memory_bytes,
                    'network_rx_bytes' => $metric->network_rx_bytes,
                    'network_tx_bytes' => $metric->network_tx_bytes,
                ];
            })->values()->all(),
        ];
    }
}
