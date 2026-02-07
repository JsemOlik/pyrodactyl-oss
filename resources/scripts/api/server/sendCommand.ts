import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default (uuid: string, command: string): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${getGlobalDaemonType()}/${uuid}/command`, { command })
            .then(() => resolve())
            .catch(reject);
    });
};
