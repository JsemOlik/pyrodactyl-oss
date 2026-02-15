<?php

namespace Pterodactyl\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Pterodactyl\Models\ServerMetric;

class PruneServerMetricsCommand extends Command
{
    protected $signature = 'p:prune-server-metrics';

    protected $description = 'Prune stored server metrics older than 24 hours.';

    public function handle(): int
    {
        $cutoff = CarbonImmutable::now()->subDay();

        $deleted = ServerMetric::query()
            ->where('timestamp', '<', $cutoff)
            ->delete();

        $this->info(sprintf('Pruned %d server metric rows older than %s.', $deleted, $cutoff->toIso8601String()));

        return Command::SUCCESS;
    }
}
