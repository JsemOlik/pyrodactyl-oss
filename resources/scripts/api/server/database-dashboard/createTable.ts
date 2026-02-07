import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface TableColumn {
    name: string;
    type: string;
    length?: number;
    precision?: number;
    scale?: number;
    nullable?: boolean;
    defaultValue?: string | null;
    unsigned?: boolean;
    autoIncrement?: boolean;
    primaryKey?: boolean;
    comment?: string;
}

export interface CreateTableRequest {
    name: string;
    columns: TableColumn[];
    database?: string;
    engine?: string;
    collation?: string;
}

export interface CreateTableResponse {
    name: string;
    created: boolean;
}

export default async (uuid: string, data: CreateTableRequest, daemonType?: string): Promise<CreateTableResponse> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const response = await http.post(`/api/client/servers/${type}/${uuid}/database/tables`, data);
    return response.data.attributes;
};
