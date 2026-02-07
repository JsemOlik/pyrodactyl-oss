import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface ColumnInfo {
    name: string;
    type: string;
    fullType: string;
    nullable: boolean;
    defaultValue: string | null;
    key: string;
    extra: string;
    comment: string | null;
    maxLength: number | null;
    precision: number | null;
    scale: number | null;
}

export interface IndexInfo {
    name: string;
    type: string;
    unique: boolean;
    columns: string[];
}

export interface TableStructure {
    name: string;
    columns: ColumnInfo[];
    indexes: IndexInfo[];
    engine: string;
    collation: string;
    comment: string;
    size: number;
    sizeFormatted: string;
    rowCount: number;
}

export default async (
    uuid: string,
    tableName: string,
    databaseName?: string,
    daemonType?: string,
): Promise<TableStructure> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const params: any = { table: tableName };
    if (databaseName) {
        params.database = databaseName;
    }
    const response = await http.get(`/api/client/servers/${type}/${uuid}/database/tables/structure`, {
        params,
    });
    return response.data.attributes;
};
