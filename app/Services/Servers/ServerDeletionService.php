<?php

namespace Pterodactyl\Services\Servers;

use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Databases\DatabaseManagementService;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Pterodactyl\Exceptions\Service\Backup\BackupLockedException;

class ServerDeletionService
{
    protected bool $force = false;

    /**
     * ServerDeletionService constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private DaemonServerRepository $daemonServerRepository,
        private DatabaseManagementService $databaseManagementService,
    ) {
    }

    /**
     * Set if the server should be forcibly deleted from the panel (ignoring daemon errors) or not.
     */
    public function withForce(bool $bool = true): self
    {
        $this->force = $bool;

        return $this;
    }

    /**
     * Delete a server from the panel and remove any associated databases from hosts.
     *
     * @throws \Throwable
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function handle(Server $server): void
    {
        try {
            $this->daemonServerRepository->setServer($server)->delete();
        } catch (DaemonConnectionException $exception) {
            // If there is an error not caused a 404 error and this isn't a forced delete,
            // go ahead and bail out. We specifically ignore a 404 since that can be assumed
            // to be a safe error, meaning the server doesn't exist at all on Wings so there
            // is no reason we need to bail out from that.
            if (!$this->force && $exception->getStatusCode() !== Response::HTTP_NOT_FOUND) {
                throw $exception;
            }

            Log::warning($exception);
        }

        $this->connection->transaction(function () use ($server) {
            // Handle subscription cancellation if server has a subscription
            if ($server->subscription_id) {
                try {
                    $subscription = \Pterodactyl\Models\Subscription::find($server->subscription_id);
                    if ($subscription && $subscription->stripe_id) {
                        \Stripe\Stripe::setApiKey(config('cashier.secret'));
                        $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_id);
                        
                        // Cancel the subscription immediately
                        if (in_array($stripeSubscription->status, ['active', 'trialing'])) {
                            \Stripe\Subscription::update($subscription->stripe_id, [
                                'cancel_at_period_end' => false,
                            ]);
                            \Stripe\Subscription::cancel($subscription->stripe_id);
                            
                            Log::info('Canceled Stripe subscription after server deletion', [
                                'server_id' => $server->id,
                                'subscription_id' => $subscription->id,
                                'stripe_id' => $subscription->stripe_id,
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the deletion operation
                    Log::warning('Failed to cancel Stripe subscription during server deletion', [
                        'server_id' => $server->id,
                        'subscription_id' => $server->subscription_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Delete all backups associated with this server
            foreach ($server->backups as $backup) {
                try {
                    // Simply delete the backup record
                    // note: this used to be more complex but Elytra's changes have made a lot of logic here redundant
                    // so this whole thing really needs a refactor now. THAT BEING SAID I HAVE NOT TESTED LOCAL IN A MINUTE!
                    // - ellie 
                    $backup->delete();
                } catch (\Exception $exception) {
                    if (!$this->force) {
                        throw $exception;
                    }

                    // If we can't delete the backup from storage, at least remove the database record
                    // to prevent orphaned backup entries
                    $backup->delete();

                    Log::warning('Failed to delete backup during server deletion', [
                        'backup_id' => $backup->id,
                        'backup_uuid' => $backup->uuid,
                        'server_id' => $server->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }

            foreach ($server->databases as $database) {
                try {
                    $this->databaseManagementService->delete($database);
                } catch (\Exception $exception) {
                    if (!$this->force) {
                        throw $exception;
                    }

                    // Oh well, just try to delete the database entry we have from the database
                    // so that the server itself can be deleted. This will leave it dangling on
                    // the host instance, but we couldn't delete it anyways so not sure how we would
                    // handle this better anyways.
                    //
                    // @see https://github.com/pterodactyl/panel/issues/2085
                    $database->delete();

                    Log::warning($exception);
                }
            }

            $server->delete();
        });
    }
}
