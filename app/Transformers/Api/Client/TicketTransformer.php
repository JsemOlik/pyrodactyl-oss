<?php

namespace Pterodactyl\Transformers\Api\Client;

use Pterodactyl\Models\Ticket;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\NullResource;

class TicketTransformer extends BaseClientTransformer
{
    /**
     * List of resources that can be included.
     */
    protected array $availableIncludes = ['user', 'server', 'subscription', 'assignedTo', 'replies'];

    /**
     * Return the resource name for the JSONAPI output.
     */
    public function getResourceName(): string
    {
        return 'ticket';
    }

    /**
     * Transform a Ticket model into a representation that can be consumed by the
     * client API.
     */
    public function transform(Ticket $model): array
    {
        return [
            'id' => $model->id,
            'subject' => $model->subject,
            'description' => $model->description,
            'category' => $model->category,
            'status' => $model->status,
            'priority' => $model->priority,
            'server_id' => $model->server_id,
            'subscription_id' => $model->subscription_id,
            'assigned_to' => $model->assigned_to,
            'resolved_at' => $model->resolved_at ? $this->formatTimestamp($model->resolved_at) : null,
            'resolved_by' => $model->resolved_by,
            'created_at' => $this->formatTimestamp($model->created_at),
            'updated_at' => $this->formatTimestamp($model->updated_at),
        ];
    }

    /**
     * Include the user relationship.
     */
    public function includeUser(Ticket $model): Item
    {
        $model->loadMissing('user');

        return $this->item($model->user, $this->makeTransformer(UserTransformer::class));
    }

    /**
     * Include the server relationship.
     */
    public function includeServer(Ticket $model): Item|NullResource
    {
        $model->loadMissing('server');

        if (!$model->server) {
            return $this->null();
        }

        return $this->item($model->server, $this->makeTransformer(ServerTransformer::class));
    }

    /**
     * Include the subscription relationship.
     */
    public function includeSubscription(Ticket $model): Item|NullResource
    {
        $model->loadMissing('subscription');

        if (!$model->subscription) {
            return $this->null();
        }

        return $this->item($model->subscription, $this->makeTransformer(SubscriptionTransformer::class));
    }

    /**
     * Include the assignedTo relationship.
     */
    public function includeAssignedTo(Ticket $model): Item|NullResource
    {
        $model->loadMissing('assignedTo');

        if (!$model->assignedTo) {
            return $this->null();
        }

        return $this->item($model->assignedTo, $this->makeTransformer(UserTransformer::class));
    }

    /**
     * Include the replies relationship.
     */
    public function includeReplies(Ticket $model): Collection
    {
        $user = $this->getUser();
        
        // Use already-loaded relationship if available, otherwise load it
        if (!$model->relationLoaded('replies')) {
            if ($user->root_admin) {
                $model->loadMissing('replies');
            } else {
                $model->loadMissing('publicReplies');
                $model->setRelation('replies', $model->publicReplies);
            }
        }

        // Ensure we have replies to transform
        if (!$model->replies || $model->replies->isEmpty()) {
            return $this->collection(collect([]), $this->makeTransformer(TicketReplyTransformer::class));
        }

        return $this->collection($model->replies, $this->makeTransformer(TicketReplyTransformer::class));
    }
}
