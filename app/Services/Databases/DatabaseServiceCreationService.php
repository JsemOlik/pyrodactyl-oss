<?php

namespace Pterodactyl\Services\Databases;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Arr;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\User;
use Webmozart\Assert\Assert;
use Pterodactyl\Models\DatabaseService;
use Illuminate\Support\Collection;
use Pterodactyl\Models\Allocation;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Models\Objects\DeploymentObject;
use Pterodactyl\Repositories\Eloquent\DatabaseServiceRepository;
use Pterodactyl\Repositories\Wings\DaemonDatabaseServiceRepository;
use Pterodactyl\Services\Deployment\FindViableNodesService;
use Pterodactyl\Repositories\Eloquent\ServerVariableRepository;
use Pterodactyl\Services\Deployment\AllocationSelectionService;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Services\Servers\VariableValidatorService;

class DatabaseServiceCreationService
{
    /**
     * DatabaseServiceCreationService constructor.
     */
    public function __construct(
        private AllocationSelectionService $allocationSelectionService,
        private ConnectionInterface $connection,
        private DaemonDatabaseServiceRepository $daemonDatabaseServiceRepository,
        private FindViableNodesService $findViableNodesService,
        private DatabaseServiceRepository $repository,
        private ServerVariableRepository $serverVariableRepository,
        private VariableValidatorService $validatorService,
    ) {
    }

    /**
     * Create a database service on the Panel and trigger a request to the Daemon to begin the
     * database service creation process.
     *
     * @throws \Throwable
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableNodeException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableAllocationException
     */
    public function handle(array $data, ?DeploymentObject $deployment = null): DatabaseService
    {
        // If a deployment object has been passed we need to get the allocation
        // that the database service should use, and assign the node from that allocation.
        if ($deployment instanceof DeploymentObject) {
            $allocation = $this->configureDeployment($data, $deployment);
            $data['allocation_id'] = $allocation->id;
            $data['node_id'] = $allocation->node_id;
        }

        // Auto-configure the node based on the selected allocation
        // if no node was defined.
        if (empty($data['node_id'])) {
            Assert::false(empty($data['allocation_id']), 'Expected a non-empty allocation_id in database service creation data.');

            $data['node_id'] = Allocation::query()->findOrFail($data['allocation_id'])->node_id;
        }

        if (empty($data['nest_id'])) {
            Assert::false(empty($data['egg_id']), 'Expected a non-empty egg_id in database service creation data.');

            $data['nest_id'] = Egg::query()->findOrFail($data['egg_id'])->nest_id;
        }

        $eggVariableData = $this->validatorService
            ->setUserLevel(User::USER_LEVEL_ADMIN)
            ->handle(Arr::get($data, 'egg_id'), Arr::get($data, 'environment', []));

        // Due to the design of the Daemon, we need to persist this database service to the disk
        // before we can actually create it on the Daemon.
        //
        // If that connection fails out we will attempt to perform a cleanup by just
        // deleting the database service itself from the system.
        /** @var DatabaseService $databaseService */
        $databaseService = $this->connection->transaction(function () use ($data, $eggVariableData) {
            // Create the database service and assign any additional allocations to it.
            $databaseService = $this->createModel($data);

            $this->storeAssignedAllocations($databaseService, $data);
            $this->storeEggVariables($databaseService, $eggVariableData);

            return $databaseService;
        }, 5);

        try {
            $this->daemonDatabaseServiceRepository->setDatabaseService($databaseService)->create(
                Arr::get($data, 'start_on_completion', false) ?? false
            );
        } catch (DaemonConnectionException $exception) {
            // If creation fails, delete the database service from the database
            // Use app() to resolve to avoid circular dependency
            $deletionService = app(\Pterodactyl\Services\Databases\DatabaseServiceDeletionService::class);
            $deletionService->withForce()->handle($databaseService);

            throw $exception;
        }

        return $databaseService;
    }

