<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Hosting;

use Pterodactyl\Http\Controllers\Controller;

class VpsDistributionController extends Controller
{
    /**
     * Get available VPS distributions.
     * Currently only Ubuntu Server is supported.
     */
    public function index(): array
    {
        return [
            'object' => 'list',
            'data' => [
                [
                    'object' => 'distribution',
                    'attributes' => [
                        'id' => 'ubuntu-server',
                        'name' => 'Ubuntu Server',
                        'description' => 'Ubuntu Server LTS - A stable, secure, and efficient Linux distribution',
                        'version' => '22.04 LTS',
                        'is_available' => true,
                    ],
                ],
            ],
        ];
    }
}

