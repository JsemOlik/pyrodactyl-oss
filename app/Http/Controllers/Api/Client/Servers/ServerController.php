<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Traits\ProxiesDaemonController;

class ServerController extends ClientApiController
{
    use ProxiesDaemonController;

    public function __call($method, $parameters)
    {
        return $this->proxyToDaemonController('ServerController', $method, $parameters);
    }
}
