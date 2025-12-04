import http from '@/api/http';

export interface CheckoutSessionData {
    plan_id?: number;
    custom?: boolean;
    memory?: number;
    interval?: string;
    nest_id: number;
    egg_id: number;
    server_name: string;
    server_description?: string;
}

export interface CheckoutSessionResponse {
    checkout_url: string;
    session_id: string;
}

export default (data: CheckoutSessionData): Promise<CheckoutSessionResponse> => {
    return new Promise((resolve, reject) => {
        http.post('/api/client/hosting/checkout', data)
            .then(({ data: responseData }) => {
                resolve({
                    checkout_url: responseData.data.checkout_url,
                    session_id: responseData.data.session_id,
                });
            })
            .catch(reject);
    });
};
