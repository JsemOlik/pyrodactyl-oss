<?php

namespace Pterodactyl\Services\Credits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\User;
use Pterodactyl\Models\CreditTransaction;
use Pterodactyl\Models\Subscription;

class CreditTransactionService
{
    /**
     * Record a credit purchase (adding credits).
     */
    public function recordPurchase(
        User $user,
        float $amount,
        ?string $referenceId = null,
        ?array $metadata = null
    ): CreditTransaction {
        $balanceBefore = (float) $user->credits_balance;
        $user->increment('credits_balance', $amount);
        $user->refresh();
        $balanceAfter = (float) $user->credits_balance;

        return CreditTransaction::create([
            'user_id' => $user->id,
            'type' => CreditTransaction::TYPE_PURCHASE,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => "Purchased {$amount} credits",
            'reference_id' => $referenceId,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Record a credit deduction (spending credits).
     */
    public function recordDeduction(
        User $user,
        float $amount,
        ?string $description = null,
        ?int $subscriptionId = null,
        ?array $metadata = null
    ): CreditTransaction {
        $balanceBefore = (float) $user->credits_balance;
        $user->decrement('credits_balance', $amount);
        $user->refresh();
        $balanceAfter = (float) $user->credits_balance;

        return CreditTransaction::create([
            'user_id' => $user->id,
            'type' => CreditTransaction::TYPE_DEDUCTION,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description ?? "Deducted {$amount} credits",
            'subscription_id' => $subscriptionId,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Record a credit refund.
     */
    public function recordRefund(
        User $user,
        float $amount,
        ?string $description = null,
        ?int $subscriptionId = null,
        ?array $metadata = null
    ): CreditTransaction {
        $balanceBefore = (float) $user->credits_balance;
        $user->increment('credits_balance', $amount);
        $user->refresh();
        $balanceAfter = (float) $user->credits_balance;

        return CreditTransaction::create([
            'user_id' => $user->id,
            'type' => CreditTransaction::TYPE_REFUND,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description ?? "Refunded {$amount} credits",
            'subscription_id' => $subscriptionId,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Record a subscription renewal payment.
     */
    public function recordRenewal(
        User $user,
        Subscription $subscription,
        float $amount
    ): CreditTransaction {
        $balanceBefore = (float) $user->credits_balance;
        $user->decrement('credits_balance', $amount);
        $user->refresh();
        $balanceAfter = (float) $user->credits_balance;

        $description = "Subscription renewal for {$subscription->id} - {$amount} credits";

        return CreditTransaction::create([
            'user_id' => $user->id,
            'type' => CreditTransaction::TYPE_RENEWAL,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'subscription_id' => $subscription->id,
            'metadata' => [
                'subscription_stripe_id' => $subscription->stripe_id,
                'billing_interval' => $subscription->billing_interval,
            ],
        ]);
    }

    /**
     * Record an adjustment (manual credit change).
     */
    public function recordAdjustment(
        User $user,
        float $amount,
        string $description,
        ?array $metadata = null
    ): CreditTransaction {
        $balanceBefore = (float) $user->credits_balance;
        
        if ($amount > 0) {
            $user->increment('credits_balance', $amount);
        } else {
            $user->decrement('credits_balance', abs($amount));
        }
        
        $user->refresh();
        $balanceAfter = (float) $user->credits_balance;

        $type = $amount > 0 ? CreditTransaction::TYPE_PURCHASE : CreditTransaction::TYPE_DEDUCTION;

        return CreditTransaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => abs($amount),
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get transaction history for a user.
     */
    public function getTransactionHistory(
        User $user,
        ?int $limit = 50,
        ?string $type = null
    ): \Illuminate\Database\Eloquent\Collection {
        $query = CreditTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($type) {
            $query->where('type', $type);
        }

        return $query->limit($limit)->get();
    }
}
