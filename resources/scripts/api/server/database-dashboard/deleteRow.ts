import http from '@/api/http';

export interface DeleteRowRequest {
    table: string;
    where: Record<string, any>;
    database?: string;
}

export default async (uuid: string, request: DeleteRowRequest): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/database/tables/data`, {
        data: request,
    });
};
