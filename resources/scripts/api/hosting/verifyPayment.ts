import http from '@/api/http';

export interface PaymentVerificationResponse {
    status: 'pending' | 'processing' | 'completed';
    payment_status?: string;
    message: string;
    server?: {
        id: number;
        uuid: string;
        name: string;
    };
}

export default (sessionId: string): Promise<PaymentVerificationResponse> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/hosting/verify-payment', { params: { session_id: sessionId } })
            .then(({ data: responseData }) => {
                resolve({
                    status: responseData.status,
                    payment_status: responseData.payment_status,
                    message: responseData.message,
                    server: responseData.server,
                });
            })
            .catch(reject);
    });
};

