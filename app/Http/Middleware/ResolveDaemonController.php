<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;

class ResolveDaemonController
{
    /**
     * Handle an incoming request and resolve daemon-specific controllers.
     */
    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();

        if (!$route) {
            return $next($request);
        }

        $action = $route->getAction();

        if (!isset($action['controller'])) {
            return $next($request);
        }

        $controller = $action['controller'];

        // Check if this is a daemon-specific controller reference
        if (
            str_contains($controller, 'Pterodactyl\Http\Controllers\Api\Client\Servers\\')
            && !str_contains($controller, '\\Wings\\')
            && !str_contains($controller, '\\Elytra\\')
            && $request->route('server') instanceof Server
        ) {

            $server = $request->route('server');
            $server->loadMissing('node');

            $daemonType = $server->node?->daemonType ?? 'elytra';

            // Convert daemon type to proper case
            $daemonNamespace = ucfirst($daemonType);

            // Replace the controller path with the daemon-specific one
            $newController = str_replace(
                'Pterodactyl\Http\Controllers\Api\Client\Servers\\',
                "Pterodactyl\\Http\\Controllers\\Api\\Client\\Servers\\{$daemonNamespace}\\",
                $controller
            );

            // Check if the daemon-specific controller exists
            if (class_exists($newController)) {
                $action['controller'] = $newController;
                $route->setAction($action);
            }
        }

        return $next($request);
    }
}
