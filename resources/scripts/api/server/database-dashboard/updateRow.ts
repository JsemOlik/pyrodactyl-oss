import http from '@/api/http';

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
    const response = await http.put(`/api/client/servers/${uuid}/database/tables/data`, request);
    return response.data.attributes;
};
