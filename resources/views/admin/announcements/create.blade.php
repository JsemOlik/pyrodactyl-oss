@extends('layouts.admin')

@section('title', 'Create Announcement')

@section('content-header')
    <h1>Create Announcement</h1>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.announcements.store') }}">
        @csrf
        <div class="box">
            <div class="box-body">
                <div class="form-group">
                    <label>Title</label>
                    <input name="title" class="form-control" value="{{ old('title') }}" required maxlength="150" />
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" class="form-control" rows="5" required>{{ old('message') }}</textarea>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-control">
                        <option value="info" {{ old('type') === 'info' ? 'selected' : '' }}>Info</option>
                        <option value="success" {{ old('type') === 'success' ? 'selected' : '' }}>Success</option>
                        <option value="warning" {{ old('type') === 'warning' ? 'selected' : '' }}>Warning</option>
                        <option value="danger" {{ old('type') === 'danger' ? 'selected' : '' }}>Danger</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="active" value="1" {{ old('active', '1') ? 'checked' : '' }} />
                        Active
                    </label>
                </div>
                <div class="form-group">
                    <label>Publish At (optional)</label>
                    <input type="datetime-local" name="published_at" class="form-control"
                        value="{{ old('published_at') ? \Carbon\Carbon::parse(old('published_at'))->format('Y-m-d\TH:i') : '' }}">
                </div>
            </div>
            <div class="box-footer">
                <a href="{{ route('admin.announcements.index') }}" class="btn btn-default">Cancel</a>
                <button class="btn btn-primary">Create</button>
            </div>
        </div>
    </form>
@endsection