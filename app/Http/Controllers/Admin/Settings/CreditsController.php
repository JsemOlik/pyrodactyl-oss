<?php

namespace Pterodactyl\Http\Controllers\Admin\Settings;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\View\Factory as ViewFactory;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\User;
use Pterodactyl\Models\CreditTransaction;
use Pterodactyl\Services\Credits\CreditTransactionService;
use Illuminate\Support\Facades\Log;

class CreditsController extends Controller
{
    public function __construct(
        private ViewFactory $view,
        private CreditTransactionService $creditTransactionService
    ) {
    }

    /**
     * Get users with credits for the admin credits management page.
     */
    public function getUsers(Request $request): JsonResponse
    {
        $search = $request->input('search', '');
        $perPage = (int) $request->input('per_page', 25);
        
        $query = User::query()
            ->select('id', 'username', 'email', 'name_first', 'name_last', 'credits_balance', 'created_at')
            ->orderBy('credits_balance', 'desc')
            ->orderBy('username', 'asc');
        
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('name_first', 'like', "%{$search}%")
                  ->orWhere('name_last', 'like', "%{$search}%");
            });
        }
        
        $users = $query->paginate($perPage);
        
        return response()->json([
            'object' => 'list',
            'data' => $users->items(),
            'meta' => [
                'pagination' => [
                    'total' => $users->total(),
                    'count' => $users->count(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'total_pages' => $users->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get transaction history for a user.
     */
    public function getUserTransactions(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        
        $transactions = CreditTransaction::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
        
        return response()->json([
            'object' => 'list',
            'data' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'amount' => (float) $transaction->amount,
                    'balance_before' => (float) $transaction->balance_before,
                    'balance_after' => (float) $transaction->balance_after,
                    'description' => $transaction->description,
                    'subscription_id' => $transaction->subscription_id,
                    'reference_id' => $transaction->reference_id,
                    'metadata' => $transaction->metadata,
                    'created_at' => $transaction->created_at->toIso8601String(),
                ];
            }),
        ]);
    }

    /**
     * Adjust a user's credits balance.
     */
    public function adjustCredits(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric',
            'description' => 'required|string|max:255',
        ]);

        $user = User::findOrFail($userId);
        $amount = (float) $request->input('amount');
        $description = $request->input('description');

        try {
            $this->creditTransactionService->recordAdjustment(
                $user,
                $amount,
                $description,
                [
                    'admin_adjusted' => true,
                    'admin_user_id' => auth()->id(),
                ]
            );

            Log::info('Admin adjusted user credits', [
                'admin_id' => auth()->id(),
                'user_id' => $userId,
                'amount' => $amount,
                'description' => $description,
            ]);

            return response()->json([
                'object' => 'credit_adjustment',
                'data' => [
                    'user_id' => $userId,
                    'new_balance' => (float) $user->fresh()->credits_balance,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to adjust user credits', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'errors' => [[
                    'code' => 'AdjustmentFailed',
                    'status' => '500',
                    'detail' => 'Failed to adjust credits: ' . $e->getMessage(),
                ]],
            ], 500);
        }
    }
}
