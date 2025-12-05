import http from '@/api/http';
import { FractalResponseList } from '@/api/http';

interface VpsDistributionResponse {
    object: string;
    attributes: {
        id: string;
        name: string;
        description?: string;
        version?: string;
        is_available?: boolean;
    };
}

export interface VpsDistribution {
    id: string;
    name: string;
    description?: string;
    version?: string;
    isAvailable?: boolean;
}

export default (): Promise<VpsDistribution[]> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/hosting/vps-distributions')
            .then(({ data }) => {
                const response = data as FractalResponseList;
                if (response.object === 'list' && Array.isArray(response.data)) {
                    const distributions: VpsDistribution[] = response.data.map((item: VpsDistributionResponse) => ({
                        id: item.attributes.id,
                        name: item.attributes.name,
                        description: item.attributes.description,
                        version: item.attributes.version,
                        isAvailable: item.attributes.is_available,
                    }));
                    resolve(distributions);
                } else {
                    // Fallback for different response format
                    resolve(data.data || data || []);
                }
            })
            .catch(reject);
    });
};
