<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\Traits;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;

trait ProxiesDaemonController
{
    protected function proxyToDaemonController(string $controllerName, string $method, array $parameters)
    {
        // Extract request and server from parameters
        $request = null;
        $server = null;
        $otherParams = [];

        foreach ($parameters as $param) {
            if ($param instanceof Request) {
                $request = $param;
            } elseif ($param instanceof Server) {
                $server = $param;
            } else {
                $otherParams[] = $param;
            }
        }

        if (!$server) {
            abort(500, 'Server not found in parameters');
        }

        if (!$request) {
            $request = request();
        }

        $server->loadMissing('node');

        $daemonType = ucfirst($server->node?->daemonType ?? 'elytra');
        $controllerClass = "Pterodactyl\\Http\\Controllers\\Api\\Client\\Servers\\{$daemonType}\\{$controllerName}";

        if (!class_exists($controllerClass)) {
            abort(500, "Controller {$controllerClass} does not exist");
        }

        $controller = app($controllerClass);

        // Call with proper parameter order: request, server, then others
        return $controller->$method($request, $server, ...$otherParams);
    }
}
