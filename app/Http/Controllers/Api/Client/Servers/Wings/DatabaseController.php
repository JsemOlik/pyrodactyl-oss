<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers\Wings;

use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Database;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Databases\DatabasePasswordService;
use Pterodactyl\Transformers\Api\Client\DatabaseTransformer;
use Pterodactyl\Services\Databases\DatabaseManagementService;
use Pterodactyl\Services\Databases\DeployServerDatabaseService;
use Pterodactyl\Services\Databases\DatabaseDashboardService;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\GetDatabasesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\StoreDatabaseRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\DeleteDatabaseRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Databases\RotatePasswordRequest;

class DatabaseController extends ClientApiController
{
    /**
     * DatabaseController constructor.
     */
    public function __construct(
        private DeployServerDatabaseService $deployDatabaseService,
        private DatabaseManagementService $managementService,
        private DatabasePasswordService $passwordService,
        private DatabaseDashboardService $dashboardService,
    ) {
        parent::__construct();
    }

    /**
     * Return all the databases that belong to the given server.
     */
    public function index(GetDatabasesRequest $request, Server $server): array
    {
        return $this->fractal->collection($server->databases)
            ->transformWith($this->getTransformer(DatabaseTransformer::class))
            ->toArray();
    }

    /**
     * Create a new database for the given server and return it.
     *
     * @throws \Throwable
     * @throws \Pterodactyl\Exceptions\Service\Database\TooManyDatabasesException
     * @throws \Pterodactyl\Exceptions\Service\Database\DatabaseClientFeatureNotEnabledException
     */
    public function store(StoreDatabaseRequest $request, Server $server): array
    {
        $database = $this->deployDatabaseService->handle($server, $request->validated());

        Activity::event('server:database.create')
            ->subject($database)
            ->property('name', $database->database)
            ->log();

        return $this->fractal->item($database)
            ->parseIncludes(['password'])
            ->transformWith($this->getTransformer(DatabaseTransformer::class))
            ->toArray();
    }

    /**
     * Rotates the password for the given server model and returns a fresh instance to
     * the caller.
     *
     * @throws \Throwable
     */
    public function rotatePassword(RotatePasswordRequest $request, Server $server, Database $database): array
    {
        $this->passwordService->handle($database);
        $database->refresh();

        Activity::event('server:database.rotate-password')
            ->subject($database)
            ->property('name', $database->database)
            ->log();

        return $this->fractal->item($database)
            ->parseIncludes(['password'])
            ->transformWith($this->getTransformer(DatabaseTransformer::class))
            ->toArray();
    }

