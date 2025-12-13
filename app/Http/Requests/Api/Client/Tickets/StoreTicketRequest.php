<?php

namespace Pterodactyl\Http\Requests\Api\Client\Tickets;

use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subscription;
use Pterodactyl\Http\Requests\Api\Client\ClientApiRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends ClientApiRequest
{
    public function rules(): array
    {
        $user = $this->user();

        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'category' => ['required', 'string', Rule::in([
                Ticket::CATEGORY_BILLING,
                Ticket::CATEGORY_TECHNICAL,
                Ticket::CATEGORY_GENERAL,
                Ticket::CATEGORY_OTHER,
            ])],
            'priority' => ['sometimes', 'string', Rule::in([
                Ticket::PRIORITY_LOW,
                Ticket::PRIORITY_MEDIUM,
                Ticket::PRIORITY_HIGH,
                Ticket::PRIORITY_URGENT,
            ])],
            'server_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('servers', 'id')->where(function ($query) use ($user) {
                    $query->where('owner_id', $user->id);
                }),
            ],
            'subscription_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('subscriptions', 'id')->where(function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                }),
            ],
        ];
    }
}
