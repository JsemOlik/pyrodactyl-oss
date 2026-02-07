import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default (uuid: string, directory: string, files: string[], daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${type}/${uuid}/files/delete`, { root: directory, files })
            .then(() => resolve())
            .catch(reject);
    });
};
