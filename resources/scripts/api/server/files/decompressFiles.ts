import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default async (uuid: string, directory: string, file: string, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    await http.post(
        `/api/client/servers/${type}/${uuid}/files/decompress`,
        { root: directory, file },
        {
            timeout: 300000,
            timeoutErrorMessage:
                'It looks like this archive is taking a long time to be unarchived. Once completed the unarchived files will appear.',
        },
    );
};
