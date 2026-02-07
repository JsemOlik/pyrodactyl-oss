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

export default async (uuid: string, request: UpdateRowRequest): Promise<UpdateRowResponse> => {
    const response = await http.put(
        `/api/client/servers/${getGlobalDaemonType()}/${uuid}/database/tables/data`,
        request,
    );
    return response.data.attributes;
};
