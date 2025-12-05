import http from '@/api/http';

export interface ResumeSubscriptionResponse {
    message: string;
}

export default (subscriptionId: number): Promise<ResumeSubscriptionResponse> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/billing/subscriptions/${subscriptionId}/resume`)
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};
