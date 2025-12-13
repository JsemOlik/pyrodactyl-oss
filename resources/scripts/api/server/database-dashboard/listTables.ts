import http from '@/api/http';

export interface TableInfo {
    name: string;
    size: number;
    sizeFormatted: string;
    rowCount: number;
    engine: string;
    collation: string;
}

export default async (uuid: string, databaseName?: string): Promise<TableInfo[]> => {
    const params = databaseName ? { database: databaseName } : undefined;
    const response = await http.get(`/api/client/servers/${uuid}/database/tables`, { params });
    return (response.data.data || []).map((item: any) => item.attributes);
};
