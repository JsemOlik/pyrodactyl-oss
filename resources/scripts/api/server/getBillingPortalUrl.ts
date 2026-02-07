import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface BillingPortalUrlResponse {
    url: string;
}

export default (serverUuid: string, daemonType?: string): Promise<BillingPortalUrlResponse> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${type}/${serverUuid}/billing-portal`)
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};
