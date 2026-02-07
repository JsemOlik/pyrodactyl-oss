import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

interface DeleteBackupResponse {
    job_id: string;
    status: string;
    message: string;
}

export default async (uuid: string, backup: string): Promise<{ jobId: string; status: string; message: string }> => {
    const response = await http.delete<DeleteBackupResponse>(
        `/api/client/servers/${getGlobalDaemonType()}/${uuid}/backups/${backup}`,
    );

    return {
        jobId: response.data.job_id,
        status: response.data.status,
        message: response.data.message,
    };
};
