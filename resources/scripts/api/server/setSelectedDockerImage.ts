import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export default async (uuid: string, image: string, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    await http.put(`/api/client/servers/${type}/${uuid}/settings/docker-image`, {
        docker_image: image,
    });
};
