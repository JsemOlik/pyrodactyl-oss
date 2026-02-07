import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface DatabaseMetrics {
    size: number;
    sizeFormatted: string;
    tableCount: number;
    connectionCount: number;
    maxConnections: number;
    queryCount: number;
    uptime: number;
}

export default async (uuid: string, daemonType?: string): Promise<DatabaseMetrics> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const response = await http.get(`/api/client/servers/${type}/${uuid}/database/metrics`);
    return response.data.attributes;
};
