import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default async (uuid: string, startup: string, daemonType?: string): Promise<string> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const { data } = await http.put(`/api/client/servers/${type}/${uuid}/startup/command`, {
        startup,
    });

    return data.meta.startup_command;
};
