import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default async (uuid: string, databaseName: string): Promise<void> => {
    await http.delete(`/api/client/servers/${getGlobalDaemonType()}/${uuid}/database/databases`, {
        data: { name: databaseName },
    });
};
