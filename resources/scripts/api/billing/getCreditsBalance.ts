import http from '@/api/http';

export interface CreditsBalanceResponse {
    object: string;
    data: {
        balance: number;
        currency: string;
    };
}

export default (): Promise<CreditsBalanceResponse> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/billing/credits/balance')
            .then(({ data: responseData }) => {
                resolve({
                    object: responseData.object,
                    data: responseData.data,
                });
            })
            .catch(reject);
    });
};
