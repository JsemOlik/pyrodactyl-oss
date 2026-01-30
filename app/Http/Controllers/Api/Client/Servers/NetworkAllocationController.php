<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Traits\ProxiesDaemonController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Network\GetNetworkRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Network\NewAllocationRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Network\UpdateAllocationRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Network\DeleteAllocationRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Network\SetPrimaryAllocationRequest;

class NetworkAllocationController extends ClientApiController
{
    use ProxiesDaemonController;

    public function index(GetNetworkRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('NetworkAllocationController', 'index', func_get_args());
    }

    public function update(UpdateAllocationRequest $request, Server $server, Allocation $allocation)
    {
        return $this->proxyToDaemonController('NetworkAllocationController', 'update', func_get_args());
    }

    public function setPrimary(SetPrimaryAllocationRequest $request, Server $server, Allocation $allocation)
    {
        return $this->proxyToDaemonController('NetworkAllocationController', 'setPrimary', func_get_args());
    }

    public function store(NewAllocationRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('NetworkAllocationController', 'store', func_get_args());
    }

    public function delete(DeleteAllocationRequest $request, Server $server, Allocation $allocation)
    {
        return $this->proxyToDaemonController('NetworkAllocationController', 'delete', func_get_args());
    }

    public function __call($method, $parameters)
    {
        return $this->proxyToDaemonController('NetworkAllocationController', $method, $parameters);
    }
}
