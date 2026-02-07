import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default (uuid: string, schedule: number, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.delete(`/api/client/servers/${type}/${uuid}/schedules/${schedule}`)
            .then(() => resolve())
            .catch(reject);
    });
};
