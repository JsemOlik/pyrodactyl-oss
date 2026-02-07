import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default (uuid: string, userId: string, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.delete(`/api/client/servers/${type}/${uuid}/users/${userId}`)
            .then(() => resolve())
            .catch(reject);
    });
};
