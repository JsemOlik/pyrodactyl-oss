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
