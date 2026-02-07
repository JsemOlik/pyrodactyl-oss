import http from '@/api/http';
import { ServerBackup } from '@/api/server/types';
import { rawDataToServerBackup } from '@/api/transformers';

import { getGlobalDaemonType } from '../getServer';

export default async (uuid: string, backup: string, name: string, daemonType?: string): Promise<ServerBackup> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const { data } = await http.post(`/api/client/servers/${type}/${uuid}/backups/${backup}/rename`, {
        name: name,
    });

    return rawDataToServerBackup(data);
};
