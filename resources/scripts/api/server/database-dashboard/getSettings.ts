import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface DatabaseSettings {
    database: {
        name: string;
        charset: string;
        collation: string;
    };
    server: {
        [key: string]: string;
    };
}

export default async (uuid: string, databaseName?: string, daemonType?: string): Promise<DatabaseSettings> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const params: any = {};
    if (databaseName) {
        params.database = databaseName;
    }
    const response = await http.get(`/api/client/servers/${type}/${uuid}/database/settings`, {
        params,
    });
    return response.data.attributes;
};
