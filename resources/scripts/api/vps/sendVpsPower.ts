import http from '@/api/http';

export type VpsPowerSignal = 'start' | 'stop' | 'restart' | 'kill';

export default (uuid: string, signal: VpsPowerSignal): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/vps-servers/${uuid}/power`, { signal })
            .then(() => resolve())
            .catch(reject);
    });
};
