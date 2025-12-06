import http, { FractalResponseData, FractalResponseList } from '@/api/http';
import { ServerEggVariable, ServerStatus } from '@/api/server/types';
import { rawDataToServerAllocation, rawDataToServerEggVariable } from '@/api/transformers';

export interface Allocation {
    id: number;
    ip: string;
    alias: string | null;
    port: number;
    notes: string | null;
    isDefault: boolean;
}

export interface ServerSubdomain {
    subdomain: string;
    domain: string;
    domain_id: number;
    full_domain: string;
    record_type: string;
    proxy_port: number | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface Server {
    id: string;
    internalId: number | string;
    uuid: string;
    name: string;
    node: string;
    isNodeUnderMaintenance: boolean;
    status: ServerStatus;
    sftpDetails: {
        ip: string;
        port: number;
    };
    invocation: string;
    dockerImage: string;
    description: string;
    limits: {
        memory: number;
        swap: number;
        disk: number;
        io: number;
        cpu: number;
        threads: string;
    };
    eggFeatures: string[];
    featureLimits: {
        databases: number;
        allocations: number;
        backups: number;
        backupStorageMb: number | null;
    };
    isTransferring: boolean;
    variables: ServerEggVariable[];
    allocations: Allocation[];
    egg: string;
    nest: number;
    active_subdomain?: ServerSubdomain | null;
}

export const rawDataToServerObject = ({ attributes: data }: FractalResponseData): Server => ({
    id: data.identifier,
    internalId: data.internal_id,
    uuid: data.uuid,
    name: data.name,
    node: data.node,
    isNodeUnderMaintenance: data.is_node_under_maintenance,
    status: data.status,
    invocation: data.invocation,
    dockerImage: data.docker_image,
    sftpDetails: {
        ip: data.sftp_details.ip,
        port: data.sftp_details.port,
    },
    description: data.description ? (data.description.length > 0 ? data.description : null) : null,
    limits: { ...data.limits },
    eggFeatures: data.egg_features || [],
    featureLimits: { ...data.feature_limits },
    isTransferring: data.is_transferring,
    variables: ((data.relationships?.variables as FractalResponseList | undefined)?.data || []).map(
        rawDataToServerEggVariable,
    ),
    allocations: ((data.relationships?.allocations as FractalResponseList | undefined)?.data || []).map(
        rawDataToServerAllocation,
    ),
    egg: data.egg,
    nest: data.nest,
    active_subdomain: (() => {
        const subdomainRel = data.relationships?.active_subdomain;
        if (
            subdomainRel &&
            typeof subdomainRel === 'object' &&
            'attributes' in subdomainRel &&
            subdomainRel.attributes &&
            subdomainRel.attributes !== null
        ) {
            const attrs = (subdomainRel as FractalResponseData).attributes;
            return {
                subdomain: attrs.subdomain,
                domain: attrs.domain,
                domain_id: attrs.domain_id,
                full_domain: attrs.full_domain,
                record_type: attrs.record_type,
                proxy_port: attrs.proxy_port,
                is_active: attrs.is_active,
                created_at: attrs.created_at,
                updated_at: attrs.updated_at,
            };
        }
        return null;
    })(),
});

export default (uuid: string): Promise<[Server, string[]]> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}`)
            .then(({ data }) =>
                resolve([
                    rawDataToServerObject(data),

                    data.meta?.is_server_owner ? ['*'] : data.meta?.user_permissions || [],
                ]),
            )
            .catch(reject);
    });
};
