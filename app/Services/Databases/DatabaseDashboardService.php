<?php

namespace Pterodactyl\Services\Databases;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\Database;
use Illuminate\Database\DatabaseManager;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Extensions\DynamicDatabaseConnection;
use Pterodactyl\Exceptions\Repository\RecordNotFoundException;

class DatabaseDashboardService
{
    public function __construct(
        private DatabaseManager $databaseManager,
        private DynamicDatabaseConnection $dynamic,
        private Encrypter $encrypter,
    ) {
    }

    /**
     * Get connection information for the server's primary database.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function getConnectionInfo(Server $server): array
    {
        // Get the first database for this server (primary database)
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;
        $password = $this->encrypter->decrypt($database->password);

        // Build connection strings
        $mysqlString = sprintf(
            'mysql -h %s -P %d -u %s -p%s %s',
            $host->host,
            $host->port,
            $database->username,
            $password,
            $database->database,
        );

        $pdoString = sprintf(
            'mysql:host=%s;port=%d;dbname=%s',
            $host->host,
            $host->port,
            $database->database,
        );

        return [
            'host' => $host->host,
            'port' => $host->port,
            'database' => $database->database,
            'username' => $database->username,
            'password' => $password,
            'connectionStrings' => [
                'mysql' => $mysqlString,
                'pdo' => $pdoString,
            ],
        ];
    }

    /**
     * Get database metrics for the server's primary database.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function getMetrics(Server $server): array
    {
        // Get the first database for this server (primary database)
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;

        // Set up dynamic connection
        $this->dynamic->set('dashboard_metrics', $host, $database->database);

        try {
            $connection = $this->databaseManager->connection('dashboard_metrics');

            // Get database size
            $sizeResult = $connection->selectOne(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                 FROM information_schema.tables 
                 WHERE table_schema = ?",
                [$database->database],
            );
            $size = (float) ($sizeResult->size_mb ?? 0);
            $sizeFormatted = $this->formatBytes($size * 1024 * 1024);

            // Get table count
            $tableCount = $connection->selectOne(
                "SELECT COUNT(*) as count 
                 FROM information_schema.tables 
                 WHERE table_schema = ?",
                [$database->database],
            )->count ?? 0;

            // Get connection count and max connections
            $connectionInfo = $connection->selectOne('SHOW STATUS WHERE Variable_name = ?', ['Threads_connected']);
            $maxConnections = $connection->selectOne('SHOW VARIABLES WHERE Variable_name = ?', ['max_connections']);
            $connectionCount = (int) ($connectionInfo->Value ?? 0);
            $maxConnectionsValue = (int) ($maxConnections->Value ?? 0);

            // Get query count (approximate from status)
            $queries = $connection->selectOne('SHOW STATUS WHERE Variable_name = ?', ['Questions']);
            $queryCount = (int) ($queries->Value ?? 0);

            // Get uptime
            $uptime = $connection->selectOne('SHOW STATUS WHERE Variable_name = ?', ['Uptime']);
            $uptimeValue = (int) ($uptime->Value ?? 0);

            return [
                'size' => $size,
                'sizeFormatted' => $sizeFormatted,
                'tableCount' => $tableCount,
                'connectionCount' => $connectionCount,
                'maxConnections' => $maxConnectionsValue,
                'queryCount' => $queryCount,
                'uptime' => $uptimeValue,
            ];
        } finally {
            // Clean up the dynamic connection
            $this->databaseManager->purge('dashboard_metrics');
        }
    }

    /**
     * Test database connection.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function testConnection(Server $server): bool
    {
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;

        try {
            $this->dynamic->set('dashboard_test', $host, $database->database);
            $connection = $this->databaseManager->connection('dashboard_test');
            $connection->selectOne('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        } finally {
            $this->databaseManager->purge('dashboard_test');
        }
    }

    /**
     * List all databases on the server's database host.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function listDatabases(Server $server): array
    {
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;

        // Set up dynamic connection
        $this->dynamic->set('dashboard_list', $host, 'information_schema');

        try {
            $connection = $this->databaseManager->connection('dashboard_list');

            // Get all databases (excluding system databases)
            $databases = $connection->select(
                "SELECT 
                    SCHEMA_NAME as name,
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb,
                    COUNT(TABLE_NAME) as table_count
                FROM information_schema.SCHEMATA
                LEFT JOIN information_schema.TABLES ON SCHEMA_NAME = TABLE_SCHEMA
                WHERE SCHEMA_NAME NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys')
                GROUP BY SCHEMA_NAME
                ORDER BY SCHEMA_NAME"
            );

            return array_map(function ($db) {
                return [
                    'name' => $db->name,
                    'size' => (float) ($db->size_mb ?? 0),
                    'sizeFormatted' => $this->formatBytes((int) (($db->size_mb ?? 0) * 1024 * 1024)),
                    'tableCount' => (int) ($db->table_count ?? 0),
                ];
            }, $databases);
        } finally {
            $this->databaseManager->purge('dashboard_list');
        }
    }

    /**
     * Create a new database on the server's database host.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function createDatabase(Server $server, string $databaseName, ?string $username = null, ?string $password = null, string $remote = '%'): array
    {
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;

        // Validate database name
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
            throw new \InvalidArgumentException('Database name can only contain alphanumeric characters and underscores.');
        }

        // Set up dynamic connection
        $this->dynamic->set('dashboard_create', $host, 'mysql');

        try {
            $connection = $this->databaseManager->connection('dashboard_create');

            // Create database (database name is validated, but we'll still escape it)
            $connection->statement("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '``', $databaseName) . "`");

            $result = [
                'name' => $databaseName,
                'created' => true,
            ];

            // Create user if credentials provided
            if ($username && $password) {
                // Escape identifiers and values
                $escapedUsername = str_replace(['`', '\\'], ['``', '\\\\'], $username);
                $escapedRemote = str_replace(['`', '\\'], ['``', '\\\\'], $remote);
                $escapedDatabase = str_replace('`', '``', $databaseName);
                $escapedPassword = $connection->getPdo()->quote($password);

                $connection->statement("CREATE USER IF NOT EXISTS `{$escapedUsername}`@`{$escapedRemote}` IDENTIFIED BY {$escapedPassword}");
                $connection->statement("GRANT ALL PRIVILEGES ON `{$escapedDatabase}`.* TO `{$escapedUsername}`@`{$escapedRemote}`");
                $connection->statement('FLUSH PRIVILEGES');

                $result['username'] = $username;
                $result['password'] = $password;
            }

            return $result;
        } finally {
            $this->databaseManager->purge('dashboard_create');
        }
    }

    /**
     * Delete a database from the server's database host.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function deleteDatabase(Server $server, string $databaseName): bool
    {
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;

        // Prevent deletion of system databases
        $systemDatabases = ['information_schema', 'performance_schema', 'mysql', 'sys'];
        if (in_array(strtolower($databaseName), $systemDatabases)) {
            throw new \InvalidArgumentException('Cannot delete system databases.');
        }

        // Set up dynamic connection
        $this->dynamic->set('dashboard_delete', $host, 'mysql');

        try {
            $connection = $this->databaseManager->connection('dashboard_delete');
            // Escape database name (already validated, but safe to escape)
            $escapedDatabase = str_replace('`', '``', $databaseName);
            $connection->statement("DROP DATABASE IF EXISTS `{$escapedDatabase}`");
            return true;
        } finally {
            $this->databaseManager->purge('dashboard_delete');
        }
    }

    /**
     * List all tables in the server's primary database.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function listTables(Server $server, ?string $databaseName = null): array
    {
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;
        $targetDatabase = $databaseName ?? $database->database;

        // Set up dynamic connection
        $this->dynamic->set('dashboard_tables', $host, $targetDatabase);

        try {
            $connection = $this->databaseManager->connection('dashboard_tables');

            // Get all tables with their sizes
            $tables = $connection->select(
                "SELECT 
                    TABLE_NAME as name,
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as size_mb,
                    TABLE_ROWS as row_count,
                    ENGINE as engine,
                    TABLE_COLLATION as collation
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ?
                ORDER BY TABLE_NAME",
                [$targetDatabase]
            );

            return array_map(function ($table) {
                return [
                    'name' => $table->name,
                    'size' => (float) ($table->size_mb ?? 0),
                    'sizeFormatted' => $this->formatBytes((int) (($table->size_mb ?? 0) * 1024 * 1024)),
                    'rowCount' => (int) ($table->row_count ?? 0),
                    'engine' => $table->engine ?? 'InnoDB',
                    'collation' => $table->collation ?? '',
                ];
            }, $tables);
        } finally {
            $this->databaseManager->purge('dashboard_tables');
        }
    }

    /**
     * Get table structure (columns, indexes, etc.).
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function getTableStructure(Server $server, string $tableName, ?string $databaseName = null): array
    {
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;
        $targetDatabase = $databaseName ?? $database->database;

        // Set up dynamic connection
        $this->dynamic->set('dashboard_structure', $host, $targetDatabase);

        try {
            $connection = $this->databaseManager->connection('dashboard_structure');

            // Get column information
            $columns = $connection->select(
                "SELECT 
                    COLUMN_NAME as name,
                    DATA_TYPE as type,
                    COLUMN_TYPE as fullType,
                    IS_NULLABLE as nullable,
                    COLUMN_DEFAULT as defaultValue,
                    COLUMN_KEY as key,
                    EXTRA as extra,
                    COLUMN_COMMENT as comment,
                    CHARACTER_MAXIMUM_LENGTH as maxLength,
                    NUMERIC_PRECISION as precision,
                    NUMERIC_SCALE as scale
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION",
                [$targetDatabase, $tableName]
            );

            // Get indexes
            $indexes = $connection->select(
                "SELECT 
                    INDEX_NAME as name,
                    COLUMN_NAME as column,
                    NON_UNIQUE as nonUnique,
                    SEQ_IN_INDEX as sequence,
                    INDEX_TYPE as type
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ORDER BY INDEX_NAME, SEQ_IN_INDEX",
                [$targetDatabase, $tableName]
            );

            // Get table info
            $tableInfo = $connection->selectOne(
                "SELECT 
                    ENGINE as engine,
                    TABLE_COLLATION as collation,
                    TABLE_COMMENT as comment,
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as size_mb,
                    TABLE_ROWS as row_count
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
                [$targetDatabase, $tableName]
            );

            return [
                'name' => $tableName,
                'columns' => array_map(function ($col) {
                    return [
                        'name' => $col->name,
                        'type' => $col->type,
                        'fullType' => $col->fullType,
                        'nullable' => $col->nullable === 'YES',
                        'defaultValue' => $col->defaultValue,
                        'key' => $col->key,
                        'extra' => $col->extra,
                        'comment' => $col->comment,
                        'maxLength' => $col->maxLength,
                        'precision' => $col->precision,
                        'scale' => $col->scale,
                    ];
                }, $columns),
                'indexes' => $this->groupIndexes($indexes),
                'engine' => $tableInfo->engine ?? 'InnoDB',
                'collation' => $tableInfo->collation ?? '',
                'comment' => $tableInfo->comment ?? '',
                'size' => (float) ($tableInfo->size_mb ?? 0),
                'sizeFormatted' => $this->formatBytes((int) (($tableInfo->size_mb ?? 0) * 1024 * 1024)),
                'rowCount' => (int) ($tableInfo->row_count ?? 0),
            ];
        } finally {
            $this->databaseManager->purge('dashboard_structure');
        }
    }

    /**
     * Create a new table.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function createTable(Server $server, string $tableName, array $columns, ?string $databaseName = null, ?string $engine = null, ?string $collation = null): array
    {
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;
        $targetDatabase = $databaseName ?? $database->database;

        // Validate table name
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            throw new \InvalidArgumentException('Table name can only contain alphanumeric characters and underscores.');
        }

        // Set up dynamic connection
        $this->dynamic->set('dashboard_create_table', $host, $targetDatabase);

        try {
            $connection = $this->databaseManager->connection('dashboard_create_table');

            // Build CREATE TABLE statement
            $columnDefinitions = [];
            foreach ($columns as $column) {
                $def = "`" . str_replace('`', '``', $column['name']) . "` " . $column['type'];
                
                if (isset($column['length']) && $column['length']) {
                    $def .= "(" . (int) $column['length'] . ")";
                } elseif (isset($column['precision']) && isset($column['scale'])) {
                    $def .= "(" . (int) $column['precision'] . "," . (int) $column['scale'] . ")";
                }

                if (isset($column['unsigned']) && $column['unsigned']) {
                    $def .= " UNSIGNED";
                }

                if (isset($column['nullable']) && !$column['nullable']) {
                    $def .= " NOT NULL";
                }

                if (isset($column['defaultValue']) && $column['defaultValue'] !== null && $column['defaultValue'] !== '') {
                    if (strtoupper($column['defaultValue']) === 'CURRENT_TIMESTAMP') {
                        $def .= " DEFAULT CURRENT_TIMESTAMP";
                    } else {
                        $def .= " DEFAULT " . $connection->getPdo()->quote($column['defaultValue']);
                    }
                }

                if (isset($column['autoIncrement']) && $column['autoIncrement']) {
                    $def .= " AUTO_INCREMENT";
                }

                if (isset($column['comment']) && $column['comment']) {
                    $def .= " COMMENT " . $connection->getPdo()->quote($column['comment']);
                }

                $columnDefinitions[] = $def;
            }

            // Add primary key if specified
            $primaryKey = null;
            foreach ($columns as $column) {
                if (isset($column['primaryKey']) && $column['primaryKey']) {
                    if ($primaryKey) {
                        $primaryKey .= ", `" . str_replace('`', '``', $column['name']) . "`";
                    } else {
                        $primaryKey = "`" . str_replace('`', '``', $column['name']) . "`";
                    }
                }
            }

            if ($primaryKey) {
                $columnDefinitions[] = "PRIMARY KEY ({$primaryKey})";
            }

            $engine = $engine ?? 'InnoDB';
            $collation = $collation ?? 'utf8mb4_unicode_ci';

            $sql = "CREATE TABLE `" . str_replace('`', '``', $tableName) . "` (\n  " . implode(",\n  ", $columnDefinitions) . "\n) ENGINE={$engine} DEFAULT CHARSET=utf8mb4 COLLATE={$collation}";

            $connection->statement($sql);

            return [
                'name' => $tableName,
                'created' => true,
            ];
        } finally {
            $this->databaseManager->purge('dashboard_create_table');
        }
    }

    /**
     * Delete a table.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function deleteTable(Server $server, string $tableName, ?string $databaseName = null): bool
    {
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;
        $targetDatabase = $databaseName ?? $database->database;

        // Set up dynamic connection
        $this->dynamic->set('dashboard_delete_table', $host, $targetDatabase);

        try {
            $connection = $this->databaseManager->connection('dashboard_delete_table');
            $escapedTable = str_replace('`', '``', $tableName);
            $connection->statement("DROP TABLE IF EXISTS `{$escapedTable}`");
            return true;
        } finally {
            $this->databaseManager->purge('dashboard_delete_table');
        }
    }

    /**
     * Group indexes by name.
     */
    private function groupIndexes(array $indexes): array
    {
        $grouped = [];
        foreach ($indexes as $index) {
            $name = $index->name;
            if (!isset($grouped[$name])) {
                $grouped[$name] = [
                    'name' => $name,
                    'type' => $index->type,
                    'unique' => $index->nonUnique == 0,
                    'columns' => [],
                ];
            }
            $grouped[$name]['columns'][] = $index->column;
        }
        return array_values($grouped);
    }

