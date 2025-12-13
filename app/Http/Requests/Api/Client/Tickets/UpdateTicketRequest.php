<?php

namespace Pterodactyl\Http\Requests\Api\Client\Tickets;

use Pterodactyl\Models\Ticket;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends ClientApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in([
                Ticket::STATUS_OPEN,
                Ticket::STATUS_IN_PROGRESS,
                Ticket::STATUS_RESOLVED,
                Ticket::STATUS_CLOSED,
            ])],
            'priority' => ['sometimes', 'string', Rule::in([
                Ticket::PRIORITY_LOW,
                Ticket::PRIORITY_MEDIUM,
                Ticket::PRIORITY_HIGH,
                Ticket::PRIORITY_URGENT,
            ])],
        ];
    }
}
