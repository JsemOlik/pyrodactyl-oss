import http from '@/api/http';

export interface CreateDatabaseRequest {
    name: string;
    username?: string;
    password?: string;
    remote?: string;
}

export interface CreateDatabaseResponse {
    name: string;
    created: boolean;
    username?: string;
    password?: string;
}

export default async (uuid: string, data: CreateDatabaseRequest): Promise<CreateDatabaseResponse> => {
    const response = await http.post(`/api/client/servers/${uuid}/database/databases`, data);
    return response.data.attributes;
};
