import http from '@/api/http';
import { VpsMetrics } from '@/api/vps/types';

export default (uuid: string, timeframe: 'hour' | 'day' | 'week' = 'hour'): Promise<VpsMetrics> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/vps-servers/${uuid}/metrics`, {
            params: { timeframe },
        })
            .then(({ data }) => {
                resolve({
                    cpu: {
                        usage_percent: data.data.cpu.usage_percent,
                        cores: data.data.cpu.cores,
                        sockets: data.data.cpu.sockets,
                    },
                    memory: {
                        used_bytes: data.data.memory.used_bytes,
                        total_bytes: data.data.memory.total_bytes,
                        usage_percent: data.data.memory.usage_percent,
                    },
                    disk: {
                        used_bytes: data.data.disk.used_bytes,
                        total_bytes: data.data.disk.total_bytes,
                        usage_percent: data.data.disk.usage_percent,
                    },
                    network: {
                        rx_bytes: data.data.network.rx_bytes,
                        tx_bytes: data.data.network.tx_bytes,
                    },
                    uptime: data.data.uptime,
                    timestamp: data.data.timestamp,
                });
            })
            .catch(reject);
    });
};

