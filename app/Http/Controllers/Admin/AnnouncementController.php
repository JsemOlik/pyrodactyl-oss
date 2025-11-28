<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Announcement;

class AnnouncementController extends Controller
{
    public function __construct(private ViewFactory $view)
    {
    }

    public function index(): View
    {
        $announcements = Announcement::query()
            ->orderByDesc('created_at')
            ->paginate(config('pterodactyl.paginate.admin.servers', 15));

        return $this->view->make('admin.announcements.index', compact('announcements'));
    }

    public function create(): View
    {
        return $this->view->make('admin.announcements.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string'],
            'type' => ['required', Rule::in(['success', 'info', 'warning', 'danger'])],
            'active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['active'] = $request->boolean('active', true);

        Announcement::create($data);

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement created.');
    }

    public function edit(Announcement $announcement): View
    {
        return $this->view->make('admin.announcements.edit', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string'],
            'type' => ['required', Rule::in(['success', 'info', 'warning', 'danger'])],
            'active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['active'] = $request->boolean('active', true);

        $announcement->update($data);

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Announcement deleted.');
    }
}
