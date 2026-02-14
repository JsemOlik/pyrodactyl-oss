<?php

namespace Pterodactyl\Services\Servers;

use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Server;
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
