<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\CheckDaemonType;
use Pterodactyl\Http\Controllers\Api\Client;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Activity\AccountSubject;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Elytra;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;
use Pterodactyl\Http\Controllers\Api\Client\ServersOrderController;
use Pterodactyl\Models\Announcement;

/*
|--------------------------------------------------------------------------
| Client Control API
|--------------------------------------------------------------------------
|
| Endpoint: /api/client
|
*/

Route::get('/', [Client\ClientController::class, 'index'])->name('api:client.index');
Route::get('/permissions', [Client\ClientController::class, 'permissions']);
Route::get('/version', function () {
    return response()->json(['version' => config('app.version')]);
});

Route::prefix('/servers')->group(function () {
    Route::get('/order', [Client\ServersOrderController::class, 'show']);
    Route::put('/order', [Client\ServersOrderController::class, 'update']);
});

Route::prefix('/nests')->group(function () {
    Route::get('/', [Client\Nests\NestController::class, 'index'])->name('api:client.nests');
    Route::get('/{nest}', [Client\Nests\NestController::class, 'view'])->name('api:client.nests.view');
});

Route::prefix('/hosting')->group(function () {
    Route::post('/checkout', [Client\Hosting\CheckoutController::class, 'store']);
    Route::get('/verify-payment', [Client\Hosting\PaymentVerificationController::class, 'check']);
    Route::get('/vps-distributions', [Client\Hosting\VpsDistributionController::class, 'index']);
    Route::get('/subdomain/domains', [Client\Hosting\SubdomainController::class, 'getAvailableDomains']);
    Route::post('/subdomain/check-availability', [Client\Hosting\SubdomainController::class, 'checkAvailability']);
});

Route::prefix('/billing')->group(function () {
    Route::get('/subscriptions', [Client\Billing\SubscriptionController::class, 'index']);
    Route::get('/subscriptions/{subscription}', [Client\Billing\SubscriptionController::class, 'view']);
    Route::post('/subscriptions/{subscription}/cancel', [Client\Billing\SubscriptionController::class, 'cancel']);
    Route::post('/subscriptions/{subscription}/resume', [Client\Billing\SubscriptionController::class, 'resume']);
    Route::get('/subscriptions/{subscription}/billing-portal', [Client\Billing\SubscriptionController::class, 'billingPortal']);
    Route::get('/invoices', [Client\Billing\InvoiceController::class, 'index']);
    Route::get('/credits/enabled', [Client\Billing\CreditsController::class, 'enabled']);
    Route::get('/credits/balance', [Client\Billing\CreditsController::class, 'balance']);
    Route::post('/credits/purchase', [Client\Billing\CreditsController::class, 'purchase']);
    Route::get('/credits/transactions', [Client\Billing\CreditsController::class, 'transactions']);
});

Route::prefix('/tickets')->group(function () {
    Route::get('/', [Client\Tickets\TicketController::class, 'index']);
    Route::post('/', [Client\Tickets\TicketController::class, 'store']);
    Route::get('/{ticket}', [Client\Tickets\TicketController::class, 'show']);
    Route::patch('/{ticket}', [Client\Tickets\TicketController::class, 'update']);
    Route::delete('/{ticket}', [Client\Tickets\TicketController::class, 'destroy']);
    Route::post('/{ticket}/resolve', [Client\Tickets\TicketController::class, 'resolve']);
    Route::post('/{ticket}/replies', [Client\Tickets\TicketReplyController::class, 'store']);
});

Route::prefix('/vps-servers')->group(function () {
    Route::get('/', [Client\Vps\VpsController::class, 'index']);
    Route::get('/{vps}', [Client\Vps\VpsController::class, 'view']);
    Route::post('/{vps}/power', [Client\Vps\VpsPowerController::class, 'send']);
    Route::get('/{vps}/metrics', [Client\Vps\VpsMetricsController::class, 'index']);
    Route::get('/{vps}/activity', [Client\Vps\VpsActivityController::class, '__invoke']);
});

