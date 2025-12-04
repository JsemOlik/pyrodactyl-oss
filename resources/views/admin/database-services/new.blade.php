@extends('layouts.admin')

@section('title')
    Create Database Service
@endsection

@section('content-header')
    <h1>Create Database Service<small>Create a new database service instance.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.database-services') }}">Database Services</a></li>
        <li class="active">Create New</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Database Service Details</h3>
            </div>
            <form action="{{ route('admin.database-services.new') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label for="owner_id" class="form-label">Owner</label>
                        <select name="owner_id" id="owner_id" class="form-control" required>
                            <option value="">Select Owner</option>
                            @foreach(\Pterodactyl\Models\User::all() as $user)
                                <option value="{{ $user->id }}" {{ old('owner_id') == $user->id ? 'selected' : '' }}>{{ $user->username }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="name" class="form-label">Service Name</label>
                        <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required placeholder="My Database Service" />
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3" placeholder="Optional description">{{ old('description') }}</textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="database_type" class="form-label">Database Type</label>
                        <select name="database_type" id="database_type" class="form-control" required>
                            <option value="mysql" {{ old('database_type') == 'mysql' ? 'selected' : '' }}>MySQL</option>
                            <option value="mariadb" {{ old('database_type') == 'mariadb' ? 'selected' : '' }}>MariaDB</option>
                            <option value="postgresql" {{ old('database_type') == 'postgresql' ? 'selected' : '' }}>PostgreSQL</option>
                            <option value="mongodb" {{ old('database_type') == 'mongodb' ? 'selected' : '' }}>MongoDB</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nest_id" class="form-label">Nest</label>
                                <select name="nest_id" id="nest_id" class="form-control" required>
                                    <option value="">Select Nest</option>
                                    @foreach($nests as $nest)
                                        <option value="{{ $nest->id }}" {{ old('nest_id') == $nest->id ? 'selected' : '' }}>{{ $nest->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="egg_id" class="form-label">Egg</label>
                                <select name="egg_id" id="egg_id" class="form-control" required>
                                    <option value="">Select Egg</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="node_id" class="form-label">Node</label>
                        <select name="node_id" id="node_id" class="form-control" required>
                            <option value="">Select Node</option>
                            @foreach($locations as $location)
                                <optgroup label="{{ $location->short }}">
                                    @foreach($location->nodes as $node)
                                        <option value="{{ $node->id }}" {{ old('node_id') == $node->id ? 'selected' : '' }}>{{ $node->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="memory" class="form-label">Memory (MB)</label>
                                <input type="number" name="memory" id="memory" class="form-control" value="{{ old('memory', 512) }}" required min="128" />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="swap" class="form-label">Swap (MB)</label>
                                <input type="number" name="swap" id="swap" class="form-control" value="{{ old('swap', 0) }}" required min="-1" />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="disk" class="form-label">Disk (MB)</label>
                                <input type="number" name="disk" id="disk" class="form-control" value="{{ old('disk', 1024) }}" required min="1" />
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="io" class="form-label">IO Weight</label>
                                <input type="number" name="io" id="io" class="form-control" value="{{ old('io', 500) }}" required min="10" max="1000" />
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="cpu" class="form-label">CPU Limit (%)</label>
                        <input type="number" name="cpu" id="cpu" class="form-control" value="{{ old('cpu', 0) }}" required min="0" />
                        <p class="text-muted small">0 = unlimited</p>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="start_on_completion" value="1" {{ old('start_on_completion') ? 'checked' : '' }} />
                            Start database service after creation
                        </label>
                    </div>
                </div>
                <div class="box-footer">
                    <a href="{{ route('admin.database-services') }}" class="btn btn-default">Cancel</a>
                    <button type="submit" class="btn btn-primary pull-right">Create Database Service</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
        $('#nest_id').on('change', function() {
            const nestId = $(this).val();
            const eggSelect = $('#egg_id');
            
            eggSelect.html('<option value="">Loading...</option>');
            
            if (!nestId) {
                eggSelect.html('<option value="">Select Egg</option>');
                return;
            }
            
            // Load eggs for selected nest
            $.get('/api/application/nests/' + nestId + '/eggs', function(data) {
                eggSelect.html('<option value="">Select Egg</option>');
                data.data.forEach(function(egg) {
                    eggSelect.append('<option value="' + egg.attributes.id + '">' + egg.attributes.name + '</option>');
                });
            }).fail(function() {
                eggSelect.html('<option value="">Error loading eggs</option>');
            });
        });
        
        // Trigger change if nest is pre-selected
        @if(old('nest_id'))
            $('#nest_id').trigger('change');
            setTimeout(function() {
                $('#egg_id').val('{{ old('egg_id') }}');
            }, 500);
        @endif
    </script>
@endsection

