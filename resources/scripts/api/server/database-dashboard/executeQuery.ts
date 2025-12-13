import http from '@/api/http';

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

export default async (uuid: string, request: ExecuteQueryRequest): Promise<ExecuteQueryResponse> => {
    const response = await http.post(`/api/client/servers/${uuid}/database/query`, request);
    return response.data.attributes;
};
