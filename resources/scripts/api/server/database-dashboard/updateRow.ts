import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface UpdateRowRequest {
    table: string;
    data: Record<string, any>;
    where: Record<string, any>;
    database?: string;
}

export interface UpdateRowResponse {
    success: boolean;
    affected: number;
}

export default async (uuid: string, request: UpdateRowRequest, daemonType?: string): Promise<UpdateRowResponse> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const response = await http.put(
        `/api/client/servers/${type}/${uuid}/database/tables/data`,
        request,
    );
    return response.data.attributes;
};
