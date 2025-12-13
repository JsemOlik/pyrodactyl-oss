<?php

namespace Pterodactyl\Http\Requests\Admin\Ticket;

use Pterodactyl\Models\Ticket;
use Pterodactyl\Http\Requests\Admin\AdminFormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends AdminFormRequest
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
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
