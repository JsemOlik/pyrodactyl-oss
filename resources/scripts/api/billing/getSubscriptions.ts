import http from '@/api/http';

export interface Subscription {
    object: string;
    attributes: {
        id: number;
        stripe_id: string;
        status: 'active' | 'trialing' | 'past_due' | 'canceled' | 'incomplete' | 'paused';
        stripe_status: string;
        plan_name: string;
        price_amount: number;
        currency: string;
        interval: 'month' | 'quarter' | 'half-year' | 'year';
        server_name: string | null;
        server_uuid: string | null;
        next_renewal_at: string | null;
        ends_at: string | null;
        trial_ends_at: string | null;
        can_cancel: boolean;
        can_resume: boolean;
        created_at: string;
        updated_at: string;
    };
}

export default (): Promise<Subscription[]> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/billing/subscriptions')
            .then(({ data }) => resolve(data.data))
            .catch(reject);
    });
};

