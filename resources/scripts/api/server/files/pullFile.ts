import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

interface PullFileOptions {
    url: string;
    directory?: string;
    filename?: string;
    use_header?: boolean;
    foreground?: boolean;
}

export default (uuid: string, options: PullFileOptions): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${getGlobalDaemonType()}/${uuid}/files/pull`, options)
            .then(() => resolve())
            .catch(reject);
    });
};
