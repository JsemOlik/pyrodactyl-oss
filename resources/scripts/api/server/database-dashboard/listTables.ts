import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface TableInfo {
    name: string;
    size: number;
    sizeFormatted: string;
    rowCount: number;
    engine: string;
    collation: string;
}

export default async (uuid: string, databaseName?: string, daemonType?: string): Promise<TableInfo[]> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const params = databaseName ? { database: databaseName } : undefined;
    const response = await http.get(`/api/client/servers/${type}/${uuid}/database/tables`, { params });
    return (response.data.data || []).map((item: any) => item.attributes);
};
