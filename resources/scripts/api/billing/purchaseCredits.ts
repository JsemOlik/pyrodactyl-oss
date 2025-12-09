import http from '@/api/http';

export interface PurchaseCreditsRequest {
    amount: number;
}

export interface PurchaseCreditsResponse {
    object: string;
    data: {
        checkout_url: string;
        session_id: string;
    };
}

export default (data: PurchaseCreditsRequest): Promise<PurchaseCreditsResponse> => {
    return new Promise((resolve, reject) => {
        http.post('/api/client/billing/credits/purchase', data)
            .then(({ data: responseData }) => {
                resolve({
                    object: responseData.object,
                    data: responseData.data,
                });
            })
            .catch(reject);
    });
};