Route::prefix('/account')->middleware(AccountSubject::class)->group(function () {
    Route::prefix('/')->withoutMiddleware(RequireTwoFactorAuthentication::class)->group(function () {
        Route::get('/', [Client\AccountController::class, 'index'])->name('api:client.account');
        Route::get('/two-factor', [Client\TwoFactorController::class, 'index']);
        Route::post('/two-factor', [Client\TwoFactorController::class, 'store']);
        Route::post('/two-factor/disable', [Client\TwoFactorController::class, 'delete']);
    });

    Route::put('/email', [Client\AccountController::class, 'updateEmail'])->name('api:client.account.update-email');
    Route::put('/password', [Client\AccountController::class, 'updatePassword'])->name('api:client.account.update-password');
    Route::put('/gravatar-style', [Client\AccountController::class, 'updateGravatarStyle'])->name('api:client.account.update-gravatar-style');

    Route::get('/activity', Client\ActivityLogController::class)->name('api:client.account.activity');

    Route::get('/api-keys', [Client\ApiKeyController::class, 'index']);
    Route::post('/api-keys', [Client\ApiKeyController::class, 'store']);
    Route::delete('/api-keys/{identifier}', [Client\ApiKeyController::class, 'delete']);

    Route::prefix('/ssh-keys')->group(function () {
        Route::get('/', [Client\SSHKeyController::class, 'index']);
        Route::post('/', [Client\SSHKeyController::class, 'store']);
        Route::post('/remove', [Client\SSHKeyController::class, 'delete']);
    });
});


/*
|--------------------------------------------------------------------------
| Client Control API
|--------------------------------------------------------------------------
|
| Endpoint: /api/client/servers/{server}
|
*/

