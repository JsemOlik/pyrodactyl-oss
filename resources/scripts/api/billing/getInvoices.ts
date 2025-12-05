import { BillingInvoice } from '@/components/dashboard/BillingInvoiceRow';

import http from '@/api/http';

export default (): Promise<BillingInvoice[]> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/billing/invoices')
            .then(({ data }) => resolve(data.data || []))
            .catch(reject);
    });
};
