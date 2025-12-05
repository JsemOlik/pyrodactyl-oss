<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Vps;

use Pterodactyl\Models\Vps;
use Pterodactyl\Models\ActivityLog;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Pterodactyl\Transformers\Api\Client\ActivityLogTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Vps\GetVpsRequest;

class VpsActivityController extends ClientApiController
{
    /**
     * Returns the activity logs for a VPS.
     */
    public function __invoke(GetVpsRequest $request, Vps $vps): array
    {
        $activity = QueryBuilder::for($vps->activity())
            ->with('actor')
            ->allowedSorts(['timestamp'])
            ->allowedFilters([AllowedFilter::partial('event')])
            ->whereNotIn('activity_logs.event', ActivityLog::DISABLED_EVENTS)
            ->paginate(min($request->query('per_page', 25), 100))
            ->appends($request->query());

        return $this->fractal->collection($activity)
            ->transformWith($this->getTransformer(ActivityLogTransformer::class))
            ->toArray();
    }
}

