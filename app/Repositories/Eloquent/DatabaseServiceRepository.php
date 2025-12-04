<?php

namespace Pterodactyl\Repositories\Eloquent;

use Pterodactyl\Models\DatabaseService;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Pterodactyl\Exceptions\Repository\RecordNotFoundException;

class DatabaseServiceRepository extends EloquentRepository
{
    /**
     * Return the model backing this repository.
     */
    public function model(): string
    {
        return DatabaseService::class;
    }

    /**
     * Load the egg relations onto the database service model.
     */
    public function loadEggRelations(DatabaseService $databaseService, bool $refresh = false): DatabaseService
    {
        if (!$databaseService->relationLoaded('egg') || $refresh) {
            $databaseService->load('egg.scriptFrom');
        }

        return $databaseService;
    }

    /**
     * Return a database service model and all variables associated with it.
     *
     * @throws RecordNotFoundException
     */
    public function findWithVariables(int $id): DatabaseService
    {
        try {
            return $this->getBuilder()->with('egg.variables', 'variables')
                ->where($this->getModel()->getKeyName(), '=', $id)
                ->firstOrFail($this->getColumns());
        } catch (ModelNotFoundException) {
            throw new RecordNotFoundException();
        }
    }

    /**
     * Return a database service by UUID.
     *
     * @throws RecordNotFoundException
     */
    public function getByUuid(string $uuid): DatabaseService
    {
        try {
            /** @var DatabaseService $model */
            $model = $this->getBuilder()
                ->with('nest', 'node')
                ->where(function (Builder $query) use ($uuid) {
                    $query->where('uuidShort', $uuid)->orWhere('uuid', $uuid);
                })
                ->firstOrFail($this->getColumns());

            return $model;
        } catch (ModelNotFoundException) {
            throw new RecordNotFoundException();
        }
    }

    /**
     * Check if a given UUID and UUID-Short string are unique to a database service.
     */
    public function isUniqueUuidCombo(string $uuid, string $short): bool
    {
        return !$this->getBuilder()->where('uuid', '=', $uuid)->orWhere('uuidShort', '=', $short)->exists();
    }

    /**
     * Returns all the database services that exist for a given node in a paginated response.
     */
    public function loadAllDatabaseServicesForNode(int $node, int $limit): LengthAwarePaginator
    {
        return $this->getBuilder()
            ->with(['user', 'nest', 'egg'])
            ->where('node_id', '=', $node)
            ->paginate($limit);
    }
}

