import http from '@/api/http';

export interface AvailableDomain {
    id: number;
    name: string;
    is_active: boolean;
    is_default: boolean;
}

export interface SubdomainAvailabilityResponse {
    object: string;
    attributes: {
        available: boolean;
        message: string;
    };
}

export const getAvailableDomains = (): Promise<AvailableDomain[]> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/hosting/subdomain/domains')
            .then(({ data }) => resolve(data.data))
            .catch(reject);
    });
};

export const checkSubdomainAvailability = (
    subdomain: string,
    domainId: number,
): Promise<SubdomainAvailabilityResponse> => {
    return new Promise((resolve, reject) => {
        http.post('/api/client/hosting/subdomain/check-availability', {
            subdomain,
            domain_id: domainId,
        })
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};
