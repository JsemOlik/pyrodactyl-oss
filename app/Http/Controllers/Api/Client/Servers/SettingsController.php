<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Traits\ProxiesDaemonController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Settings\RenameServerRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Settings\ReinstallServerRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Settings\SetDockerImageRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Settings\RevertDockerImageRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Settings\SetEggRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Settings\PreviewEggRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Settings\ApplyEggChangeRequest;

class SettingsController extends ClientApiController
{
    use ProxiesDaemonController;

    public function rename(RenameServerRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('SettingsController', 'rename', func_get_args());
    }

    public function reinstall(ReinstallServerRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('SettingsController', 'reinstall', func_get_args());
    }

    public function dockerImage(SetDockerImageRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('SettingsController', 'dockerImage', func_get_args());
    }

    public function revertDockerImage(RevertDockerImageRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('SettingsController', 'revertDockerImage', func_get_args());
    }

    public function changeEgg(SetEggRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('SettingsController', 'changeEgg', func_get_args());
    }

    public function previewEggChange(PreviewEggRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('SettingsController', 'previewEggChange', func_get_args());
    }

    public function applyEggChange(ApplyEggChangeRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('SettingsController', 'applyEggChange', func_get_args());
    }

    public function getOperationStatus(Server $server, string $operationId)
    {
        return $this->proxyToDaemonController('SettingsController', 'getOperationStatus', func_get_args());
    }

    public function getServerOperations(Server $server)
    {
        return $this->proxyToDaemonController('SettingsController', 'getServerOperations', func_get_args());
    }

    public function __call($method, $parameters)
    {
        return $this->proxyToDaemonController('SettingsController', $method, $parameters);
    }
}
