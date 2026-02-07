import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default async (uuid: string, daemonType?: string): Promise<string> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const { data } = await http.get(`/api/client/servers/${type}/${uuid}/startup/command/default`);

    return data.default_startup_command;
};
