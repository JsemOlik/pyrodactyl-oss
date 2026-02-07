import http from '@/api/http';
import { Allocation } from '@/api/server/getServer';
import { getGlobalDaemonType } from '@/api/server/getServer';
import { rawDataToServerAllocation } from '@/api/transformers';

export default async (uuid: string, id: number, daemonType?: string): Promise<Allocation> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const { data } = await http.post(
        `/api/client/servers/${type}/${uuid}/network/allocations/${id}/primary`,
    );

    return rawDataToServerAllocation(data);
};
