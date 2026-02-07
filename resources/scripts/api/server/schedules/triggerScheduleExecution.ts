import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default async (server: string, schedule: number, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return await http.post(`/api/client/servers/${type}/${server}/schedules/${schedule}/execute`);
};
