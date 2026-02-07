import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';
import { rawDataToFileObject } from '@/api/transformers';

export interface FileObject {
    key: string;
    name: string;
    mode: string;
    modeBits: string;
    size: number;
    isFile: boolean;
    isSymlink: boolean;
    mimetype: string;
    createdAt: Date;
    modifiedAt: Date;
    isArchiveType: () => boolean;
    isEditable: () => boolean;
}

export default async (uuid: string, directory?: string, daemonType?: string): Promise<FileObject[]> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    const { data } = await http.get(`/api/client/servers/${type}/${uuid}/files/list`, {
        params: { directory: directory ?? '/' },
    });

    const files = (data.data || []).map(rawDataToFileObject);

    if (files.length > 500) {
        files.length = 500;
    }

    return files;
};