    /**
     * Gets an allocation to use for automatic deployment.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableAllocationException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableNodeException
     */
    private function configureDeployment(array $data, DeploymentObject $deployment): Allocation
    {
        /** @var Collection $nodes */
        $nodes = $this->findViableNodesService->setLocations($deployment->getLocations())
            ->setDisk(Arr::get($data, 'disk'))
            ->setMemory(Arr::get($data, 'memory'))
            ->handle();

        return $this->allocationSelectionService->setDedicated($deployment->isDedicated())
            ->setNodes($nodes->pluck('id')->toArray())
            ->setPorts($deployment->getPorts())
            ->handle();
    }

    /**
     * Store the database service in the database and return the model.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    private function createModel(array $data): DatabaseService
    {
        $uuid = $this->generateUniqueUuidCombo();

        /** @var DatabaseService $model */
        $model = $this->repository->create([
            'external_id' => Arr::get($data, 'external_id'),
            'uuid' => $uuid,
            'uuidShort' => substr($uuid, 0, 8),
            'node_id' => Arr::get($data, 'node_id'),
            'name' => Arr::get($data, 'name'),
            'description' => Arr::get($data, 'description') ?? '',
            'status' => DatabaseService::STATUS_INSTALLING,
            'skip_scripts' => Arr::get($data, 'skip_scripts') ?? isset($data['skip_scripts']),
            'owner_id' => Arr::get($data, 'owner_id'),
            'database_type' => Arr::get($data, 'database_type', DatabaseService::DATABASE_TYPE_MYSQL),
            'memory' => Arr::get($data, 'memory'),
            'overhead_memory' => Arr::get($data, 'overhead_memory', 0),
            'swap' => Arr::get($data, 'swap'),
            'disk' => Arr::get($data, 'disk'),
            'io' => Arr::get($data, 'io'),
            'cpu' => Arr::get($data, 'cpu'),
            'threads' => Arr::get($data, 'threads'),
            'oom_disabled' => Arr::get($data, 'oom_disabled') ?? true,
            'exclude_from_resource_calculation' => Arr::get($data, 'exclude_from_resource_calculation') ?? false,
            'allocation_id' => Arr::get($data, 'allocation_id'),
            'nest_id' => Arr::get($data, 'nest_id'),
            'egg_id' => Arr::get($data, 'egg_id'),
            'startup' => Arr::get($data, 'startup'),
            'image' => Arr::get($data, 'image'),
            'backup_limit' => Arr::get($data, 'backup_limit'),
            'backup_storage_limit' => Arr::get($data, 'backup_storage_limit'),
        ]);

        return $model;
    }

    /**
     * Configure the allocations assigned to this database service.
     */
    private function storeAssignedAllocations(DatabaseService $databaseService, array $data): void
    {
        $records = [$data['allocation_id']];
        if (isset($data['allocation_additional']) && is_array($data['allocation_additional'])) {
            $records = array_merge($records, $data['allocation_additional']);
        }

        Allocation::query()->whereIn('id', $records)->update([
            'server_id' => $databaseService->id,
        ]);
    }

    /**
     * Process environment variables passed for this database service and store them in the database.
     */
    private function storeEggVariables(DatabaseService $databaseService, Collection $variables): void
    {
        $records = $variables->map(function ($result) use ($databaseService) {
            return [
                'server_id' => $databaseService->id,
                'variable_id' => $result->id,
                'variable_value' => $result->value ?? '',
            ];
        })->toArray();

        if (!empty($records)) {
            $this->serverVariableRepository->insert($records);
        }
    }

    /**
     * Create a unique UUID and UUID-Short combo for a database service.
     */
    private function generateUniqueUuidCombo(): string
    {
        $uuid = Uuid::uuid4()->toString();

        if (!$this->repository->isUniqueUuidCombo($uuid, substr($uuid, 0, 8))) {
            return $this->generateUniqueUuidCombo();
        }

        return $uuid;
    }
}

