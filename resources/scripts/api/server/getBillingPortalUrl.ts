import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface BillingPortalUrlResponse {
    url: string;
}

export default (serverUuid: string): Promise<BillingPortalUrlResponse> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${getGlobalDaemonType()}/${serverUuid}/billing-portal`)
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};
