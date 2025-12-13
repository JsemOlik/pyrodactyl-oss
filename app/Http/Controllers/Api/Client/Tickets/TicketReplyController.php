<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Tickets;

use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\TicketReply;
use Pterodactyl\Services\Activity\ActivityLogService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Tickets\StoreTicketReplyRequest;
use Pterodactyl\Transformers\Api\Client\TicketReplyTransformer;

class TicketReplyController extends ClientApiController
{
    public function __construct(
        protected ActivityLogService $activity
    ) {
        parent::__construct();
    }

    /**
     * Add a reply to a ticket.
     */
    public function store(StoreTicketReplyRequest $request, int $ticket): array
    {
        $user = $request->user();

        $ticketModel = Ticket::where('id', $ticket)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Users can only reply to open or in-progress tickets
        if (!in_array($ticketModel->status, [Ticket::STATUS_OPEN, Ticket::STATUS_IN_PROGRESS])) {
            return response()->json([
                'errors' => [[
                    'code' => 'InvalidStatus',
                    'status' => '400',
                    'detail' => 'You can only reply to open or in-progress tickets.',
                ]],
            ], 400);
        }

        $reply = TicketReply::create([
            'ticket_id' => $ticketModel->id,
            'user_id' => $user->id,
            'message' => $request->input('message'),
            'is_internal' => false,
        ]);

        // If ticket was resolved, reopen it when user replies
        if ($ticketModel->status === Ticket::STATUS_RESOLVED) {
            $ticketModel->status = Ticket::STATUS_OPEN;
            $ticketModel->resolved_at = null;
            $ticketModel->resolved_by = null;
            $ticketModel->save();
        }

        // Log reply creation
        $this->activity
            ->event('ticket:reply:created')
            ->subject($ticketModel)
            ->description("Reply added to ticket #{$ticketModel->id}: {$ticketModel->subject}")
            ->log();

        $transformer = $this->getTransformer(TicketReplyTransformer::class);
        $reply->loadMissing(['user', 'ticket']);

        return $this->fractal->item($reply)
            ->transformWith($transformer)
            ->toArray();
    }
}
