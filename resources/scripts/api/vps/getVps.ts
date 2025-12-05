import http, { FractalResponseData } from '@/api/http';
import { Vps, VpsLimits, VpsNetwork, VpsProxmox } from '@/api/vps/types';

export const rawDataToVpsObject = ({ attributes: data }: FractalResponseData): Vps => ({
    id: data.identifier,
    internalId: data.internal_id,
    uuid: data.uuid,
    name: data.name,
    description: data.description || null,
    status: data.status,
    isSuspended: data.is_suspended,
    isRunning: data.is_running,
    isStopped: data.is_stopped,
    isInstalled: data.is_installed,
    limits: {
        memory: data.limits.memory,
        disk: data.limits.disk,
        cpu_cores: data.limits.cpu_cores,
        cpu_sockets: data.limits.cpu_sockets,
    } as VpsLimits,
    proxmox: {
        vm_id: data.proxmox.vm_id,
        node: data.proxmox.node,
        storage: data.proxmox.storage,
    } as VpsProxmox,
    network: {
        ip_address: data.network.ip_address,
        ipv6_address: data.network.ipv6_address,
    } as VpsNetwork,
    distribution: data.distribution,
    installedAt: data.installed_at ? new Date(data.installed_at) : null,
    createdAt: new Date(data.created_at),
    updatedAt: new Date(data.updated_at),
});

export default (uuid: string): Promise<Vps> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/vps-servers/${uuid}`)
            .then(({ data }) => resolve(rawDataToVpsObject(data)))
            .catch(reject);
    });
};
