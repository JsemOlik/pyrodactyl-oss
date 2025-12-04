@extends('layouts.admin')

@section('title')
    Database Services
@endsection

@section('content-header')
    <h1>Database Services<small>All database services available on the system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Database Services</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Database Service List</h3>
                <div class="box-tools">
                    <a href="{{ route('admin.database-services.new') }}" class="btn btn-sm btn-primary">Create New</a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Service Name</th>
                            <th>UUID</th>
                            <th>Type</th>
                            <th>Owner</th>
                            <th>Node</th>
                            <th>Connection</th>
                            <th class="text-center">Status</th>
                            <th></th>
                        </tr>
                        @forelse ($databaseServices as $databaseService)
                            <tr>
                                <td><a href="{{ route('admin.database-services.view', $databaseService->id) }}">{{ $databaseService->name }}</a></td>
                                <td><code title="{{ $databaseService->uuid }}">{{ $databaseService->uuidShort }}</code></td>
                                <td><span class="label label-info">{{ strtoupper($databaseService->database_type) }}</span></td>
                                <td><a href="{{ route('admin.users.view', $databaseService->user->id) }}">{{ $databaseService->user->username }}</a></td>
                                <td><a href="{{ route('admin.nodes.view', $databaseService->node->id) }}">{{ $databaseService->node->name }}</a></td>
                                <td>
                                    @if($databaseService->allocation)
                                        <code>{{ $databaseService->allocation->alias }}:{{ $databaseService->allocation->port }}</code>
                                    @else
                                        <span class="text-muted">N/A</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($databaseService->isSuspended())
                                        <span class="label bg-maroon">Suspended</span>
                                    @elseif(! $databaseService->isInstalled())
                                        <span class="label label-warning">Installing</span>
                                    @else
                                        <span class="label label-success">Active</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a class="btn btn-xs btn-default" href="/database/{{ $databaseService->uuidShort }}"><i class="fa fa-wrench"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">No database services found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($databaseServices->hasPages())
                <div class="box-footer with-border">
                    <div class="col-md-12 text-center">{!! $databaseServices->appends(request()->query())->render() !!}</div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

