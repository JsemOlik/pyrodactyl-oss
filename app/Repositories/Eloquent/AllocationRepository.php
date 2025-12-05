<?php

namespace Pterodactyl\Repositories\Eloquent;

use Pterodactyl\Models\Allocation;
use Illuminate\Database\Eloquent\Builder;
use Pterodactyl\Contracts\Repository\AllocationRepositoryInterface;

class AllocationRepository extends EloquentRepository implements AllocationRepositoryInterface
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return Allocation::class;
    }

    /**
     * Return all the allocations that exist for a node that are not currently
     * allocated.
     */
    public function getUnassignedAllocationIds(int $node): array
    {
        return Allocation::query()->select('id')
            ->whereNull('server_id')
            ->where('node_id', $node)
            ->get()
            ->pluck('id')
            ->toArray();
    }

    /**
     * Return a concatenated result set of node ips that already have at least one
     * server assigned to that IP. This allows for filtering out sets for
     * dedicated allocation IPs.
     *
     * If an array of nodes is passed the results will be limited to allocations
     * in those nodes.
     */
    protected function getDiscardableDedicatedAllocations(array $nodes = []): array
    {
        $query = Allocation::query()->selectRaw('CONCAT_WS("-", node_id, ip) as result');

        if (!empty($nodes)) {
            $query->whereIn('node_id', $nodes);
        }

        return $query->whereNotNull('server_id')
            ->groupByRaw('CONCAT(node_id, ip)')
            ->get()
            ->pluck('result')
            ->toArray();
    }

    /**
     * Return a single allocation from those meeting the requirements.
     */
    public function getRandomAllocation(array $nodes, array $ports, bool $dedicated = false, ?int $nestId = null, ?int $eggId = null): ?Allocation
    {
        $query = Allocation::query()->whereNull('server_id');

        if (!empty($nodes)) {
            $query->whereIn('node_id', $nodes);
        }

        if (!empty($ports)) {
            $query->where(function (Builder $inner) use ($ports) {
                $whereIn = [];
                foreach ($ports as $port) {
                    if (is_array($port)) {
                        $inner->orWhereBetween('port', $port);
                        continue;
                    }

                    $whereIn[] = $port;
                }

                if (!empty($whereIn)) {
                    $inner->orWhereIn('port', $whereIn);
                }
            });
        }

        // Filter by nest/egg restrictions if provided
        if ($nestId !== null || $eggId !== null) {
            $query->where(function (Builder $inner) use ($nestId, $eggId) {
                // An allocation is allowed based on restriction_type:
                // - 'none': No restrictions, always allowed
                // - 'whitelist': Only allowed if nest/egg is in the list
                // - 'blacklist': Only allowed if nest/egg is NOT in the list
                
                if ($nestId !== null) {
                    $inner->where(function (Builder $nestQuery) use ($nestId) {
                        // No restrictions
                        $nestQuery->where('restriction_type', 'none')
                            // OR whitelist and in list
                            ->orWhere(function (Builder $q) use ($nestId) {
                                $q->where('restriction_type', 'whitelist')
                                    ->whereHas('allowedNests', function (Builder $subQ) use ($nestId) {
                                        $subQ->where('nests.id', $nestId);
                                    });
                            })
                            // OR blacklist and not in list
                            ->orWhere(function (Builder $q) use ($nestId) {
                                $q->where('restriction_type', 'blacklist')
                                    ->whereDoesntHave('allowedNests', function (Builder $subQ) use ($nestId) {
                                        $subQ->where('nests.id', $nestId);
                                    });
                            });
                    });
                }

                if ($eggId !== null) {
                    $inner->where(function (Builder $eggQuery) use ($eggId) {
                        // No restrictions
                        $eggQuery->where('restriction_type', 'none')
                            // OR whitelist and in list
                            ->orWhere(function (Builder $q) use ($eggId) {
                                $q->where('restriction_type', 'whitelist')
                                    ->whereHas('allowedEggs', function (Builder $subQ) use ($eggId) {
                                        $subQ->where('eggs.id', $eggId);
                                    });
                            })
                            // OR blacklist and not in list
                            ->orWhere(function (Builder $q) use ($eggId) {
                                $q->where('restriction_type', 'blacklist')
                                    ->whereDoesntHave('allowedEggs', function (Builder $subQ) use ($eggId) {
                                        $subQ->where('eggs.id', $eggId);
                                    });
                            });
                    });
                }
            });
        }

        // If this allocation should not be shared with any other servers get
        // the data and modify the query as necessary,
        if ($dedicated) {
            $discard = $this->getDiscardableDedicatedAllocations($nodes);

            if (!empty($discard)) {
                $query->whereNotIn(
                    $this->getBuilder()->raw('CONCAT_WS("-", node_id, ip)'),
                    $discard
                );
            }
        }

        return $query->inRandomOrder()->first();
    }
}
