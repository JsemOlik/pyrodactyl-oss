<?php

namespace Pterodactyl\Http\Controllers\Admin\DatabaseServices;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\DatabaseService;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Pterodactyl\Services\Databases\DatabaseServiceCreationService;
use Pterodactyl\Services\Databases\DatabaseServiceDeletionService;
use Pterodactyl\Repositories\Eloquent\DatabaseServiceRepository;
use Pterodactyl\Contracts\Repository\NestRepositoryInterface;
use Pterodactyl\Contracts\Repository\NodeRepositoryInterface;
use Pterodactyl\Contracts\Repository\LocationRepositoryInterface;
use Pterodactyl\Models\Objects\DeploymentObject;

class DatabaseServiceController extends Controller
{
    /**
     * DatabaseServiceController constructor.
     */
    public function __construct(
        private ViewFactory $view,
        private AlertsMessageBag $alert,
        private DatabaseServiceRepository $repository,
        private DatabaseServiceCreationService $creationService,
        private DatabaseServiceDeletionService $deletionService,
        private NestRepositoryInterface $nestRepository,
        private NodeRepositoryInterface $nodeRepository,
        private LocationRepositoryInterface $locationRepository,
    ) {
    }

    /**
     * Returns all the database services that exist on the system using a paginated result set.
     */
    public function index(Request $request): View
    {
        $databaseServices = QueryBuilder::for(DatabaseService::query()->with('node', 'user', 'allocation'))
            ->allowedFilters([
                AllowedFilter::exact('owner_id'),
                AllowedFilter::exact('node_id'),
                AllowedFilter::exact('database_type'),
            ])
            ->paginate(config()->get('pterodactyl.paginate.admin.servers', 50));

        return $this->view->make('admin.database-services.index', [
            'databaseServices' => $databaseServices,
        ]);
    }

    /**
     * Display the form for creating a new database service.
     */
    public function create(): View
    {
        return $this->view->make('admin.database-services.new', [
            'nests' => $this->nestRepository->all(),
            'locations' => $this->locationRepository->getAllWithNodes(),
        ]);
    }

    /**
     * Handle request to create a new database service.
     *
     * @throws \Throwable
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'owner_id' => 'required|integer|exists:users,id',
            'name' => 'required|string|min:1|max:191',
            'description' => 'nullable|string',
            'database_type' => 'required|string|in:mysql,mariadb,postgresql,mongodb',
            'node_id' => 'required|exists:nodes,id',
            'nest_id' => 'required|exists:nests,id',
            'egg_id' => 'required|exists:eggs,id',
            'memory' => 'required|integer|min:128',
            'swap' => 'required|integer|min:-1',
            'disk' => 'required|integer|min:1',
            'io' => 'required|integer|between:10,1000',
            'cpu' => 'required|integer|min:0',
            'start_on_completion' => 'sometimes|boolean',
        ]);

        try {
            $egg = \Pterodactyl\Models\Egg::findOrFail($data['egg_id']);
            $data['image'] = $egg->docker_images[array_key_first($egg->docker_images)] ?? 'mysql:8.0';
            $data['startup'] = $egg->startup ?? '';

            // Create deployment object for automatic allocation
            $deployment = new DeploymentObject();
            $deployment->setDedicated(false);
            $deployment->setLocations([]);
            $deployment->setPorts([]);

            $databaseService = $this->creationService->handle($data, $deployment);

            $this->alert->success('Successfully created a new database service.')->flash();

            return redirect()->route('admin.database-services.view', $databaseService->id);
        } catch (\Exception $exception) {
            $this->alert->danger('Failed to create database service: ' . $exception->getMessage())->flash();

            return redirect()->route('admin.database-services.new')->withInput($request->all());
        }
    }

    /**
     * Handle request to update a database service.
     */
    public function update(Request $request, DatabaseService $databaseService): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|min:1|max:191',
            'description' => 'nullable|string',
        ]);

        try {
            $databaseService->update($data);
            $this->alert->success('Database service was updated successfully.')->flash();
        } catch (\Exception $exception) {
            $this->alert->danger('Failed to update database service: ' . $exception->getMessage())->flash();
        }

        return redirect()->route('admin.database-services.view', $databaseService->id);
    }

    /**
     * Handle request to delete a database service.
     */
    public function delete(DatabaseService $databaseService): RedirectResponse
    {
        try {
            $this->deletionService->handle($databaseService);
            $this->alert->success('The requested database service has been deleted from the system.')->flash();
        } catch (\Exception $exception) {
            $this->alert->danger('Failed to delete database service: ' . $exception->getMessage())->flash();
        }

        return redirect()->route('admin.database-services');
    }
}

