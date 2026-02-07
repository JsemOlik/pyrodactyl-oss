import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default (uuid: string, database: string, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.delete(`/api/client/servers/${type}/${uuid}/databases/${database}`)
            .then(() => resolve())
            .catch(reject);
    });
};
