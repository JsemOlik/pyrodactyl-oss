<?php

namespace Pterodactyl\Services\Servers;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerMetric;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Contracts\Repository\ServerRepositoryInterface;

class SyncPowerStateService
{
    public function __construct(
        protected DaemonServerRepository $daemonServerRepository,
        protected ServerRepositoryInterface $serverRepository,
    ) {
    }

    /**
     * Sync the cached power_state for all servers, grouped by node.
     *
     * This is intentionally conservative: if Wings/elytra is unreachable for a
     * server, we leave its cached state unchanged rather than marking it offline.
     */
    public function handle(): void
    {
        Server::query()
            ->with('node')
            ->chunkById(50, function ($servers) {
                /** @var \Pterodactyl\Models\Server $server */
                foreach ($servers as $server) {
                    try {
                        if (! $server->node) {
                            continue;
                        }

                        $this->daemonServerRepository->setServer($server)->setNode($server->node);

                        $details = $this->daemonServerRepository->getDetails();

                        // Wings/Elytra may return different shapes; normalise here.
                        $attributes = $details['attributes'] ?? $details;
                        $state = $attributes['current_state']
                            ?? $attributes['state']
                            ?? null;

                        // Fallback: derive from boolean is_running if present.
                        if ($state === null && array_key_exists('is_running', $attributes)) {
                            $state = $attributes['is_running'] ? 'running' : 'offline';
                        }

                        if ($state !== null) {
                            $this->serverRepository->withoutFreshModel()->update($server->id, [
                                'power_state' => $state,
                            ]);
                        }

                        // Persist resource metrics snapshot for this server.
                        // Best-effort: if keys are missing or the daemon payload shape changes,
                        // we simply skip writing a row for this iteration.
                        try {
                            // Wings/Elytra utilization stats are exposed under "utilization".
                            $utilization = $attributes['utilization'] ?? null;

                            if (is_array($utilization)) {
                                $cpu = $utilization['cpu_absolute'] ?? $utilization['cpu'] ?? null;

                                $memoryBytes = $utilization['memory_bytes']
                                    ?? $utilization['memory'] ?? null;

                                $network = $utilization['network'] ?? [];
                                $rxBytes = $network['rx_bytes'] ?? null;
                                $txBytes = $network['tx_bytes'] ?? null;

                                if ($cpu !== null && $memoryBytes !== null && $rxBytes !== null && $txBytes !== null) {
                                    ServerMetric::query()->create([
                                        'server_id' => $server->id,
                                        'timestamp' => now(),
                                        'cpu' => (float) $cpu,
                                        'memory_bytes' => (int) $memoryBytes,
                                        'network_rx_bytes' => (int) $rxBytes,
                                        'network_tx_bytes' => (int) $txBytes,
                                    ]);
                                }
                            }
                        } catch (\Throwable $metricEx) {
                            Log::debug('Failed persisting server metrics snapshot', [
                                'server_id' => $server->id,
                                'uuid' => $server->uuid,
                                'message' => $metricEx->getMessage(),
                            ]);
                        }
                    } catch (\Throwable $ex) {
                        Log::warning('Failed syncing power state for server', [
                            'server_id' => $server->id,
                            'uuid' => $server->uuid,
                            'message' => $ex->getMessage(),
                        ]);
                        // Intentionally swallow so one bad daemon does not kill the loop.
                        continue;
                    }
                }
            });
    }
}
