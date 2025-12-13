<?php

namespace Pterodactyl\Http\Requests\Admin\Ticket;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class AssignTicketRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
