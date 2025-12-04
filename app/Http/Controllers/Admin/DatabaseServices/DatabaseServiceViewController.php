<?php

namespace Pterodactyl\Http\Controllers\Admin\DatabaseServices;

use Illuminate\View\View;
use Pterodactyl\Models\DatabaseService;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Contracts\View\Factory as ViewFactory;

class DatabaseServiceViewController extends Controller
{
    /**
     * DatabaseServiceViewController constructor.
     */
    public function __construct(private ViewFactory $view)
    {
    }

    /**
     * Display database service details.
     */
    public function index(DatabaseService $databaseService): View
    {
        $databaseService->load(['node', 'user', 'allocation', 'nest', 'egg', 'subscription']);

        return $this->view->make('admin.database-services.view', [
            'databaseService' => $databaseService,
        ]);
    }
}

