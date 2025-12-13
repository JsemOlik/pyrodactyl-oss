@extends('layouts.admin')

@section('title', 'Tickets')

@section('content-header')
    <h1>Tickets <small>Manage support tickets from users.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Tickets</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-xs-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Support Tickets</h3>
                    <div class="box-tools">
                        <form action="{{ route('admin.tickets') }}" method="GET" class="form-inline">
                            <div class="input-group input-group-sm" style="width: 200px;">
                                <select name="filter[status]" class="form-control" onchange="this.form.submit()">
                                    <option value="">All Statuses</option>
                                    <option value="open" {{ request('filter.status') === 'open' ? 'selected' : '' }}>Open</option>
                                    <option value="in_progress" {{ request('filter.status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                    <option value="resolved" {{ request('filter.status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                    <option value="closed" {{ request('filter.status') === 'closed' ? 'selected' : '' }}>Closed</option>
                                </select>
                            </div>
                            <div class="input-group input-group-sm" style="width: 150px; margin-left: 5px;">
                                <select name="filter[category]" class="form-control" onchange="this.form.submit()">
                                    <option value="">All Categories</option>
                                    <option value="billing" {{ request('filter.category') === 'billing' ? 'selected' : '' }}>Billing</option>
                                    <option value="technical" {{ request('filter.category') === 'technical' ? 'selected' : '' }}>Technical</option>
                                    <option value="general" {{ request('filter.category') === 'general' ? 'selected' : '' }}>General</option>
                                    <option value="other" {{ request('filter.category') === 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Subject</th>
                                <th>User</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Assigned To</th>
                                <th>Created</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tickets as $ticket)
                                <tr>
                                    <td><code>#{{ $ticket->id }}</code></td>
                                    <td>
                                        <a href="{{ route('admin.tickets.view', $ticket->id) }}">
                                            {{ Str::limit($ticket->subject, 50) }}
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.users.view', $ticket->user_id) }}">
                                            {{ $ticket->user->email }}
                                        </a>
                                    </td>
                                    <td>
                                        <span class="label label-info">{{ ucfirst($ticket->category) }}</span>
                                    </td>
                                    <td>
                                        @if($ticket->status === 'open')
                                            <span class="label label-success">Open</span>
                                        @elseif($ticket->status === 'in_progress')
                                            <span class="label label-warning">In Progress</span>
                                        @elseif($ticket->status === 'resolved')
                                            <span class="label label-primary">Resolved</span>
                                        @else
                                            <span class="label label-default">Closed</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($ticket->priority === 'urgent')
                                            <span class="label label-danger">Urgent</span>
                                        @elseif($ticket->priority === 'high')
                                            <span class="label label-warning">High</span>
                                        @elseif($ticket->priority === 'medium')
                                            <span class="label label-info">Medium</span>
                                        @else
                                            <span class="label label-default">Low</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($ticket->assignedTo)
                                            {{ $ticket->assignedTo->username }}
                                        @else
                                            <span class="text-muted">Unassigned</span>
                                        @endif
                                    </td>
                                    <td>{{ $ticket->created_at->format('Y-m-d H:i') }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('admin.tickets.view', $ticket->id) }}" class="btn btn-xs btn-default">
                                            <i class="fa fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">No tickets found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($tickets->hasPages())
                    <div class="box-footer">
                        {{ $tickets->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
