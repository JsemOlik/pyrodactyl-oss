<?php

namespace Pterodactyl\Console\Commands\Billing;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Pterodactyl\Models\Subscription;
use Pterodactyl\Services\Credits\CreditTransactionService;
use Pterodactyl\Services\Servers\SuspensionService;

class ProcessCreditsRecurringBillingCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'billing:process-credits-recurring';

    /**
     * The console command description.
     */
    protected $description = 'Process recurring billing for credits-based subscriptions';

    public function __construct(
        private CreditTransactionService $creditTransactionService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Processing credits-based recurring billing...');

        // Get all active credits-based subscriptions that are due for billing
        $dueSubscriptions = Subscription::where('is_credits_based', true)
            ->where('stripe_status', 'active')
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', now())
            ->with(['user', 'servers'])
            ->get();

        if ($dueSubscriptions->isEmpty()) {
            $this->info('No subscriptions due for billing.');
            return Command::SUCCESS;
        }

        $this->info("Found {$dueSubscriptions->count()} subscription(s) due for billing.");

        $processed = 0;
        $failed = 0;
        $suspended = 0;

        foreach ($dueSubscriptions as $subscription) {
            try {
                $user = $subscription->user;
                $billingAmount = (float) $subscription->billing_amount;

                if ($billingAmount <= 0) {
                    $this->warn("Subscription {$subscription->id} has invalid billing amount. Skipping.");
                    $failed++;
                    continue;
                }

                // Check if user has enough credits
                if ($user->credits_balance < $billingAmount) {
                    $this->warn("User {$user->id} does not have enough credits for subscription {$subscription->id}. Suspending subscription.");

                    // Suspend all servers associated with this subscription
                    foreach ($subscription->servers as $server) {
                        try {
                            app(SuspensionService::class)->toggle($server, SuspensionService::ACTION_SUSPEND);
                            Log::info('Server suspended due to insufficient credits', [
                                'server_id' => $server->id,
                                'subscription_id' => $subscription->id,
                                'user_id' => $user->id,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to suspend server', [
                                'server_id' => $server->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // Update subscription status
                    $subscription->update([
                        'stripe_status' => 'past_due',
                    ]);

                    Log::warning('Subscription suspended due to insufficient credits', [
                        'subscription_id' => $subscription->id,
                        'user_id' => $user->id,
                        'required' => $billingAmount,
                        'available' => $user->credits_balance,
                    ]);

                    $suspended++;
                    continue;
                }

                // Deduct credits and record renewal
                $this->creditTransactionService->recordRenewal($user, $subscription, $billingAmount);

                // Calculate next billing date
                $nextBillingAt = $subscription->calculateNextBillingDate();

                // Update subscription
                $subscription->update([
                    'next_billing_at' => $nextBillingAt,
                    'stripe_status' => 'active', // Ensure it's still active
                ]);

                // Unsuspend any suspended servers if they were suspended
                foreach ($subscription->servers as $server) {
                    if ($server->isSuspended()) {
                        try {
                            app(SuspensionService::class)->toggle($server, SuspensionService::ACTION_UNSUSPEND);
                            Log::info('Server unsuspended after successful billing', [
                                'server_id' => $server->id,
                                'subscription_id' => $subscription->id,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to unsuspend server', [
                                'server_id' => $server->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                $this->info("Processed billing for subscription {$subscription->id} - User {$user->id}, Amount: {$billingAmount}");
                $processed++;

                Log::info('Credits-based subscription renewed', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $user->id,
                    'amount' => $billingAmount,
                    'next_billing_at' => $nextBillingAt,
                ]);
            } catch (\Exception $e) {
                $this->error("Failed to process subscription {$subscription->id}: {$e->getMessage()}");
                Log::error('Failed to process credits-based recurring billing', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $failed++;
            }
        }

        $this->info("Processing complete. Processed: {$processed}, Suspended: {$suspended}, Failed: {$failed}");

        return Command::SUCCESS;
    }
}
