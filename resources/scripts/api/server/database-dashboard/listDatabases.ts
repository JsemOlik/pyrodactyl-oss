import http from '@/api/http';

export interface DatabaseInfo {
    name: string;
    size: number;
    sizeFormatted: string;
    tableCount: number;
}

export default async (uuid: string): Promise<DatabaseInfo[]> => {
    const response = await http.get(`/api/client/servers/${uuid}/database/databases`);
    return (response.data.data || []).map((item: any) => item.attributes);
};
