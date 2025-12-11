import http from '@/api/http';

export interface TableDataResponse {
    data: Record<string, any>[];
    columns: string[];
    pagination: {
        total: number;
        perPage: number;
        currentPage: number;
        lastPage: number;
    };
}

export default async (
    uuid: string,
    tableName: string,
    page: number = 1,
    perPage: number = 50,
    databaseName?: string,
): Promise<TableDataResponse> => {
    const params: any = { table: tableName, page, per_page: perPage };
    if (databaseName) {
        params.database = databaseName;
    }
    const response = await http.get(`/api/client/servers/${uuid}/database/tables/data`, { params });
    return response.data.attributes;
};
