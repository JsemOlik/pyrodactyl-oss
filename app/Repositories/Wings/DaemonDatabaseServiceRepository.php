<?php

namespace Pterodactyl\Repositories\Wings;

use Webmozart\Assert\Assert;
use Pterodactyl\Models\DatabaseService;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * @method \Pterodactyl\Repositories\Wings\DaemonDatabaseServiceRepository setNode(\Pterodactyl\Models\Node $node)
 * @method \Pterodactyl\Repositories\Wings\DaemonDatabaseServiceRepository setDatabaseService(\Pterodactyl\Models\DatabaseService $databaseService)
 */
class DaemonDatabaseServiceRepository extends DaemonRepository
{
    protected ?DatabaseService $databaseService;

    /**
     * Set the database service model this request is stemming from.
     */
    public function setDatabaseService(DatabaseService $databaseService): self
    {
        $this->databaseService = $databaseService;

        $this->setNode($this->databaseService->node);

        return $this;
    }

    /**
     * Returns details about a database service from the Daemon instance.
     *
     * @throws DaemonConnectionException
     */
    public function getDetails(): array
    {
        Assert::isInstanceOf($this->databaseService, DatabaseService::class);

        try {
            $response = $this->getHttpClient()->get(
                sprintf('/api/servers/%s', $this->databaseService->uuid)
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception, false);
        }

        return json_decode($response->getBody()->__toString(), true);
    }

    /**
     * Creates a new database service on the Wings daemon.
     *
     * @throws DaemonConnectionException
     */
    public function create(bool $startOnCompletion = true): void
    {
        Assert::isInstanceOf($this->databaseService, DatabaseService::class);

        try {
            $this->getHttpClient()->post('/api/servers', [
                'json' => [
                    'uuid' => $this->databaseService->uuid,
                    'start_on_completion' => $startOnCompletion,
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Triggers a database service sync on Wings.
     *
     * @throws DaemonConnectionException
     */
    public function sync(): void
    {
        Assert::isInstanceOf($this->databaseService, DatabaseService::class);

        try {
            $this->getHttpClient()->post("/api/servers/{$this->databaseService->uuid}/sync");
        } catch (GuzzleException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Delete a database service from the daemon.
     *
     * @throws DaemonConnectionException
     */
    public function delete(): void
    {
        Assert::isInstanceOf($this->databaseService, DatabaseService::class);

        try {
            $this->getHttpClient()->delete('/api/servers/' . $this->databaseService->uuid);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Reinstall a database service on the daemon.
     *
     * @throws DaemonConnectionException
     */
    public function reinstall(): void
    {
        Assert::isInstanceOf($this->databaseService, DatabaseService::class);

        try {
            $this->getHttpClient()->post(sprintf(
                '/api/servers/%s/reinstall',
                $this->databaseService->uuid
            ));
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }
}