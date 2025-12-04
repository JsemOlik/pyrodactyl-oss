@extends('layouts.admin')

@section('title', 'Edit Announcement')

@section('content-header')
    <h1>Edit Announcement</h1>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.announcements.update', $announcement) }}">
        @csrf
        @method('PUT')
        <div class="box">
            <div class="box-body">
                <div class="form-group">
                    <label>Title</label>
                    <input name="title" class="form-control" value="{{ old('title', $announcement->title) }}" required
                        maxlength="150" />
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="message" class="form-control" rows="5"
                        required>{{ old('message', $announcement->message) }}</textarea>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" class="form-control">
                        @foreach (['info', 'success', 'warning', 'danger'] as $t)
                            <option value="{{ $t }}" {{ old('type', $announcement->type) === $t ? 'selected' : '' }}>{{ ucfirst($t) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="active" value="1" {{ old('active', $announcement->active) ? 'checked' : '' }} />
                        Active
                    </label>
                </div>
                <div class="form-group">
                    <label>Publish At (optional)</label>
                    <input type="datetime-local" name="published_at" class="form-control"
                        value="{{ old('published_at', $announcement->published_at ? $announcement->published_at->format('Y-m-d\TH:i') : '') }}">
                </div>
            </div>
            <div class="box-footer">
                <a href="{{ route('admin.announcements.index') }}" class="btn btn-default">Cancel</a>
                <button class="btn btn-primary">Save</button>
            </div>
        </div>
    </form>
@endsection