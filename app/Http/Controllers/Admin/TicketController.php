<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Ticket;
use Pterodactyl\Models\User;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Services\Activity\ActivityLogService;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\TicketReply;
use Pterodactyl\Http\Requests\Admin\Ticket\UpdateTicketRequest;
use Pterodactyl\Http\Requests\Admin\Ticket\AssignTicketRequest;
use Pterodactyl\Http\Requests\Admin\Ticket\StoreTicketReplyRequest;

class TicketController extends Controller
{
    public function __construct(
        protected AlertsMessageBag $alert,
        protected ActivityLogService $activity,
        protected ViewFactory $view,
    ) {
    }

    /**
     * Display ticket index page.
     */
    public function index(Request $request): View
    {
        $tickets = QueryBuilder::for(
            Ticket::query()->with(['user', 'server', 'subscription', 'assignedTo'])
        )
            ->allowedFilters([
                'status',
                'category',
                'priority',
                AllowedFilter::exact('assigned_to'),
                AllowedFilter::exact('user_id'),
            ])
            ->allowedSorts(['created_at', 'updated_at', 'status', 'priority'])
            ->defaultSort('-created_at')
            ->paginate(50);

        // Get admin users for assignment dropdown
        $admins = User::where('root_admin', true)->orderBy('username')->get();

        return $this->view->make('admin.tickets.index', [
            'tickets' => $tickets,
            'admins' => $admins,
        ]);
    }

    /**
     * Display ticket view page.
     */
    public function show(int $ticket): View
    {
        $ticketModel = Ticket::with([
            'user',
            'server',
            'subscription',
            'assignedTo',
            'resolvedBy',
            'replies.user',
        ])->findOrFail($ticket);

        // Get admin users for assignment dropdown
        $admins = User::where('root_admin', true)->orderBy('username')->get();

        return $this->view->make('admin.tickets.view', [
            'ticket' => $ticketModel,
            'admins' => $admins,
        ]);
    }

    /**
     * Update a ticket.
     */
    public function update(UpdateTicketRequest $request, int $ticket): RedirectResponse
    {
        $ticketModel = Ticket::findOrFail($ticket);

        $oldStatus = $ticketModel->status;
        $oldPriority = $ticketModel->priority;
        $oldAssignedTo = $ticketModel->assigned_to;

        $updates = [];
        if ($request->has('status')) {
            $updates['status'] = $request->input('status');
            if ($request->input('status') === Ticket::STATUS_RESOLVED && !$ticketModel->resolved_at) {
                $updates['resolved_at'] = now();
                $updates['resolved_by'] = $request->user()->id;
            }
        }
        if ($request->has('priority')) {
            $updates['priority'] = $request->input('priority');
        }
        if ($request->has('assigned_to')) {
            $updates['assigned_to'] = $request->input('assigned_to') ?: null;
        }

        $ticketModel->update($updates);

        // Log changes
        $changes = [];
        if (isset($updates['status']) && $updates['status'] !== $oldStatus) {
            $changes[] = "status changed from {$oldStatus} to {$updates['status']}";
            if ($updates['status'] === Ticket::STATUS_RESOLVED) {
                $this->activity
                    ->event('ticket:resolved')
                    ->subject($ticketModel)
                    ->description("Ticket #{$ticketModel->id} resolved by admin: {$ticketModel->subject}")
                    ->log();
            }
        }
        if (isset($updates['priority']) && $updates['priority'] !== $oldPriority) {
            $changes[] = "priority changed from {$oldPriority} to {$updates['priority']}";
        }
        if (isset($updates['assigned_to']) && $updates['assigned_to'] != $oldAssignedTo) {
            $assignedTo = $updates['assigned_to'] ? User::find($updates['assigned_to'])->username : 'Unassigned';
            $changes[] = "assigned to {$assignedTo}";
            $this->activity
                ->event('ticket:assigned')
                ->subject($ticketModel)
                ->description("Ticket #{$ticketModel->id} assigned to {$assignedTo}: {$ticketModel->subject}")
                ->log();
        }

        if (!empty($changes)) {
            $this->activity
                ->event('ticket:updated')
                ->subject($ticketModel)
                ->description("Ticket #{$ticketModel->id} updated: " . implode(', ', $changes))
                ->log();
        }

        $this->alert->success('Ticket has been updated successfully.')->flash();

        return redirect()->route('admin.tickets.view', $ticket);
    }

    /**
     * Assign a ticket to an admin.
     */
    public function assign(AssignTicketRequest $request, int $ticket): RedirectResponse
    {
        $ticketModel = Ticket::findOrFail($ticket);
        $assignedTo = $request->input('assigned_to') ? User::findOrFail($request->input('assigned_to')) : null;

        $ticketModel->update([
            'assigned_to' => $assignedTo?->id,
        ]);

        $assignedToName = $assignedTo ? $assignedTo->username : 'Unassigned';

        $this->activity
            ->event('ticket:assigned')
            ->subject($ticketModel)
            ->description("Ticket #{$ticketModel->id} assigned to {$assignedToName}: {$ticketModel->subject}")
            ->log();

        $this->alert->success("Ticket has been assigned to {$assignedToName}.")->flash();

        return redirect()->route('admin.tickets.view', $ticket);
    }

    /**
     * Resolve a ticket.
     */
    public function resolve(Request $request, int $ticket): RedirectResponse
    {
        $ticketModel = Ticket::findOrFail($ticket);

        if ($ticketModel->isResolved()) {
            $this->alert->warning('This ticket is already resolved.')->flash();
            return redirect()->route('admin.tickets.view', $ticket);
        }

        $ticketModel->markAsResolved($request->user());

        $this->activity
            ->event('ticket:resolved')
            ->subject($ticketModel)
            ->description("Ticket #{$ticketModel->id} resolved by admin: {$ticketModel->subject}")
            ->log();

        $this->alert->success('Ticket has been marked as resolved.')->flash();

        return redirect()->route('admin.tickets.view', $ticket);
    }

    /**
     * Delete a ticket.
     */
    public function destroy(int $ticket): RedirectResponse
    {
        $ticketModel = Ticket::findOrFail($ticket);
        $ticketId = $ticketModel->id;
        $ticketSubject = $ticketModel->subject;

        $ticketModel->delete();

        $this->activity
            ->event('ticket:deleted')
            ->description("Ticket #{$ticketId} deleted by admin: {$ticketSubject}")
            ->log();

        $this->alert->success('Ticket has been deleted successfully.')->flash();

        return redirect()->route('admin.tickets');
    }

    /**
     * Add a reply to a ticket.
     */
    public function storeReply(StoreTicketReplyRequest $request, int $ticket): RedirectResponse
    {
        $ticketModel = Ticket::findOrFail($ticket);

        $reply = TicketReply::create([
            'ticket_id' => $ticketModel->id,
            'user_id' => $request->user()->id,
            'message' => $request->input('message'),
            'is_internal' => $request->input('is_internal', false),
        ]);

        // If ticket was resolved, reopen it when admin replies
        if ($ticketModel->status === Ticket::STATUS_RESOLVED) {
            $ticketModel->status = Ticket::STATUS_OPEN;
            $ticketModel->resolved_at = null;
            $ticketModel->resolved_by = null;
            $ticketModel->save();
        }

        $this->activity
            ->event('ticket:reply:created')
            ->subject($ticketModel)
            ->description("Reply added to ticket #{$ticketModel->id} by admin: {$ticketModel->subject}")
            ->log();

        $this->alert->success('Reply has been added successfully.')->flash();

        return redirect()->route('admin.tickets.view', $ticket);
    }
}
