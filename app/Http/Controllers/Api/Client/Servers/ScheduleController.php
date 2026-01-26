<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Schedule;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Traits\ProxiesDaemonController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Schedules\ViewScheduleRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Schedules\StoreScheduleRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Schedules\UpdateScheduleRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Schedules\DeleteScheduleRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Schedules\TriggerScheduleRequest;

class ScheduleController extends ClientApiController
{
    use ProxiesDaemonController;

    public function index(ViewScheduleRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('ScheduleController', 'index', func_get_args());
    }

    public function store(StoreScheduleRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('ScheduleController', 'store', func_get_args());
    }

    public function view(ViewScheduleRequest $request, Server $server, Schedule $schedule)
    {
        return $this->proxyToDaemonController('ScheduleController', 'view', func_get_args());
    }

    public function update(UpdateScheduleRequest $request, Server $server, Schedule $schedule)
    {
        return $this->proxyToDaemonController('ScheduleController', 'update', func_get_args());
    }

    public function execute(TriggerScheduleRequest $request, Server $server, Schedule $schedule)
    {
        return $this->proxyToDaemonController('ScheduleController', 'execute', func_get_args());
    }

    public function delete(DeleteScheduleRequest $request, Server $server, Schedule $schedule)
    {
        return $this->proxyToDaemonController('ScheduleController', 'delete', func_get_args());
    }

    public function __call($method, $parameters)
    {
        return $this->proxyToDaemonController('ScheduleController', $method, $parameters);
    }
}
