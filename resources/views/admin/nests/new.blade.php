@extends('layouts.admin')

@section('title')
New Nest
@endsection

@section('content-header')
<h1>New Nest<small>Configure a new nest to deploy to all nodes.</small></h1>
<ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li><a href="{{ route('admin.nests') }}">Nests</a></li>
    <li class="active">New</li>
</ol>
@endsection

@section('content')
<form action="{{ route('admin.nests.new') }}" method="POST">
    <div class="row">
        <div class="col-md-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">New Nest</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label class="control-label">Name</label>
                        <div>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" />
                            <p class="text-muted"><small>This should be a descriptive category name that encompasses all of the eggs within the nest.</small></p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Description</label>
                        <div>
                            <textarea name="description" class="form-control" rows="6">{{ old('description') }}</textarea>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Dashboard Type <span class="field-required"></span></label>
                        <div>
                            <select name="dashboard_type" class="form-control">
                                <option value="game-server" {{ old('dashboard_type', 'game-server') === 'game-server' ? 'selected' : '' }}>Game Server</option>
                                <option value="database" {{ old('dashboard_type') === 'database' ? 'selected' : '' }}>Database</option>
                                <option value="website" {{ old('dashboard_type') === 'website' ? 'selected' : '' }}>Website</option>
                                <option value="s3-storage" {{ old('dashboard_type') === 's3-storage' ? 'selected' : '' }}>S3 Storage</option>
                            </select>
                            <p class="text-muted"><small>Determines which dashboard interface will be shown for servers using this nest. Defaults to "Game Server" for existing nests.</small></p>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-primary pull-right">Save</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection