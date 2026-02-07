import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface UpdateSettingsRequest {
    charset?: string;
    collation?: string;
    database?: string;
}

export interface UpdateSettingsResponse {
    success: boolean;
    message: string;
}

export default async (uuid: string, request: UpdateSettingsRequest): Promise<UpdateSettingsResponse> => {
    const response = await http.put(`/api/client/servers/${getGlobalDaemonType()}/${uuid}/database/settings`, request);
    return response.data.attributes;
};
