import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default (uuid: string, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${type}/${uuid}/settings/reinstall`)
            .then(() => resolve())
            .catch(reject);
    });
};
