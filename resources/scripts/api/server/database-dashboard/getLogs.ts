import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface LogEntry {
    timestamp: string;
    level?: string;
    code?: number | null;
    message?: string;
    query?: string;
    user_host?: string;
    thread_id?: number | null;
    query_time?: number;
    lock_time?: number;
    rows_sent?: number;
    rows_examined?: number;
}

export interface LogsResponse {
    logs: LogEntry[];
    type: string;
    count: number;
}

export default async (
    uuid: string,
    logType: 'error' | 'slow' | 'general' = 'general',
    limit: number = 100,
    databaseName?: string,
): Promise<LogsResponse> => {
    const params: any = { type: logType, limit };
    if (databaseName) {
        params.database = databaseName;
    }
    const response = await http.get(`/api/client/servers/${getGlobalDaemonType()}/${uuid}/database/logs`, { params });
    return response.data.attributes;
};
