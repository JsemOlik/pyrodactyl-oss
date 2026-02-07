import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

interface Response {
    token: string;
    socket: string;
}

export default (server: string, daemonType?: string): Promise<Response> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';

    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${type}/${server}/websocket`)
            .then(({ data }) =>
                resolve({
                    token: data.data.token,
                    socket: data.data.socket,
                }),
            )
            .catch(reject);
    });
};
