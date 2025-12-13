@extends('layouts.admin')

@section('title', 'Ticket #' . $ticket->id)

@section('content-header')
    <h1>Ticket #{{ $ticket->id }} <small>{{ $ticket->subject }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.tickets') }}">Tickets</a></li>
        <li class="active">#{{ $ticket->id }}</li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8">
            <!-- Ticket Information -->
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Ticket Details</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label>Subject</label>
                        <p class="form-control-static">{{ $ticket->subject }}</p>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <div class="well" style="white-space: pre-wrap;">{{ $ticket->description }}</div>
                    </div>
                    @if($ticket->server)
                        <div class="form-group">
                            <label>Related Server</label>
                            <p class="form-control-static">
                                <a href="{{ route('admin.servers.view', $ticket->server->id) }}">
                                    {{ $ticket->server->name }}
                                </a>
                            </p>
                        </div>
                    @endif
                    @if($ticket->subscription)
                        <div class="form-group">
                            <label>Related Subscription</label>
                            <p class="form-control-static">
                                Subscription #{{ $ticket->subscription->id }}
                                @if($ticket->subscription->plan)
                                    - {{ $ticket->subscription->plan->name }}
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Replies -->
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Replies</h3>
                </div>
                <div class="box-body">
                    @forelse ($ticket->replies as $reply)
                        <div class="direct-chat-msg {{ $reply->is_internal ? 'direct-chat-msg-internal' : '' }}" style="margin-bottom: 15px;">
                            <div class="direct-chat-info clearfix">
                                <span class="direct-chat-name pull-left">
                                    {{ $reply->user->username }}
                                    @if($reply->is_internal)
                                        <span class="label label-warning">Internal</span>
                                    @endif
                                </span>
                                <span class="direct-chat-timestamp pull-right">{{ $reply->created_at->format('Y-m-d H:i:s') }}</span>
                            </div>
                            <div class="direct-chat-text" style="background: {{ $reply->is_internal ? '#fff3cd' : '#d2d6de' }}; border-color: {{ $reply->is_internal ? '#ffc107' : '#d2d6de' }};">
                                {{ $reply->message }}
                            </div>
                        </div>
                    @empty
                        <p class="text-muted">No replies yet.</p>
                    @endforelse
                </div>
            </div>

            <!-- Add Reply -->
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Add Reply</h3>
                </div>
                <form action="{{ route('admin.tickets.reply', $ticket->id) }}" method="POST">
                    {!! csrf_field() !!}
                    <div class="box-body">
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea name="message" id="message" class="form-control" rows="5" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_internal" value="1">
                                Internal Note (only visible to admins)
                            </label>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">Add Reply</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Ticket Management -->
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">Manage Ticket</h3>
                </div>
                <form action="{{ route('admin.tickets.update', $ticket->id) }}" method="POST">
                    {!! csrf_field() !!}
                    {!! method_field('PATCH') !!}
                    <div class="box-body">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" id="status" class="form-control">
                                <option value="open" {{ $ticket->status === 'open' ? 'selected' : '' }}>Open</option>
                                <option value="in_progress" {{ $ticket->status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="resolved" {{ $ticket->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="closed" {{ $ticket->status === 'closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select name="priority" id="priority" class="form-control">
                                <option value="low" {{ $ticket->priority === 'low' ? 'selected' : '' }}>Low</option>
                                <option value="medium" {{ $ticket->priority === 'medium' ? 'selected' : '' }}>Medium</option>
                                <option value="high" {{ $ticket->priority === 'high' ? 'selected' : '' }}>High</option>
                                <option value="urgent" {{ $ticket->priority === 'urgent' ? 'selected' : '' }}>Urgent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="assigned_to">Assign To</label>
                            <select name="assigned_to" id="assigned_to" class="form-control">
                                <option value="">Unassigned</option>
                                @foreach($admins as $admin)
                                    <option value="{{ $admin->id }}" {{ $ticket->assigned_to === $admin->id ? 'selected' : '' }}>
                                        {{ $admin->username }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary">Update Ticket</button>
                    </div>
                </form>
            </div>

            <!-- Ticket Info -->
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Ticket Information</h3>
                </div>
                <div class="box-body">
                    <dl>
                        <dt>Created By</dt>
                        <dd>
                            <a href="{{ route('admin.users.view', $ticket->user_id) }}">
                                {{ $ticket->user->email }}
                            </a>
                        </dd>
                        <dt>Category</dt>
                        <dd><span class="label label-info">{{ ucfirst($ticket->category) }}</span></dd>
                        <dt>Created</dt>
                        <dd>{{ $ticket->created_at->format('Y-m-d H:i:s') }}</dd>
                        <dt>Last Updated</dt>
                        <dd>{{ $ticket->updated_at->format('Y-m-d H:i:s') }}</dd>
                        @if($ticket->resolved_at)
                            <dt>Resolved</dt>
                            <dd>{{ $ticket->resolved_at->format('Y-m-d H:i:s') }}</dd>
                            @if($ticket->resolvedBy)
                                <dt>Resolved By</dt>
                                <dd>{{ $ticket->resolvedBy->username }}</dd>
                            @endif
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Actions -->
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title">Actions</h3>
                </div>
                <div class="box-body">
                    @if(!$ticket->isResolved())
                        <form action="{{ route('admin.tickets.resolve', $ticket->id) }}" method="POST" style="margin-bottom: 10px;">
                            {!! csrf_field() !!}
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="fa fa-check"></i> Mark as Resolved
                            </button>
                        </form>
                    @endif
                    <form action="{{ route('admin.tickets.delete', $ticket->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this ticket?');">
                        {!! csrf_field() !!}
                        {!! method_field('DELETE') !!}
                        <button type="submit" class="btn btn-danger btn-block">
                            <i class="fa fa-trash"></i> Delete Ticket
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
