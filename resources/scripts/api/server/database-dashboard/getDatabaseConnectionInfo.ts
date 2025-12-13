import http from '@/api/http';

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

export default async (uuid: string): Promise<DatabaseConnectionInfo> => {
    const response = await http.get(`/api/client/servers/${uuid}/database/connection`);
    return response.data.attributes;
};
