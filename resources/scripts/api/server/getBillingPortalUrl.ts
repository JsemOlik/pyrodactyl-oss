import http from '@/api/http';

export interface BillingPortalUrlResponse {
    url: string;
}

export default (serverUuid: string): Promise<BillingPortalUrlResponse> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${serverUuid}/billing-portal`)
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};

