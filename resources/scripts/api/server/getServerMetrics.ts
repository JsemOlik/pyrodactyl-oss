import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface ServerMetricPoint {
    t: string;
    cpu: number;
    memory_bytes: number;
    network_rx_bytes: number;
    network_tx_bytes: number;
}

export interface ServerMetricsResponse {
    from: string;
    to: string;
    points: ServerMetricPoint[];
}

export const getServerMetrics = async (
    uuid: string,
    window: '5m' | '15m' | '1h' | '6h' | '24h',
): Promise<ServerMetricsResponse> => {
    const type = getGlobalDaemonType() || 'elytra';

    const { data } = await http.get<ServerMetricsResponse>(
        `/api/client/servers/${type}/${uuid}/metrics`,
        {
            params: { window },
        },
    );

    return data;
};
