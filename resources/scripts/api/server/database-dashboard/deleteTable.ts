import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default async (uuid: string, tableName: string, databaseName?: string, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    await http.delete(`/api/client/servers/${type}/${uuid}/database/tables`, {
        data: { table: tableName, database: databaseName },
    });
};
