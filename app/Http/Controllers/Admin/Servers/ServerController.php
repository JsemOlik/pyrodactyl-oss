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

class ServerController extends Controller
{
    /**
     * ServerController constructor.
     */
    public function __construct(private ViewFactory $view)
    {
    }

    /**
     * Returns all the servers that exist on the system using a paginated result set. If
     * a query is passed along in the request it is also passed to the repository function.
     */
    public function index(Request $request): View
    {
        $baseQuery = Server::query()->with('node', 'user', 'allocation', 'subscription.plan');
        
        // Calculate statistics
        $totalServers = Server::count();
        
        // Online servers: installed (has installed_at) and not suspended, and not installing/failed
        // A server is online if it has installed_at AND is not suspended AND is not installing/failed
        // Handle NULL status values properly
        $onlineServers = Server::query()
            ->whereNotNull('installed_at')
            ->where(function ($query) {
                // Not suspended: status is NULL or status != 'suspended'
                $query->where(function ($q) {
                    $q->whereNull('status')
                      ->orWhere('status', '!=', Server::STATUS_SUSPENDED);
                })
                // Not installing/failed: status is NULL or status not in bad states
                ->where(function ($q) {
                    $q->whereNull('status')
                      ->orWhereNotIn('status', [Server::STATUS_INSTALLING, Server::STATUS_INSTALL_FAILED, Server::STATUS_REINSTALL_FAILED]);
                });
            })
            ->count();
        
        // Offline servers: suspended, not installed, or installing/failed
        $offlineServers = Server::query()
            ->where(function ($query) {
                $query->where('status', Server::STATUS_SUSPENDED)
                    ->orWhereNull('installed_at')
                    ->orWhereIn('status', [Server::STATUS_INSTALLING, Server::STATUS_INSTALL_FAILED, Server::STATUS_REINSTALL_FAILED]);
            })
            ->count();
        
        // Count active subscriptions (subscription status is active)
        $activeSubscriptions = Server::query()
            ->whereHas('subscription', function ($query) {
                $query->where('stripe_status', 'active');
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
            $baseQuery->whereNotNull('installed_at')
                ->where(function ($query) {
                    // Not suspended: status is NULL or status != 'suspended'
                    $query->where(function ($q) {
                        $q->whereNull('status')
                          ->orWhere('status', '!=', Server::STATUS_SUSPENDED);
                    })
                    // Not installing/failed: status is NULL or status not in bad states
                    ->where(function ($q) {
                        $q->whereNull('status')
                          ->orWhereNotIn('status', [Server::STATUS_INSTALLING, Server::STATUS_INSTALL_FAILED, Server::STATUS_REINSTALL_FAILED]);
                    });
                });
        } elseif ($filterType === 'offline') {
            $baseQuery->where(function ($query) {
                $query->where('status', Server::STATUS_SUSPENDED)
                    ->orWhereNull('installed_at')
                    ->orWhereIn('status', [Server::STATUS_INSTALLING, Server::STATUS_INSTALL_FAILED, Server::STATUS_REINSTALL_FAILED]);
            });
        } elseif ($filterType === 'active_subscription') {
            $baseQuery->whereHas('subscription', function ($query) {
                $query->where('stripe_status', 'active');
            });
        } elseif ($filterType === 'pending_cancellation') {
            $baseQuery->whereHas('subscription', function ($query) {
                $query->whereIn('stripe_status', ['active', 'trialing'])
                    ->whereNotNull('ends_at')
                    ->where('ends_at', '>', now());
            });
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
}