Route::group([
    'prefix' => '/servers/{server}',
    'middleware' => [
        ServerSubject::class,
        AuthenticateServerAccess::class,
        ResourceBelongsToServer::class,
    ],
], function () {
<<<<<<< HEAD
    Route::get('/', [Client\Servers\ServerController::class, 'index'])->name('api:client:server.view');
    Route::get('/websocket', Client\Servers\WebsocketController::class)->name('api:client:server.ws');
    Route::get('/resources', Client\Servers\ResourceUtilizationController::class)->name('api:client:server.resources');
    Route::get('/activity', Client\Servers\ActivityLogController::class)->name('api:client:server.activity');
    Route::get('/billing-portal', [Client\Servers\ServerController::class, 'billingPortal'])->name('api:client:server.billing-portal');

    Route::post('/command', [Client\Servers\CommandController::class, 'index']);
    Route::post('/power', [Client\Servers\PowerController::class, 'index']);

    Route::group(['prefix' => '/databases'], function () {
        Route::get('/', [Client\Servers\DatabaseController::class, 'index']);
        Route::post('/', [Client\Servers\DatabaseController::class, 'store']);
        Route::post('/{database}/rotate-password', [Client\Servers\DatabaseController::class, 'rotatePassword']);
        Route::delete('/{database}', [Client\Servers\DatabaseController::class, 'delete']);
    });

    Route::group(['prefix' => '/database'], function () {
        Route::get('/connection', [Client\Servers\DatabaseController::class, 'getConnectionInfo']);
        Route::get('/metrics', [Client\Servers\DatabaseController::class, 'getMetrics']);
        Route::post('/connection/test', [Client\Servers\DatabaseController::class, 'testConnection']);
        Route::get('/databases', [Client\Servers\DatabaseController::class, 'listDatabases']);
        Route::post('/databases', [Client\Servers\DatabaseController::class, 'createDatabase']);
        Route::delete('/databases', [Client\Servers\DatabaseController::class, 'deleteDatabase']);
        Route::get('/tables', [Client\Servers\DatabaseController::class, 'listTables']);
        Route::get('/tables/structure', [Client\Servers\DatabaseController::class, 'getTableStructure']);
        Route::post('/tables', [Client\Servers\DatabaseController::class, 'createTable']);
        Route::delete('/tables', [Client\Servers\DatabaseController::class, 'deleteTable']);
        Route::get('/tables/data', [Client\Servers\DatabaseController::class, 'getTableData']);
        Route::post('/tables/data', [Client\Servers\DatabaseController::class, 'insertRow']);
        Route::put('/tables/data', [Client\Servers\DatabaseController::class, 'updateRow']);
        Route::delete('/tables/data', [Client\Servers\DatabaseController::class, 'deleteRow']);
        Route::post('/query', [Client\Servers\DatabaseController::class, 'executeQuery']);
        Route::get('/logs', [Client\Servers\DatabaseController::class, 'getLogs']);
        Route::get('/settings', [Client\Servers\DatabaseController::class, 'getSettings']);
        Route::put('/settings', [Client\Servers\DatabaseController::class, 'updateSettings']);
    });

    Route::group(['prefix' => '/files'], function () {
        Route::get('/list', [Client\Servers\FileController::class, 'directory']);
        Route::get('/contents', [Client\Servers\FileController::class, 'contents']);
        Route::get('/download', [Client\Servers\FileController::class, 'download']);
        Route::put('/rename', [Client\Servers\FileController::class, 'rename']);
        Route::post('/copy', [Client\Servers\FileController::class, 'copy']);
        Route::post('/write', [Client\Servers\FileController::class, 'write']);
        Route::post('/compress', [Client\Servers\FileController::class, 'compress']);
        Route::post('/decompress', [Client\Servers\FileController::class, 'decompress']);
        Route::post('/delete', [Client\Servers\FileController::class, 'delete']);
        Route::post('/create-folder', [Client\Servers\FileController::class, 'create']);
        Route::post('/chmod', [Client\Servers\FileController::class, 'chmod']);
        Route::post('/pull', [Client\Servers\FileController::class, 'pull'])->middleware(['throttle:10,5']);
        Route::get('/upload', Client\Servers\FileUploadController::class);
    });

    Route::group(['prefix' => '/schedules'], function () {
        Route::get('/', [Client\Servers\ScheduleController::class, 'index']);
        Route::post('/', [Client\Servers\ScheduleController::class, 'store']);
        Route::get('/{schedule}', [Client\Servers\ScheduleController::class, 'view']);
        Route::post('/{schedule}', [Client\Servers\ScheduleController::class, 'update']);
        Route::post('/{schedule}/execute', [Client\Servers\ScheduleController::class, 'execute']);
        Route::delete('/{schedule}', [Client\Servers\ScheduleController::class, 'delete']);

        Route::post('/{schedule}/tasks', [Client\Servers\ScheduleTaskController::class, 'store']);
        Route::post('/{schedule}/tasks/{task}', [Client\Servers\ScheduleTaskController::class, 'update']);
        Route::delete('/{schedule}/tasks/{task}', [Client\Servers\ScheduleTaskController::class, 'delete']);
    });

    Route::group(['prefix' => '/network'], function () {
        Route::get('/allocations', [Client\Servers\NetworkAllocationController::class, 'index']);
        Route::post('/allocations', [Client\Servers\NetworkAllocationController::class, 'store']);
        Route::post('/allocations/{allocation}', [Client\Servers\NetworkAllocationController::class, 'update']);
        Route::post('/allocations/{allocation}/primary', [Client\Servers\NetworkAllocationController::class, 'setPrimary']);
        Route::delete('/allocations/{allocation}', [Client\Servers\NetworkAllocationController::class, 'delete']);
    });
=======
    Route::get('/', [Client\ServerController::class, 'index'])->name('api.client.servers.daemonType');
    Route::get('/resources', [Client\ServerController::class, 'resources'])->name('api.client.servers.resources');
>>>>>>> upstream/main

    Route::group(['prefix' => '/subdomain'], function () {
        Route::get('/', [Elytra\SubdomainController::class, 'index']);
        Route::post('/', [Elytra\SubdomainController::class, 'store'])
            ->middleware('throttle:5,1'); // Max 5 creates/replaces per minute
        Route::delete('/', [Elytra\SubdomainController::class, 'destroy'])
            ->middleware('throttle:5,1'); // Max 5 deletes per minute
        Route::post('/check-availability', [Elytra\SubdomainController::class, 'checkAvailability'])
            ->middleware('throttle:20,1'); // Max 20 availability checks per minute
    });
});



/*
|--------------------------------------------------------------------------
| Client Control API(Wings)
|--------------------------------------------------------------------------
|
| Endpoint: /api/client/servers/wings/{server}
|
*/

Route::group([
    'prefix' => 'servers/wings/',
], function () {
    require __DIR__ . '/servers/wings.php';
});


/*
|--------------------------------------------------------------------------
| Client Control API(Elytra)
|--------------------------------------------------------------------------
|
| Endpoint: /api/client/servers/elytra/{server}
|
*/
Route::group([
    'prefix' => 'servers/elytra/',
], function () {
    require __DIR__ . '/servers/elytra.php';
});

Route::get('/announcements', function () {
    $now = now();
    $announcements = Announcement::query()
        ->where('active', true)
        ->where(function ($q) use ($now) {
            $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
        })
        ->orderByDesc('published_at')
        ->orderByDesc('created_at')
        ->get(['id', 'title', 'message', 'type', 'published_at', 'created_at']);

    return response()->json($announcements);
});
