import http from '@/api/http';

export default async (uuid: string, databaseName: string): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/database/databases`, {
        data: { name: databaseName },
    });
};
