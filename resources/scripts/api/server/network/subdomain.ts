import http from '@/api/http';
import { getGlobalDaemonType } from '@/api/server/getServer';

export interface SubdomainInfo {
    supported: boolean;
    current_subdomain?: {
        object: string;
        attributes: {
            subdomain: string;
            domain: string;
            domain_id: number;
            full_domain: string;
            is_active: boolean;
        };
    };
    available_domains: Array<{
        id: number;
        name: string;
        is_active: boolean;
    }>;
    message?: string;
}

export interface AvailabilityResponse {
    available: boolean;
    message: string;
}

export const getSubdomainInfo = (uuid: string, daemonType?: string): Promise<SubdomainInfo> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${type}/${uuid}/subdomain`)
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};

export const setSubdomain = (uuid: string, subdomain: string, domainId: number, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${type}/${uuid}/subdomain`, {
            subdomain,
            domain_id: domainId,
        })
            .then(() => resolve())
            .catch(reject);
    });
};

export const deleteSubdomain = (uuid: string, daemonType?: string): Promise<void> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.delete(`/api/client/servers/${type}/${uuid}/subdomain`)
            .then(() => resolve())
            .catch(reject);
    });
};

export const checkSubdomainAvailability = (
    uuid: string,
    subdomain: string,
    domainId: number,
    daemonType?: string,
): Promise<AvailabilityResponse> => {
    const type = daemonType || getGlobalDaemonType() || 'elytra';
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${type}/${uuid}/subdomain/check-availability`, {
            subdomain,
            domain_id: domainId,
        })
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};
