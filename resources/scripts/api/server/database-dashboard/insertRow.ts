import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface InsertRowRequest {
    table: string;
    data: Record<string, any>;
    database?: string;
}

export interface InsertRowResponse {
    success: boolean;
    insertId: number | string;
}

export default async (uuid: string, request: InsertRowRequest, daemonType?: string): Promise<InsertRowResponse> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const response = await http.post(`/api/client/servers/${type}/${uuid}/database/tables/data`, request);
    return response.data.attributes;
};
