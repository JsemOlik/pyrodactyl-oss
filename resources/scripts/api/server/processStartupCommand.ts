import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default (uuid: string, command: string, daemonType?: string): Promise<string> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${type}/${uuid}/startup/command/process`, { command })
            .then(({ data }) => resolve(data.processed_command))
            .catch(reject);
    });
};
