<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Traits\ProxiesDaemonController;
use Pterodactyl\Http\Requests\Api\Client\GetServerRequest;

class ServerController extends ClientApiController
{
    use ProxiesDaemonController;

    public function index(GetServerRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('ServerController', 'index', func_get_args());
    }

    public function __call($method, $parameters)
    {
        return $this->proxyToDaemonController('ServerController', $method, $parameters);
    }
}
