<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Tickets;

use Illuminate\Http\JsonResponse;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Services\Activity\ActivityLogService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Tickets\StoreTicketRequest;
use Pterodactyl\Http\Requests\Api\Client\Tickets\UpdateTicketRequest;
use Pterodactyl\Transformers\Api\Client\TicketTransformer;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class TicketController extends ClientApiController
{
    public function __construct(
        protected ActivityLogService $activity
    ) {
        parent::__construct();
    }

    /**
     * Get all tickets for the authenticated user.
     */
    public function index(): array
    {
        $user = $this->request->user();
        $transformer = $this->getTransformer(TicketTransformer::class);

        $tickets = QueryBuilder::for(
            Ticket::query()->where('user_id', $user->id)
                ->with($this->getIncludesForTransformer($transformer, ['user', 'server', 'subscription']))
        )
            ->allowedFilters([
                'status',
                'category',
                'priority',
                AllowedFilter::exact('server_id'),
                AllowedFilter::exact('subscription_id'),
            ])
            ->allowedSorts(['created_at', 'updated_at', 'status', 'priority'])
            ->defaultSort('-created_at')
            ->paginate(min($this->request->query('per_page', 50), 100));

        return $this->fractal->collection($tickets)
            ->transformWith($transformer)
            ->toArray();
    }

    /**
     * Create a new ticket.
     */
    public function store(StoreTicketRequest $request): array
    {
        $user = $request->user();

        $ticket = Ticket::create([
            'user_id' => $user->id,
            'subject' => $request->input('subject'),
            'description' => $request->input('description'),
            'category' => $request->input('category'),
            'priority' => $request->input('priority', Ticket::PRIORITY_MEDIUM),
            'server_id' => $request->input('server_id'),
            'subscription_id' => $request->input('subscription_id'),
            'status' => Ticket::STATUS_OPEN,
        ]);

        // Log ticket creation
        $this->activity
            ->event('ticket:created')
            ->subject($ticket)
            ->description("Ticket #{$ticket->id} created: {$ticket->subject}")
            ->log();

        $transformer = $this->getTransformer(TicketTransformer::class);
        $ticket->loadMissing($this->getIncludesForTransformer($transformer));

        return $this->fractal->item($ticket)
            ->transformWith($transformer)
            ->toArray();
    }

    /**
     * Get a single ticket.
     */
    public function show(int $ticket): array
    {
        $user = $this->request->user();
        $transformer = $this->getTransformer(TicketTransformer::class);

        // Parse includes from request and ensure replies is included
        $requestIncludes = $this->parseIncludes();
        if (!in_array('replies', $requestIncludes)) {
            $requestIncludes[] = 'replies';
        }

        // Get includes for eager loading
        $includes = $this->getIncludesForTransformer($transformer, ['user', 'server', 'subscription', 'replies']);
        if (!in_array('replies', $includes)) {
            $includes[] = 'replies';
        }

        $ticketModel = Ticket::where('id', $ticket)
            ->where('user_id', $user->id)
            ->with($includes)
            ->firstOrFail();

        // Parse includes for Fractal
        $this->fractal->parseIncludes($requestIncludes);

        return $this->fractal->item($ticketModel)
            ->transformWith($transformer)
            ->toArray();
    }

    /**
     * Update a ticket.
     */
    public function update(UpdateTicketRequest $request, int $ticket): array
    {
        $user = $request->user();
        $transformer = $this->getTransformer(TicketTransformer::class);

        $ticketModel = Ticket::where('id', $ticket)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $oldStatus = $ticketModel->status;
        $oldPriority = $ticketModel->priority;

        $updates = [];
        if ($request->has('status')) {
            $updates['status'] = $request->input('status');
        }
        if ($request->has('priority')) {
            $updates['priority'] = $request->input('priority');
        }

        $ticketModel->update($updates);

        // Log ticket update
        $changes = [];
        if (isset($updates['status']) && $updates['status'] !== $oldStatus) {
            $changes[] = "status changed from {$oldStatus} to {$updates['status']}";
        }
        if (isset($updates['priority']) && $updates['priority'] !== $oldPriority) {
            $changes[] = "priority changed from {$oldPriority} to {$updates['priority']}";
        }

        if (!empty($changes)) {
            $this->activity
                ->event('ticket:updated')
                ->subject($ticketModel)
                ->description("Ticket #{$ticketModel->id} updated: " . implode(', ', $changes))
                ->log();
        }

        $ticketModel->loadMissing($this->getIncludesForTransformer($transformer));

        return $this->fractal->item($ticketModel)
            ->transformWith($transformer)
            ->toArray();
    }

    /**
     * Delete a ticket.
     */
    public function destroy(int $ticket): JsonResponse
    {
        $user = $this->request->user();

        $ticketModel = Ticket::where('id', $ticket)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $ticketId = $ticketModel->id;
        $ticketSubject = $ticketModel->subject;

        $ticketModel->delete();

        // Log ticket deletion
        $this->activity
            ->event('ticket:deleted')
            ->description("Ticket #{$ticketId} deleted: {$ticketSubject}")
            ->log();

        return new JsonResponse([], 204);
    }

    /**
     * Mark a ticket as resolved.
     */
    public function resolve(int $ticket): array
    {
        $user = $this->request->user();
        $transformer = $this->getTransformer(TicketTransformer::class);

        $ticketModel = Ticket::where('id', $ticket)
            ->where('user_id', $user->id)
            ->firstOrFail();

        if ($ticketModel->isResolved()) {
            return response()->json([
                'errors' => [[
                    'code' => 'AlreadyResolved',
                    'status' => '400',
                    'detail' => 'This ticket is already resolved.',
                ]],
            ], 400);
        }

        $ticketModel->markAsResolved($user);

        // Log ticket resolution
        $this->activity
            ->event('ticket:resolved')
            ->subject($ticketModel)
            ->description("Ticket #{$ticketModel->id} resolved: {$ticketModel->subject}")
            ->log();

        $ticketModel->loadMissing($this->getIncludesForTransformer($transformer));

        return $this->fractal->item($ticketModel)
            ->transformWith($transformer)
            ->toArray();
    }
}