    /**
     * Get table data with pagination.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function getTableData(Server $server, string $tableName, int $page = 1, int $perPage = 50, ?string $databaseName = null): array
    {
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;
        $targetDatabase = $databaseName ?? $database->database;

        // Set up dynamic connection
        $this->dynamic->set('dashboard_data', $host, $targetDatabase);

        try {
            $connection = $this->databaseManager->connection('dashboard_data');

            // Get total count
            $total = $connection->selectOne("SELECT COUNT(*) as count FROM `" . str_replace('`', '``', $tableName) . "`");
            $totalCount = (int) ($total->count ?? 0);

            // Get data with pagination
            $offset = ($page - 1) * $perPage;
            $escapedTable = str_replace('`', '``', $tableName);
            $data = $connection->select("SELECT * FROM `{$escapedTable}` LIMIT {$perPage} OFFSET {$offset}");

            // Get column names
            $columns = $connection->select("SHOW COLUMNS FROM `{$escapedTable}`");
            $columnNames = array_map(fn($col) => $col->Field, $columns);

            return [
                'data' => array_map(function ($row) use ($columnNames) {
                    $result = [];
                    foreach ($columnNames as $col) {
                        $result[$col] = $row->$col;
                    }
                    return $result;
                }, $data),
                'columns' => $columnNames,
                'pagination' => [
                    'total' => $totalCount,
                    'perPage' => $perPage,
                    'currentPage' => $page,
                    'lastPage' => (int) ceil($totalCount / $perPage),
                ],
            ];
        } finally {
            $this->databaseManager->purge('dashboard_data');
        }
    }

    /**
     * Insert a new row into a table.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function insertRow(Server $server, string $tableName, array $data, ?string $databaseName = null): array
    {
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;
        $targetDatabase = $databaseName ?? $database->database;

        // Set up dynamic connection
        $this->dynamic->set('dashboard_insert', $host, $targetDatabase);

        try {
            $connection = $this->databaseManager->connection('dashboard_insert');
            $escapedTable = str_replace('`', '``', $tableName);

            // Build insert query
            $columns = array_keys($data);
            $values = array_values($data);

            $escapedColumns = array_map(fn($col) => "`" . str_replace('`', '``', $col) . "`", $columns);
            $placeholders = array_fill(0, count($values), '?');

            $sql = "INSERT INTO `{$escapedTable}` (" . implode(', ', $escapedColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";

            $connection->insert($sql, $values);

            return [
                'success' => true,
                'insertId' => $connection->getPdo()->lastInsertId(),
            ];
        } finally {
            $this->databaseManager->purge('dashboard_insert');
        }
    }

    /**
     * Update a row in a table.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function updateRow(Server $server, string $tableName, array $data, array $where, ?string $databaseName = null): array
    {
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;
        $targetDatabase = $databaseName ?? $database->database;

        // Set up dynamic connection
        $this->dynamic->set('dashboard_update', $host, $targetDatabase);

        try {
            $connection = $this->databaseManager->connection('dashboard_update');
            $escapedTable = str_replace('`', '``', $tableName);

            // Build update query
            $setClauses = [];
            $setValues = [];
            foreach ($data as $column => $value) {
                $escapedCol = "`" . str_replace('`', '``', $column) . "`";
                $setClauses[] = "{$escapedCol} = ?";
                $setValues[] = $value;
            }

            $whereClauses = [];
            $whereValues = [];
            foreach ($where as $column => $value) {
                $escapedCol = "`" . str_replace('`', '``', $column) . "`";
                $whereClauses[] = "{$escapedCol} = ?";
                $whereValues[] = $value;
            }

            $sql = "UPDATE `{$escapedTable}` SET " . implode(', ', $setClauses) . " WHERE " . implode(' AND ', $whereClauses);
            $connection->update($sql, array_merge($setValues, $whereValues));

            return [
                'success' => true,
                'affected' => $connection->getPdo()->rowCount(),
            ];
        } finally {
            $this->databaseManager->purge('dashboard_update');
        }
    }

    /**
     * Delete a row from a table.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function deleteRow(Server $server, string $tableName, array $where, ?string $databaseName = null): array
    {
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;
        $targetDatabase = $databaseName ?? $database->database;

        // Set up dynamic connection
        $this->dynamic->set('dashboard_delete_row', $host, $targetDatabase);

        try {
            $connection = $this->databaseManager->connection('dashboard_delete_row');
            $escapedTable = str_replace('`', '``', $tableName);

            // Build delete query
            $whereClauses = [];
            $whereValues = [];
            foreach ($where as $column => $value) {
                $escapedCol = "`" . str_replace('`', '``', $column) . "`";
                $whereClauses[] = "{$escapedCol} = ?";
                $whereValues[] = $value;
            }

            $sql = "DELETE FROM `{$escapedTable}` WHERE " . implode(' AND ', $whereClauses);
            $connection->delete($sql, $whereValues);

            return [
                'success' => true,
                'affected' => $connection->getPdo()->rowCount(),
            ];
        } finally {
            $this->databaseManager->purge('dashboard_delete_row');
        }
    }

    /**
     * Execute a SQL query (read-only SELECT queries for safety).
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function executeQuery(Server $server, string $query, ?string $databaseName = null): array
    {
        $database = $server->databases()->with('host')->first();

        if (!$database) {
            throw new RecordNotFoundException('No database found for this server.');
        }

        $host = $database->host;
        $targetDatabase = $databaseName ?? $database->database;

        // Validate query - only allow SELECT statements
        $trimmedQuery = trim($query);
        if (!preg_match('/^\s*SELECT\s+/i', $trimmedQuery)) {
            throw new \InvalidArgumentException('Only SELECT queries are allowed for security reasons.');
        }

        // Prevent dangerous operations
        $dangerousKeywords = ['DROP', 'DELETE', 'UPDATE', 'INSERT', 'ALTER', 'CREATE', 'TRUNCATE', 'EXEC', 'EXECUTE'];
        foreach ($dangerousKeywords as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $trimmedQuery)) {
                throw new \InvalidArgumentException("Query contains dangerous keyword: {$keyword}. Only SELECT queries are allowed.");
            }
        }

        // Set up dynamic connection
        $this->dynamic->set('dashboard_query', $host, $targetDatabase);

        try {
            $connection = $this->databaseManager->connection('dashboard_query');
            
            $startTime = microtime(true);
            $results = $connection->select($query);
            $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Convert results to array format
            $data = [];
            if (!empty($results)) {
                $firstRow = (array) $results[0];
                $columns = array_keys($firstRow);
                
                $data = array_map(function ($row) use ($columns) {
                    $result = [];
                    foreach ($columns as $col) {
                        $result[$col] = is_object($row) ? $row->$col : $row[$col] ?? null;
                    }
                    return $result;
                }, $results);
            } else {
                $columns = [];
            }

            return [
                'success' => true,
                'data' => $data,
                'columns' => $columns,
                'rowCount' => count($data),
                'executionTime' => round($executionTime, 2),
            ];
        } finally {
            $this->databaseManager->purge('dashboard_query');
        }
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
