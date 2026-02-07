import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface DeleteRowRequest {
    table: string;
    where: Record<string, any>;
    database?: string;
}

export default async (uuid: string, request: DeleteRowRequest): Promise<void> => {
    await http.delete(`/api/client/servers/${getGlobalDaemonType()}/${uuid}/database/tables/data`, {
        data: request,
    });
};
