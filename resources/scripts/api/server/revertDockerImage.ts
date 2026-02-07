import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default async (uuid: string, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    await http.post(`/api/client/servers/${type}/${uuid}/settings/docker-image/revert`, {
        confirm: true,
    });
};
