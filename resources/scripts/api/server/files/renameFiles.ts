import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

interface Data {
    to: string;
    from: string;
}

export default (uuid: string, directory: string, files: Data[], daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.put(`/api/client/servers/${type}/${uuid}/files/rename`, { root: directory, files })
            .then(() => resolve())
            .catch(reject);
    });
};
