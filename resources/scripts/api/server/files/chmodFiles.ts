import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

interface Data {
    file: string;
    mode: string;
}

export default (uuid: string, directory: string, files: Data[], daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${type}/${uuid}/files/chmod`, { root: directory, files })
            .then(() => resolve())
            .catch(reject);
    });
};
