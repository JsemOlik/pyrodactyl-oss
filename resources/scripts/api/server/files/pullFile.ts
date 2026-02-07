import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

interface PullFileOptions {
    url: string;
    directory?: string;
    filename?: string;
    use_header?: boolean;
    foreground?: boolean;
}

export default (uuid: string, options: PullFileOptions, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${type}/${uuid}/files/pull`, options)
            .then(() => resolve())
            .catch(reject);
    });
};
