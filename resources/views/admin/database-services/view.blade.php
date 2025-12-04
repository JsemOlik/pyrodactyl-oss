@extends('layouts.admin')

@section('title')
    View Database Service
@endsection

@section('content-header')
    <h1>{{ $databaseService->name }}<small>View database service details.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.database-services') }}">Database Services</a></li>
        <li class="active">{{ $databaseService->name }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Database Service Information</h3>
            </div>
            <div class="box-body">
                <dl class="dl-horizontal">
                    <dt>ID</dt>
                    <dd><code>{{ $databaseService->id }}</code></dd>
                    
                    <dt>UUID</dt>
                    <dd><code>{{ $databaseService->uuid }}</code></dd>
                    
                    <dt>UUID Short</dt>
                    <dd><code>{{ $databaseService->uuidShort }}</code></dd>
                    
                    <dt>Name</dt>
                    <dd>{{ $databaseService->name }}</dd>
                    
                    <dt>Description</dt>
                    <dd>{{ $databaseService->description ?: 'No description provided.' }}</dd>
                    
                    <dt>Database Type</dt>
                    <dd><span class="label label-info">{{ strtoupper($databaseService->database_type) }}</span></dd>
                    
                    <dt>Owner</dt>
                    <dd><a href="{{ route('admin.users.view', $databaseService->user->id) }}">{{ $databaseService->user->username }} ({{ $databaseService->user->email }})</a></dd>
                    
                    <dt>Node</dt>
                    <dd><a href="{{ route('admin.nodes.view', $databaseService->node->id) }}">{{ $databaseService->node->name }}</a></dd>
                    
                    <dt>Nest</dt>
                    <dd>{{ $databaseService->nest->name }}</dd>
                    
                    <dt>Egg</dt>
                    <dd>{{ $databaseService->egg->name }}</dd>
                    
                    @if($databaseService->allocation)
                        <dt>Connection</dt>
                        <dd><code>{{ $databaseService->allocation->alias }}:{{ $databaseService->allocation->port }}</code></dd>
                    @endif
                    
                    <dt>Status</dt>
                    <dd>
                        @if($databaseService->isSuspended())
                            <span class="label bg-maroon">Suspended</span>
                        @elseif(! $databaseService->isInstalled())
                            <span class="label label-warning">Installing</span>
                        @else
                            <span class="label label-success">Active</span>
                        @endif
                    </dd>
                    
                    <dt>Resources</dt>
                    <dd>
                        Memory: {{ number_format($databaseService->memory / 1024, 2) }} GB<br>
                        Disk: {{ number_format($databaseService->disk / 1024, 2) }} GB<br>
                        CPU: {{ $databaseService->cpu }}%<br>
                        IO: {{ $databaseService->io }}
                    </dd>
                    
                    @if($databaseService->subscription)
                        <dt>Subscription</dt>
                        <dd>Linked to subscription #{{ $databaseService->subscription->id }}</dd>
                    @endif
                    
                    <dt>Created</dt>
                    <dd>{{ $databaseService->created_at->format('Y-m-d H:i:s') }}</dd>
                    
                    @if($databaseService->installed_at)
                        <dt>Installed</dt>
                        <dd>{{ $databaseService->installed_at->format('Y-m-d H:i:s') }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

