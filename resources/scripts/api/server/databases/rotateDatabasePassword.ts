import http from '@/api/http';
import { ServerDatabase, rawDataToServerDatabase } from '@/api/server/databases/getServerDatabases';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default (uuid: string, database: string, daemonType?: string): Promise<ServerDatabase> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.post(`/api/client/${type}/servers/${uuid}/databases/${database}/rotate-password`)
            .then((response) => resolve(rawDataToServerDatabase(response.data.attributes)))
            .catch(reject);
    });
};
