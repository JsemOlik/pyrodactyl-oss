<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Database;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Traits\ProxiesDaemonController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\GetDatabasesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\StoreDatabaseRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\DeleteDatabaseRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\RotatePasswordRequest;

class DatabaseController extends ClientApiController
{
    use ProxiesDaemonController;

    public function index(GetDatabasesRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('DatabaseController', 'index', func_get_args());
    }

    public function store(StoreDatabaseRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('DatabaseController', 'store', func_get_args());
    }

    public function rotatePassword(RotatePasswordRequest $request, Server $server, Database $database)
    {
        return $this->proxyToDaemonController('DatabaseController', 'rotatePassword', func_get_args());
    }

    public function delete(DeleteDatabaseRequest $request, Server $server, Database $database)
    {
        return $this->proxyToDaemonController('DatabaseController', 'delete', func_get_args());
    }

    public function __call($method, $parameters)
    {
        return $this->proxyToDaemonController('DatabaseController', $method, $parameters);
    }
}
