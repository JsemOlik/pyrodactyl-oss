<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Vps;

use Pterodactyl\Models\Vps;
use Pterodactyl\Services\Vps\VpsMetricsService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Vps\GetVpsRequest;

class VpsMetricsController extends ClientApiController
{
    public function __construct(
        private VpsMetricsService $metricsService
    ) {
        parent::__construct();
    }

    /**
     * Return the current resource utilization for a VPS.
     */
    public function index(GetVpsRequest $request, Vps $vps): array
    {
        $timeframe = $request->query('timeframe', 'hour');

        $metrics = $this->metricsService->getMetrics($vps, $timeframe);

        return [
            'data' => $metrics,
        ];
    }
}

