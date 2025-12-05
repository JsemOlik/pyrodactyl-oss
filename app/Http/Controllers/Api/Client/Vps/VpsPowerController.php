<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Vps;

use Illuminate\Http\Response;
use Pterodactyl\Models\Vps;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Vps\VpsPowerService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Vps\SendPowerRequest;

class VpsPowerController extends ClientApiController
{
    public function __construct(
        private VpsPowerService $powerService
    ) {
        parent::__construct();
    }

    /**
     * Send a power action to a VPS.
     */
    public function send(SendPowerRequest $request, Vps $vps): Response
    {
        $signal = $request->input('signal');

        try {
            match ($signal) {
                'start' => $this->powerService->start($vps),
                'stop' => $this->powerService->stop($vps),
                'restart' => $this->powerService->reboot($vps),
                'kill' => $this->powerService->kill($vps),
                default => throw new \InvalidArgumentException("Invalid power signal: {$signal}"),
            };

            Activity::event(strtolower("vps:power.{$signal}"))
                ->subject($vps)
                ->log();

            return $this->returnNoContent();
        } catch (\Exception $e) {
            return response()->json([
                'errors' => [[
                    'code' => 'PowerActionFailed',
                    'status' => '500',
                    'detail' => 'Failed to execute power action: ' . $e->getMessage(),
                ]],
            ], 500);
        }
    }
}

