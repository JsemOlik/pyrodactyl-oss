<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Backup;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Traits\ProxiesDaemonController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Backups\StoreBackupRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Backups\RestoreBackupRequest;

class BackupController extends ClientApiController
{
    use ProxiesDaemonController;

    public function index(Request $request, Server $server)
    {
        return $this->proxyToDaemonController('BackupController', 'index', func_get_args());
    }

    public function store(StoreBackupRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('BackupController', 'store', func_get_args());
    }

    public function toggleLock(Request $request, Server $server, Backup $backup)
    {
        return $this->proxyToDaemonController('BackupController', 'toggleLock', func_get_args());
    }

    public function view(Request $request, Server $server, Backup $backup)
    {
        return $this->proxyToDaemonController('BackupController', 'view', func_get_args());
    }

    public function delete(Request $request, Server $server, Backup $backup)
    {
        return $this->proxyToDaemonController('BackupController', 'delete', func_get_args());
    }

    public function download(Request $request, Server $server, Backup $backup)
    {
        return $this->proxyToDaemonController('BackupController', 'download', func_get_args());
    }

    public function restore(RestoreBackupRequest $request, Server $server, Backup $backup)
    {
        return $this->proxyToDaemonController('BackupController', 'restore', func_get_args());
    }

    public function __call($method, $parameters)
    {
        return $this->proxyToDaemonController('BackupController', $method, $parameters);
    }
}
