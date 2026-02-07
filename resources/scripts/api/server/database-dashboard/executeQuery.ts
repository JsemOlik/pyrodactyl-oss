import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface ExecuteQueryRequest {
    query: string;
    database?: string;
}

export interface ExecuteQueryResponse {
    success: boolean;
    data: Record<string, any>[];
    columns: string[];
    rowCount: number;
    executionTime: number;
}

export default async (
    uuid: string,
    request: ExecuteQueryRequest,
    daemonType?: string,
): Promise<ExecuteQueryResponse> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const response = await http.post(`/api/client/servers/${type}/${uuid}/database/query`, request);
    return response.data.attributes;
};
