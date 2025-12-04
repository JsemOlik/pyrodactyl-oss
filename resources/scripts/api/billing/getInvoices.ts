import http from '@/api/http';
import { BillingInvoice } from '@/components/dashboard/BillingInvoiceRow';

export default (): Promise<BillingInvoice[]> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/billing/invoices')
            .then(({ data }) => resolve(data.data || []))
            .catch(reject);
    });
};

