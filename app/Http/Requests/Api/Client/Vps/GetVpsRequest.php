<?php

namespace Pterodactyl\Http\Requests\Api\Client\Vps;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class GetVpsRequest extends ClientApiRequest
{
    /**
     * Determine if the user has permission to access this VPS.
     */
    public function permission(): string
    {
        return ''; // No specific permission required, just ownership check
    }

    /**
     * Rules to validate this request against.
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Authorize the request.
     */
    public function authorize(): bool
    {
        $vps = $this->route('vps');

        // Ensure the VPS belongs to the authenticated user
        return $vps && $vps->owner_id === $this->user()->id;
    }
}