    /**
     * Removes a database from the server.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function delete(DeleteDatabaseRequest $request, Server $server, Database $database): Response
    {
        $this->managementService->delete($database);

        Activity::event('server:database.delete')
            ->subject($database)
            ->property('name', $database->database)
            ->log();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    /**
     * Get connection information for the server's database.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function getConnectionInfo(GetDatabasesRequest $request, Server $server): array
    {
        $info = $this->dashboardService->getConnectionInfo($server);

        return $this->fractal->item($info)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }

    /**
     * Get database metrics for the server's database.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function getMetrics(GetDatabasesRequest $request, Server $server): array
    {
        $metrics = $this->dashboardService->getMetrics($server);

        return $this->fractal->item($metrics)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }

    /**
     * Test database connection.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function testConnection(GetDatabasesRequest $request, Server $server): Response
    {
        $success = $this->dashboardService->testConnection($server);

        if (!$success) {
            return response()->json(['error' => 'Connection test failed'], Response::HTTP_BAD_REQUEST);
        }

        return response()->json(['success' => true, 'message' => 'Connection successful']);
    }

    /**
     * List all databases on the server's database host.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function listDatabases(GetDatabasesRequest $request, Server $server): array
    {
        $databases = $this->dashboardService->listDatabases($server);

        return $this->fractal->collection($databases)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }

    /**
     * Create a new database on the server's database host.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function createDatabase(GetDatabasesRequest $request, Server $server): array
    {
        $request->validate([
            'name' => 'required|string|min:1|max:64|regex:/^[a-zA-Z0-9_]+$/',
            'username' => 'nullable|string|min:1|max:32|regex:/^[a-zA-Z0-9_]+$/',
            'password' => 'nullable|string|min:1',
            'remote' => 'nullable|string|max:255',
        ]);

        $database = $this->dashboardService->createDatabase(
            $server,
            $request->input('name'),
            $request->input('username'),
            $request->input('password'),
            $request->input('remote', '%')
        );

        return $this->fractal->item($database)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }

    /**
     * Delete a database from the server's database host.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function deleteDatabase(GetDatabasesRequest $request, Server $server): Response
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $this->dashboardService->deleteDatabase($server, $request->input('name'));

        return response()->json(['success' => true, 'message' => 'Database deleted successfully']);
    }

    /**
     * List all tables in the server's database.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function listTables(GetDatabasesRequest $request, Server $server): array
    {
        $databaseName = $request->input('database');
        $tables = $this->dashboardService->listTables($server, $databaseName);

        return $this->fractal->collection($tables)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }

    /**
     * Get table structure.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function getTableStructure(GetDatabasesRequest $request, Server $server): array
    {
        $request->validate([
            'table' => 'required|string',
            'database' => 'nullable|string',
        ]);

        $structure = $this->dashboardService->getTableStructure(
            $server,
            $request->input('table'),
            $request->input('database')
        );

        return $this->fractal->item($structure)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }

    /**
     * Create a new table.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function createTable(GetDatabasesRequest $request, Server $server): array
    {
        $request->validate([
            'name' => 'required|string|min:1|max:64|regex:/^[a-zA-Z0-9_]+$/',
            'columns' => 'required|array|min:1',
            'columns.*.name' => 'required|string|regex:/^[a-zA-Z0-9_]+$/',
            'columns.*.type' => 'required|string',
            'database' => 'nullable|string',
            'engine' => 'nullable|string',
            'collation' => 'nullable|string',
        ]);

        $table = $this->dashboardService->createTable(
            $server,
            $request->input('name'),
            $request->input('columns'),
            $request->input('database'),
            $request->input('engine'),
            $request->input('collation')
        );

        return $this->fractal->item($table)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }

    /**
     * Delete a table.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function deleteTable(GetDatabasesRequest $request, Server $server): Response
    {
        $request->validate([
            'table' => 'required|string',
            'database' => 'nullable|string',
        ]);

        $this->dashboardService->deleteTable(
            $server,
            $request->input('table'),
            $request->input('database')
        );

        return response()->json(['success' => true, 'message' => 'Table deleted successfully']);
    }

    /**
     * Get table data with pagination.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function getTableData(GetDatabasesRequest $request, Server $server): array
    {
        $request->validate([
            'table' => 'required|string',
            'database' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $this->dashboardService->getTableData(
            $server,
            $request->input('table'),
            $request->input('page', 1),
            $request->input('per_page', 50),
            $request->input('database')
        );

        return $this->fractal->item($result)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }

    /**
     * Insert a new row into a table.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function insertRow(GetDatabasesRequest $request, Server $server): array
    {
        $request->validate([
            'table' => 'required|string',
            'data' => 'required|array',
            'database' => 'nullable|string',
        ]);

        $result = $this->dashboardService->insertRow(
            $server,
            $request->input('table'),
            $request->input('data'),
            $request->input('database')
        );

        return $this->fractal->item($result)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }

    /**
     * Update a row in a table.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function updateRow(GetDatabasesRequest $request, Server $server): array
    {
        $request->validate([
            'table' => 'required|string',
            'data' => 'required|array',
            'where' => 'required|array',
            'database' => 'nullable|string',
        ]);

        $result = $this->dashboardService->updateRow(
            $server,
            $request->input('table'),
            $request->input('data'),
            $request->input('where'),
            $request->input('database')
        );

        return $this->fractal->item($result)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }

    /**
     * Delete a row from a table.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function deleteRow(GetDatabasesRequest $request, Server $server): Response
    {
        $request->validate([
            'table' => 'required|string',
            'where' => 'required|array',
            'database' => 'nullable|string',
        ]);

        $this->dashboardService->deleteRow(
            $server,
            $request->input('table'),
            $request->input('where'),
            $request->input('database')
        );

        return response()->json(['success' => true, 'message' => 'Row deleted successfully']);
    }

    /**
     * Execute a SQL query (SELECT only).
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function executeQuery(GetDatabasesRequest $request, Server $server): array
    {
        $request->validate([
            'query' => 'required|string|max:10000',
            'database' => 'nullable|string',
        ]);

        $result = $this->dashboardService->executeQuery(
            $server,
            $request->input('query'),
            $request->input('database')
        );

        return $this->fractal->item($result)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }

    /**
     * Get database logs.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function getLogs(GetDatabasesRequest $request, Server $server): array
    {
        $request->validate([
            'type' => 'nullable|string|in:error,slow,general',
            'limit' => 'nullable|integer|min:1|max:1000',
            'database' => 'nullable|string',
        ]);

        $result = $this->dashboardService->getLogs(
            $server,
            $request->input('type', 'general'),
            $request->input('limit', 100),
            $request->input('database')
        );

        return $this->fractal->item($result)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }

    /**
     * Get database settings.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function getSettings(GetDatabasesRequest $request, Server $server): array
    {
        $request->validate([
            'database' => 'nullable|string',
        ]);

        $result = $this->dashboardService->getSettings(
            $server,
            $request->input('database')
        );

        return $this->fractal->item($result)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }

    /**
     * Update database settings.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function updateSettings(GetDatabasesRequest $request, Server $server): array
    {
        $request->validate([
            'charset' => 'nullable|string|max:50',
            'collation' => 'nullable|string|max:50',
            'database' => 'nullable|string',
        ]);

        $result = $this->dashboardService->updateSettings(
            $server,
            $request->input('charset'),
            $request->input('collation'),
            $request->input('database')
        );

        return $this->fractal->item($result)
            ->transformWith(function ($item) {
                return ['attributes' => $item];
            })
            ->toArray();
    }
}
