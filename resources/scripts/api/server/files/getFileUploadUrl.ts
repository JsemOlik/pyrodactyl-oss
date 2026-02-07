import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default (uuid: string, daemonType?: string): Promise<string> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${type}/${uuid}/files/upload`)
            .then(({ data }) => resolve(data.attributes.url))
            .catch(reject);
    });
};
