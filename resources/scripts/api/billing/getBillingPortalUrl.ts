import http from '@/api/http';

export interface BillingPortalUrlResponse {
    url: string;
}

export default (subscriptionId: number): Promise<BillingPortalUrlResponse> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/billing/subscriptions/${subscriptionId}/billing-portal`)
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};

