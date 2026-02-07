import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

interface DeleteBackupResponse {
    job_id: string;
    status: string;
    message: string;
}

export default async (uuid: string, backup: string, daemonType?: string): Promise<{ jobId: string; status: string; message: string }> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const response = await http.delete<DeleteBackupResponse>(
        `/api/client/servers/${type}/${uuid}/backups/${backup}`,
    );

    return {
        jobId: response.data.job_id,
        status: response.data.status,
        message: response.data.message,
    };
};
