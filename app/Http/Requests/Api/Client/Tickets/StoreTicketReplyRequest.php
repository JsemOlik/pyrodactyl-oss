<?php

namespace Pterodactyl\Http\Requests\Api\Client\Tickets;

use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;

class StoreTicketReplyRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
