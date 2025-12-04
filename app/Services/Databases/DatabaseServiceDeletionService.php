<?php

namespace Pterodactyl\Services\Databases;

use Illuminate\Http\Response;
use Pterodactyl\Models\DatabaseService;
use Pterodactyl\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\ConnectionInterface;
use Pterodactyl\Repositories\Wings\DaemonDatabaseServiceRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

class DatabaseServiceDeletionService
{
    protected bool $force = false;

    /**
     * DatabaseServiceDeletionService constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private DaemonDatabaseServiceRepository $daemonDatabaseServiceRepository,
    ) {
    }

    /**
     * Set if the database service should be forcibly deleted from the panel (ignoring daemon errors) or not.
     */
    public function withForce(bool $bool = true): self
    {
        $this->force = $bool;

        return $this;
    }

    /**
     * Delete a database service from the panel.
     *
     * @throws \Throwable
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function handle(DatabaseService $databaseService): void
    {
        try {
            $this->daemonDatabaseServiceRepository->setDatabaseService($databaseService)->delete();
        } catch (DaemonConnectionException $exception) {
            // If there is an error not caused a 404 error and this isn't a forced delete,
            // go ahead and bail out. We specifically ignore a 404 since that can be assumed
            // to be a safe error, meaning the database service doesn't exist at all on Wings so there
            // is no reason we need to bail out from that.
            if (!$this->force && $exception->getStatusCode() !== Response::HTTP_NOT_FOUND) {
                throw $exception;
            }

            Log::warning($exception);
        }

        $this->connection->transaction(function () use ($databaseService) {
            // Handle subscription cancellation if database service has a subscription
            if ($databaseService->subscription_id) {
                try {
                    $subscription = Subscription::find($databaseService->subscription_id);
                    if ($subscription && $subscription->stripe_id) {
                        \Stripe\Stripe::setApiKey(config('cashier.secret'));
                        $stripeSubscription = \Stripe\Subscription::retrieve($subscription->stripe_id);

                        // Cancel the subscription immediately
                        if (in_array($stripeSubscription->status, ['active', 'trialing'])) {
                            \Stripe\Subscription::update($subscription->stripe_id, [
                                'cancel_at_period_end' => false,
                            ]);
                            \Stripe\Subscription::cancel($subscription->stripe_id);

                            Log::info('Canceled Stripe subscription after database service deletion', [
                                'database_service_id' => $databaseService->id,
                                'subscription_id' => $subscription->id,
                                'stripe_id' => $subscription->stripe_id,
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the deletion operation
                    Log::warning('Failed to cancel Stripe subscription during database service deletion', [
                        'database_service_id' => $databaseService->id,
                        'subscription_id' => $databaseService->subscription_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Delete all backups associated with this database service
            foreach ($databaseService->backups as $backup) {
                try {
                    $backup->delete();
                } catch (\Exception $exception) {
                    if (!$this->force) {
                        throw $exception;
                    }

                    // If we can't delete the backup from storage, at least remove the database record
                    // to prevent orphaned backup entries
                    $backup->delete();

                    Log::warning('Failed to delete backup during database service deletion', [
                        'backup_id' => $backup->id,
                        'backup_uuid' => $backup->uuid,
                        'database_service_id' => $databaseService->id,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }

            $databaseService->delete();
        });
    }
}

