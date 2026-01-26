<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Task;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Schedule;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Traits\ProxiesDaemonController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Schedules\StoreTaskRequest;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class ScheduleTaskController extends ClientApiController
{
    use ProxiesDaemonController;

    public function store(StoreTaskRequest $request, Server $server, Schedule $schedule)
    {
        return $this->proxyToDaemonController('ScheduleTaskController', 'store', func_get_args());
    }

    public function update(StoreTaskRequest $request, Server $server, Schedule $schedule, Task $task)
    {
        return $this->proxyToDaemonController('ScheduleTaskController', 'update', func_get_args());
    }

    public function delete(ClientApiRequest $request, Server $server, Schedule $schedule, Task $task)
    {
        return $this->proxyToDaemonController('ScheduleTaskController', 'delete', func_get_args());
    }

    public function __call($method, $parameters)
    {
        return $this->proxyToDaemonController('ScheduleTaskController', $method, $parameters);
    }
}
