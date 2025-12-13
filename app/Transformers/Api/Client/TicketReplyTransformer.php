<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\TicketReply;
use League\Fractal\Resource\Item;

class TicketReplyTransformer extends BaseClientTransformer
{
    /**
     * List of resources that can be included.
     */
    protected array $availableIncludes = ['user', 'ticket'];

    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return 'ticket_reply';
    }

    /**
     * Transform a TicketReply model into a representation that can be consumed by the
     * client API.
     */
    public function transform(TicketReply $model): array
    {
        return [
            'id' => $model->id,
            'ticket_id' => $model->ticket_id,
            'message' => $model->message,
            'is_internal' => $model->is_internal,
            'created_at' => $this->formatTimestamp($model->created_at),
            'updated_at' => $this->formatTimestamp($model->updated_at),
        ];
    }

    /**
     * Include the user relationship.
     */
    public function includeUser(TicketReply $model): Item
    {
        $model->loadMissing('user');

        return $this->item($model->user, $this->makeTransformer(UserTransformer::class));
    }

    /**
     * Include the ticket relationship.
     */
    public function includeTicket(TicketReply $model): Item
    {
        $model->loadMissing('ticket');

        return $this->item($model->ticket, $this->makeTransformer(TicketTransformer::class));
    }
}
