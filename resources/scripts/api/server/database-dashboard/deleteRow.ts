import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface DeleteRowRequest {
    table: string;
    where: Record<string, any>;
    database?: string;
}

export default async (uuid: string, request: DeleteRowRequest, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    await http.delete(`/api/client/servers/${type}/${uuid}/database/tables/data`, {
        data: request,
    });
};
