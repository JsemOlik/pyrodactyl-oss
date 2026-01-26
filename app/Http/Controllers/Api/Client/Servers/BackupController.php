<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Traits\ProxiesDaemonController;

class BackupController extends ClientApiController
{
    use ProxiesDaemonController;

    public function __call($method, $parameters)
    {
        return $this->proxyToDaemonController('BackupController', $method, $parameters);
    }
}
