import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface RetryBackupResponse {
    message: string;
    job_id: string;
    status: string;
    progress: number;
}

export default async (uuid: string, backupUuid: string, daemonType?: string): Promise<RetryBackupResponse> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const { data } = await http.post(`/api/client/servers/${type}/${uuid}/backups/${backupUuid}/retry`);

    return data;
};
