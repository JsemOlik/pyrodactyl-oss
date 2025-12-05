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
        // Running servers are those that are installed and not suspended
        $runningServers = Server::whereNotIn('status', [
            Server::STATUS_INSTALLING,
            Server::STATUS_INSTALL_FAILED,
            Server::STATUS_REINSTALL_FAILED,
            Server::STATUS_SUSPENDED,
        ])->count();
        $stoppedServers = $totalServers - $runningServers;

        // Get total node resources (without JOIN to avoid multiplication)
        $nodeTotals = DB::table('nodes')
            ->selectRaw('
                SUM(memory) as total_memory,
                SUM(disk) as total_disk
            ')
            ->first();

        // Get allocated resources from servers (separate query to avoid JOIN multiplication)
        $serverAllocations = DB::table('servers')
            ->where('exclude_from_resource_calculation', false)
            ->selectRaw('
                SUM(memory) as allocated_memory,
                SUM(disk) as allocated_disk,
                SUM(cpu) as allocated_cpu
            ')
            ->first();

        $resourceStats = (object) [
            'total_memory' => $nodeTotals->total_memory ?? 0,
            'total_disk' => $nodeTotals->total_disk ?? 0,
            'allocated_memory' => $serverAllocations->allocated_memory ?? 0,
            'allocated_disk' => $serverAllocations->allocated_disk ?? 0,
            'allocated_cpu' => $serverAllocations->allocated_cpu ?? 0,
        ];

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
                'running' => $runningServers,
                'stopped' => $stoppedServers,
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
