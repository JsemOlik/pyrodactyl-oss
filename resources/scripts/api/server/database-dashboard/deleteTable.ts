import http from '@/api/http';

export default async (uuid: string, tableName: string, databaseName?: string): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/database/tables`, {
        data: { table: tableName, database: databaseName },
    });
};
