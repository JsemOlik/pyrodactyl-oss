import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';
import { Schedule, rawDataToServerSchedule } from '@/api/server/schedules/getServerSchedules';

export default (uuid: string, schedule: number, daemonType?: string): Promise<Schedule> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${type}/${uuid}/schedules/${schedule}`, {
            params: {
                include: ['tasks'],
            },
        })
            .then(({ data }) => resolve(rawDataToServerSchedule(data.attributes)))
            .catch(reject);
    });
};
