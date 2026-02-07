import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface DatabaseConnectionInfo {
    host: string;
    port: number;
    database: string;
    username: string;
    password?: string;
    connectionStrings: {
        mysql: string;
        pdo: string;
    };
}

export default async (uuid: string, daemonType?: string): Promise<DatabaseConnectionInfo> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const response = await http.get(`/api/client/servers/${type}/${uuid}/database/connection`);
    return response.data.attributes;
};
