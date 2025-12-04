import http from '@/api/http';

export interface CancelSubscriptionResponse {
    message: string;
    ends_at?: string | null;
}

export default (subscriptionId: number): Promise<CancelSubscriptionResponse> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/billing/subscriptions/${subscriptionId}/cancel`)
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};

