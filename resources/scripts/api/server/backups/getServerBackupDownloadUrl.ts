import http from '@/api/http';

import { getGlobalDaemonType } from '../getServer';

export default async (uuid: string, backup: string, daemonType?: string): Promise<string> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const { data } = await http.get(`/api/client/servers/${type}/${uuid}/backups/${backup}/download`);
    return data.attributes.url;
};
