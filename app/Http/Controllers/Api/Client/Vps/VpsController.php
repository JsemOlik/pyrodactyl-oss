<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Vps;

use Pterodactyl\Models\Vps;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Pterodactyl\Transformers\Api\Client\VpsTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Vps\GetVpsRequest;

class VpsController extends ClientApiController
{
    /**
     * Return all VPS servers available to the client making the API request.
     */
    public function index(): array
    {
        $user = $this->request->user();
        $transformer = $this->getTransformer(VpsTransformer::class);

        // Start the query builder and ensure we eager load any requested relationships from the request.
        $builder = QueryBuilder::for(
            Vps::query()->where('owner_id', $user->id)
                ->with($this->getIncludesForTransformer($transformer))
        )->allowedFilters([
            'uuid',
            'name',
            'description',
            AllowedFilter::exact('status'),
        ]);

        return $this->fractal->collection($builder->paginate(min($this->request->query('per_page', 50), 100)))
            ->transformWith($transformer)
            ->toArray();
    }

    /**
     * Transform an individual VPS into a response that can be consumed by a
     * client using the API.
     */
    public function view(GetVpsRequest $request, Vps $vps): array
    {
        return $this->fractal->item($vps)
            ->transformWith($this->getTransformer(VpsTransformer::class))
            ->addMeta([
                'is_vps_owner' => $request->user()->id === $vps->owner_id,
            ])
            ->toArray();
    }
}

