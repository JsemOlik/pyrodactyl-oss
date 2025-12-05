<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Illuminate\Support\Collection;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Pterodactyl\Models\Nest;
use Pterodactyl\Repositories\Eloquent\NodeRepository;
use Pterodactyl\Repositories\Eloquent\NestRepository;
use Pterodactyl\Repositories\Eloquent\ServerRepository;
use Pterodactyl\Traits\Controllers\JavascriptInjection;
use Pterodactyl\Services\Helpers\SoftwareVersionService;
use Pterodactyl\Repositories\Eloquent\LocationRepository;
use Pterodactyl\Repositories\Eloquent\AllocationRepository;
use Illuminate\Support\Facades\DB;

class NodeViewController extends Controller
{
    use JavascriptInjection;

    /**
     * NodeViewController constructor.
     */
    public function __construct(
        private AllocationRepository $allocationRepository,
        private LocationRepository $locationRepository,
        private NestRepository $nestRepository,
        private NodeRepository $repository,
        private ServerRepository $serverRepository,
        private SoftwareVersionService $versionService,
        private ViewFactory $view,
    ) {}

    /**
     * Returns index view for a specific node on the system.
     */
    public function index(Request $request, Node $node): View
    {
        $node = $this->repository->loadLocationAndServerCount($node);

        return $this->view->make('admin.nodes.view.index', [
            'node' => $node,
            'stats' => $this->repository->getUsageStats($node),
            'version' => $this->versionService,
        ]);
    }

    /**
     * Returns the settings page for a specific node.
     */
    public function settings(Request $request, Node $node): View
    {
        return $this->view->make('admin.nodes.view.settings', [
            'node' => $node,
            'locations' => $this->locationRepository->all(),
        ]);
    }

    /**
     * Return the node configuration page for a specific node.
     */
    public function configuration(Request $request, Node $node): View
    {
        return $this->view->make('admin.nodes.view.configuration', compact('node'));
    }

    /**
     * Return the node allocation management page.
     */
    public function allocations(Request $request, Node $node): View
    {
        $node = $this->repository->loadNodeAllocations($node);
        
        // Load allocations with their restrictions
        $allocationsQuery = $node->allocations()->with(['allowedNests', 'allowedEggs']);
        
        $this->plainInject(['node' => Collection::wrap($node)->only(['id'])]);

        // Get all nests and eggs for the restriction UI
        $nests = $this->nestRepository->getWithEggs();

        $allocationsWithRestrictionsCollection = $allocationsQuery->get();
        
        switch (DB::getPdo()->getAttribute(DB::getPdo()::ATTR_DRIVER_NAME)) {
            case 'mysql':
                return $this->view->make('admin.nodes.view.allocation', [
                    'node' => $node,
                    'allocations' => Allocation::query()->where('node_id', $node->id)
                        ->groupBy('ip')
                        ->orderByRaw('INET_ATON(ip) ASC')
                        ->get(['ip']),
                    'nests' => $nests,
                    'allocationsWithRestrictions' => $allocationsWithRestrictionsCollection,
                ]);
            case 'pgsql':
                return $this->view->make('admin.nodes.view.allocation', [
                    'node' => $node,
                    'allocations' => Allocation::query()->where('node_id', $node->id)
                        ->groupBy('ip')
                        ->orderByRaw('ip::inet ASC')
                        ->get(['ip']),
                    'nests' => $nests,
                    'allocationsWithRestrictions' => $allocationsWithRestrictionsCollection,
                ]);
            default:
                return $this->view->make('admin.nodes.view.allocation', [
                    'node' => $node,
                    'allocations' => Allocation::query()->where('node_id', $node->id)
                        ->groupBy('ip')
                        ->orderBy('ip', 'ASC')
                        ->get(['ip']),
                    'nests' => $nests,
                    'allocationsWithRestrictions' => $allocationsWithRestrictionsCollection,
                ]);
        }
    }

    /**
     * Return a listing of servers that exist for this specific node.
     */
    public function servers(Request $request, Node $node): View
    {
        $this->plainInject([
            'node' => Collection::wrap($node->makeVisible(['daemon_token_id', 'daemon_token']))
                ->only(['scheme', 'fqdn', 'daemonListen', 'daemon_token_id', 'daemon_token']),
        ]);

        return $this->view->make('admin.nodes.view.servers', [
            'node' => $node,
            'servers' => $this->serverRepository->loadAllServersForNode($node->id, 25),
        ]);
    }
}
