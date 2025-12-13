<?php

namespace Pterodactyl\Http\Requests\Admin\Ticket;

use Pterodactyl\Http\Requests\Admin\AdminFormRequest;

class StoreTicketReplyRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
            'is_internal' => ['sometimes', 'boolean'],
        ];
    }
}
