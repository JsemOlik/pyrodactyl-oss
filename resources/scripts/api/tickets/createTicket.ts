import http from '@/api/http';
import { Ticket } from './getTickets';

export interface CreateTicketData {
    subject: string;
    description: string;
    category: 'billing' | 'technical' | 'general' | 'other';
    priority?: 'low' | 'medium' | 'high' | 'urgent';
    server_id?: number | null;
    subscription_id?: number | null;
}

export default (data: CreateTicketData): Promise<Ticket> => {
    return new Promise((resolve, reject) => {
        http.post('/api/client/tickets', data)
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};
