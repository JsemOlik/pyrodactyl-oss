<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Helpers\SoftwareVersionService;

class BaseController extends Controller
{
    /**
     * BaseController constructor.
     */
    public function __construct(private SoftwareVersionService $version, private ViewFactory $view)
    {
    }

    /**
     * Return the admin index view.
     */
    public function index(): View
    {
        // Calculate aggregate metrics across all nodes
        $metrics = $this->getClusterMetrics();

        return $this->view->make('admin.index', [
            'version' => $this->version,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Get cluster-wide metrics for all nodes.
     */
    private function getClusterMetrics(): array
    {
        // Node statistics
        $totalNodes = Node::count();
        $nodesInMaintenance = Node::where('maintenance_mode', true)->count();
        $nodesOnline = $totalNodes - $nodesInMaintenance;

        // Server statistics
        $totalServers = Server::count();
        $suspendedServers = Server::where('status', Server::STATUS_SUSPENDED)->count();
        $installedServers = Server::whereNotIn('status', [
            Server::STATUS_INSTALLING,
            Server::STATUS_INSTALL_FAILED,
            Server::STATUS_REINSTALL_FAILED,
        ])->count();
        $runningServers = $installedServers - $suspendedServers; // Approximation

        // Aggregate resource usage across all nodes
        $resourceStats = DB::table('nodes')
            ->selectRaw('
                SUM(nodes.memory) as total_memory,
                SUM(nodes.disk) as total_disk,
                COALESCE(SUM(servers.memory), 0) as allocated_memory,
                COALESCE(SUM(servers.disk), 0) as allocated_disk,
                COALESCE(SUM(servers.cpu), 0) as allocated_cpu
            ')
            ->leftJoin('servers', function ($join) {
                $join->on('servers.node_id', '=', 'nodes.id')
                    ->where('servers.exclude_from_resource_calculation', '=', false);
            })
            ->first();

        // Calculate memory with overallocation
        $totalMemory = $resourceStats->total_memory;
        $allocatedMemory = $resourceStats->allocated_memory;
        $memoryPercent = $totalMemory > 0 ? ($allocatedMemory / $totalMemory) * 100 : 0;

        // Calculate disk with overallocation
        $totalDisk = $resourceStats->total_disk;
        $allocatedDisk = $resourceStats->allocated_disk;
        $diskPercent = $totalDisk > 0 ? ($allocatedDisk / $totalDisk) * 100 : 0;

        // CPU calculation - sum of all server CPU limits (in percentage/cores)
        // Note: Actual CPU usage percentage would require daemon API calls to each node
        $allocatedCpu = $resourceStats->allocated_cpu;
        // For display, we'll show allocated CPU as a count
        // Percentage would need to be calculated from actual node CPU usage via API
        $cpuPercent = 0; // Placeholder - would need API calls to get actual usage

        return [
            'health' => [
                'total_nodes' => $totalNodes,
                'nodes_online' => $nodesOnline,
                'nodes_offline' => $nodesInMaintenance,
            ],
            'servers' => [
                'total' => $totalServers,
                'running' => max(0, $runningServers),
                'stopped' => max(0, $totalServers - $runningServers),
            ],
            'resources' => [
                'cpu' => [
                    'allocated' => $allocatedCpu,
                    'total' => $allocatedCpu, // Show allocated as total for now
                    'percent' => $cpuPercent,
                ],
                'memory' => [
                    'allocated' => $allocatedMemory,
                    'total' => $totalMemory,
                    'percent' => $memoryPercent,
                ],
                'disk' => [
                    'allocated' => $allocatedDisk,
                    'total' => $totalDisk,
                    'percent' => $diskPercent,
                ],
            ],
        ];
    }
}
