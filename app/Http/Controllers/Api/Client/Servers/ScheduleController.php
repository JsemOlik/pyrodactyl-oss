<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

class ScheduleController extends ClientApiController
{
    public function __call($method, $parameters)
    {
        return $this->proxyToDaemonController('ScheduleController', $method, $parameters);
    }

    private function proxyToDaemonController(string $controllerName, string $method, array $parameters)
    {
        $server = $this->getServerFromParameters($parameters);
        $server->loadMissing('node');

        $daemonType = ucfirst($server->node?->daemonType ?? 'elytra');
        $controllerClass = "Pterodactyl\\Http\\Controllers\\Api\\Client\\Servers\\{$daemonType}\\{$controllerName}";

        if (!class_exists($controllerClass)) {
            abort(500, "Controller {$controllerClass} does not exist");
        }

        $controller = app($controllerClass);
        return $controller->$method(...$parameters);
    }

    private function getServerFromParameters(array $parameters): Server
    {
        foreach ($parameters as $param) {
            if ($param instanceof Server) {
                return $param;
            }
        }

        abort(500, 'Server not found in parameters');
    }
}
