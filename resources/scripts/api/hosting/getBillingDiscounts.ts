import http from '@/api/http';

export interface BillingDiscounts {
    [categorySlug: string]: {
        month: number;
        quarter: number;
        'half-year': number;
        year: number;
    };
}

export default async (): Promise<BillingDiscounts> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/hosting/billing-discounts')
            .then(({ data }) => {
                resolve(data.data || {});
            })
            .catch(reject);
    });
};
