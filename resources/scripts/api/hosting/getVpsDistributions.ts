import http from '@/api/http';

export interface VpsDistribution {
    id: string;
    name: string;
    description?: string;
}

export default (): Promise<VpsDistribution[]> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/hosting/vps-distributions')
            .then(({ data }) => resolve(data.data || data))
            .catch(reject);
    });
};

