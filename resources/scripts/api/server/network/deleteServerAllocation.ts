import http from '@/api/http';
import { Allocation } from '@/api/server/getServer';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default async (uuid: string, id: number, daemonType?: string): Promise<Allocation> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return await http.delete(`/api/client/servers/${type}/${uuid}/network/allocations/${id}`);
