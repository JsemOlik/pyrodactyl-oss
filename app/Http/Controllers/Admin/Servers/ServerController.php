<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Filters\AdminServerFilter;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Pterodactyl\Services\Servers\SyncPowerStateService;

class ServerController extends Controller
{
    /**
     * ServerController constructor.
     */
    public function __construct(
        private ViewFactory $view,
        private SyncPowerStateService $syncPowerStateService,
    ) {}

    /**
     * Returns all the servers that exist on the system using a paginated result set. If
     * a query is passed along in the request it is also passed to the repository function.
     */
    public function index(Request $request): View
    {
        $baseQuery = Server::query()->with('node', 'user', 'allocation', 'subscription.plan');
        
        // Calculate statistics
        $totalServers = Server::count();
        
        // Online/offline servers: prefer cached power_state from Wings/Elytra.
        // We treat "running" as online; everything else is offline for the dashboard stats.
        $onlineServers = Server::where('power_state', 'running')->count();
        
        $offlineServers = Server::where(function ($query) {
                $query->whereNull('power_state')
                    ->orWhere('power_state', '!=', 'running');
            })
            ->count();
        
        // Count active subscriptions (subscription status is active, but exclude pending cancellation)
        $activeSubscriptions = Server::query()
            ->whereHas('subscription', function ($query) {
                $query->whereIn('stripe_status', ['active', 'trialing'])
                    ->where(function ($q) {
                        // Not pending cancellation (no ends_at or ends_at is in the past)
                        $q->whereNull('ends_at')
                          ->orWhere('ends_at', '<=', now());
                    });
            })
            ->count();
        
        // Count pending cancellation subscriptions (active subscriptions with ends_at in future)
        $pendingCancellation = Server::query()
            ->whereHas('subscription', function ($query) {
                $query->whereIn('stripe_status', ['active', 'trialing'])
                    ->whereNotNull('ends_at')
                    ->where('ends_at', '>', now());
            })
            ->count();
        
        // Apply filters based on request
        $filterType = $request->input('filter_type');
        if ($filterType === 'online') {
            $baseQuery->where('power_state', 'running');
        } elseif ($filterType === 'offline') {
            $baseQuery->where(function ($query) {
                $query->whereNull('power_state')
                    ->orWhere('power_state', '!=', 'running');
            });
        } elseif ($filterType === 'active_subscription') {
            $baseQuery->whereHas('subscription', function ($query) {
                $query->whereIn('stripe_status', ['active', 'trialing'])
                    ->where(function ($q) {
                        // Not pending cancellation (no ends_at or ends_at is in the past)
                        $q->whereNull('ends_at')
                          ->orWhere('ends_at', '<=', now());
                    });
            });
        } elseif ($filterType === 'pending_cancellation') {
            $baseQuery->whereHas('subscription', function ($query) {
                $query->whereIn('stripe_status', ['active', 'trialing'])
                    ->whereNotNull('ends_at')
                    ->where('ends_at', '>', now());
            });
        } elseif ($filterType === 'limbo') {
            // Placeholder filter - doesn't filter anything for now
            // This will be implemented later
        }
        
        $servers = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                AllowedFilter::exact('owner_id'),
                AllowedFilter::custom('*', new AdminServerFilter()),
            ])
            ->paginate(config()->get('pterodactyl.paginate.admin.servers'));

        return $this->view->make('admin.servers.index', [
            'servers' => $servers,
            'stats' => [
                'total' => $totalServers,
                'online' => $onlineServers,
                'offline' => $offlineServers,
                'active_subscription' => $activeSubscriptions,
                'pending_cancellation' => $pendingCancellation,
            ],
            'current_filter' => $filterType,
        ]);
    }

    /**
     * Manually refresh cached power_state for all servers from Wings/Elytra.
     */
    public function refreshPowerStates(Request $request)
    {
        $this->syncPowerStateService->handle();

        return redirect()
            ->route('admin.servers', $request->only('filter_type', 'filter'))
            ->with('status', 'Server power states are being refreshed.');
    }
}
