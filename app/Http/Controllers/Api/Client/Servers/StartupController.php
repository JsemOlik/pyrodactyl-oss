<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Traits\ProxiesDaemonController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\GetStartupRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\UpdateStartupVariableRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Startup\UpdateStartupCommandRequest;

class StartupController extends ClientApiController
{
    use ProxiesDaemonController;

    public function index(GetStartupRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('StartupController', 'index', func_get_args());
    }

    public function update(UpdateStartupVariableRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('StartupController', 'update', func_get_args());
    }

    public function updateCommand(UpdateStartupCommandRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('StartupController', 'updateCommand', func_get_args());
    }

    public function getDefaultCommand(GetStartupRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('StartupController', 'getDefaultCommand', func_get_args());
    }

    public function processCommand(GetStartupRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('StartupController', 'processCommand', func_get_args());
    }

    public function __call($method, $parameters)
    {
        return $this->proxyToDaemonController('StartupController', $method, $parameters);
    }
}
