import http from '@/api/http';

export interface HostingPlan {
    object: string;
    attributes: {
        id: number;
        name: string;
        description: string | null;
        price: number;
        currency: string;
        interval: string;
        memory: number | null;
        disk: number | null;
        cpu: number | null;
        io: number | null;
        swap: number | null;
        is_custom: boolean;
        sort_order: number;
        pricing: {
            monthly: number;
            quarterly: number;
            half_year: number;
            yearly: number;
        };
        created_at: string;
        updated_at: string;
    };
}

export interface CustomPlanCalculation {
    memory: number;
    interval: string;
    price: number;
    price_per_month: number;
    currency: string;
    discount_months: number;
}

export default (): Promise<HostingPlan[]> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/hosting/plans')
            .then(({ data }) => resolve(data.data))
            .catch(reject);
    });
};

export const calculateCustomPlan = (memory: number, interval: string = 'month'): Promise<CustomPlanCalculation> => {
    return new Promise((resolve, reject) => {
        http.post('/api/client/hosting/calculate-custom-plan', { memory, interval })
            .then(({ data }) => resolve(data.data))
            .catch(reject);
    });
};

