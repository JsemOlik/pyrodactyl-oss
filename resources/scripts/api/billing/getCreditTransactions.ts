import http from '@/api/http';

export interface CreditTransaction {
    id: number;
    type: 'purchase' | 'deduction' | 'refund' | 'renewal' | 'adjustment';
    amount: number;
    balance_before: number;
    balance_after: number;
    description: string | null;
    subscription_id: number | null;
    reference_id: string | null;
    metadata: Record<string, any> | null;
    created_at: string;
}

export interface CreditTransactionsResponse {
    object: 'list';
    data: CreditTransaction[];
}

export default async (params?: { limit?: number; type?: string }): Promise<CreditTransactionsResponse> => {
    const { data } = await http.get('/api/client/billing/credits/transactions', {
        params,
    });
    return data;
};
