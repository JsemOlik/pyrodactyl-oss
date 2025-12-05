import http from '@/api/http';

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
            proxy_port?: number | null;
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

export const getSubdomainInfo = (uuid: string): Promise<SubdomainInfo> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/subdomain`)
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};

export const setSubdomain = (uuid: string, subdomain: string, domainId: number, proxyPort?: number | null): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/subdomain`, {
            subdomain,
            domain_id: domainId,
            proxy_port: proxyPort || null,
        })
            .then(() => resolve())
            .catch(reject);
    });
};

export const deleteSubdomain = (uuid: string): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.delete(`/api/client/servers/${uuid}/subdomain`)
            .then(() => resolve())
            .catch(reject);
    });
};

export const checkSubdomainAvailability = (
    uuid: string,
    subdomain: string,
    domainId: number,
): Promise<AvailabilityResponse> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/subdomain/check-availability`, {
            subdomain,
            domain_id: domainId,
        })
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};
