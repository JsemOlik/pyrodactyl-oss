import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';
import { ServerEggVariable } from '@/api/server/types';
import { rawDataToServerEggVariable } from '@/api/transformers';

export default async (uuid: string, key: string, value: string, daemonType?: string): Promise<[ServerEggVariable, string]> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const { data } = await http.put(`/api/client/servers/${type}/${uuid}/startup/variable`, {
        key,
        value,
    });

    return [rawDataToServerEggVariable(data), data.meta.startup_command];
};
