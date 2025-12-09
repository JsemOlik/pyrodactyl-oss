import http from '@/api/http';

export interface CreditsEnabledResponse {
    object: string;
    data: {
        enabled: boolean;
        currency: string;
    };
}

export default (): Promise<CreditsEnabledResponse> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/billing/credits/enabled')
            .then(({ data: responseData }) => {
                resolve({
                    object: responseData.object,
                    data: responseData.data,
                });
            })
            .catch(reject);
    });
};
