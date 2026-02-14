<?php

namespace Pterodactyl\Console\Commands\Servers;

use Illuminate\Console\Command;
use Pterodactyl\Services\Servers\SyncPowerStateService;

class SyncPowerStateCommand extends Command
{
    protected $signature = 'servers:sync-power-state';

    protected $description = 'Sync cached server power_state from Wings/Elytra daemons.';

    public function __construct(protected SyncPowerStateService $syncPowerStateService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Syncing server power states from Wings/Elytra...');

        $this->syncPowerStateService->handle();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
