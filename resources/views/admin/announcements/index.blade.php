@extends('layouts.admin')

@section('title', 'Announcements')

@section('content-header')
    <h1>Announcements <small>Display an announcement on the "Your Servers" page.</small></h1>
@endsection

@section('content')
    <div class="box">
        <div class="box-header with-border">
            <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary">
                <i class="fa fa-plus"></i> New Announcement
            </a>
        </div>
        <div class="box-body table-responsive no-padding">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Active</th>
                        <th>Published</th>
                        <th>Created</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($announcements as $a)
                        <tr>
                            <td>{{ $a->title }}</td>
                            <td>
                                <span
                                    class="label label-{{ $a->type === 'danger' ? 'danger' : ($a->type === 'warning' ? 'warning' : ($a->type === 'success' ? 'success' : 'info')) }}">
                                    {{ ucfirst($a->type) }}
                                </span>
                            </td>
                            <td>{!! $a->active ? '<span class="text-green">Yes</span>' : '<span class="text-muted">No</span>' !!}
                            </td>
                            <td>{{ $a->published_at ? $a->published_at->format('Y-m-d H:i') : '-' }}</td>
                            <td>{{ $a->created_at->format('Y-m-d H:i') }}</td>
                            <td class="text-right">
                                <a class="btn btn-xs btn-default" href="{{ route('admin.announcements.edit', $a) }}">
                                    <i class="fa fa-pencil"></i>
                                </a>
                                <form action="{{ route('admin.announcements.destroy', $a) }}" method="POST"
                                    style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-xs btn-danger" onclick="return confirm('Delete this announcement?')">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted">No announcements yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="box-footer">
            {{ $announcements->links() }}
        </div>
    </div>
@endsection
