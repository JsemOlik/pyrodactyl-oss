import http from '@/api/http';

export interface InsertRowRequest {
    table: string;
    data: Record<string, any>;
    database?: string;
}

export interface InsertRowResponse {
    success: boolean;
    insertId: number | string;
}

export default async (uuid: string, request: InsertRowRequest): Promise<InsertRowResponse> => {
    const response = await http.post(`/api/client/servers/${uuid}/database/tables/data`, request);
    return response.data.attributes;
};
