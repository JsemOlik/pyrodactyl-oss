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
                </div>
                <div class="box-body">
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i>
                        <strong>Ticket Management Coming Soon</strong>
                        <p>Full ticket management functionality is being implemented. Users can currently create tickets from the Support page.</p>
                        <p>Once complete, you'll be able to:</p>
                        <ul>
                            <li>View all tickets</li>
                            <li>Assign tickets to staff members</li>
                            <li>Update ticket status and priority</li>
                            <li>Add internal notes and replies</li>
                            <li>Resolve tickets</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
