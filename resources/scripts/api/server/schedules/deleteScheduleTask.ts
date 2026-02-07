import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default (uuid: string, scheduleId: number, taskId: number, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.delete(`/api/client/servers/${type}/${uuid}/schedules/${scheduleId}/tasks/${taskId}`)
            .then(() => resolve())
            .catch(reject);
    });
};
