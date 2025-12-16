import http from '@/api/http';

import { Ticket } from './getTickets';

export interface UpdateTicketData {
    status?: 'open' | 'in_progress' | 'resolved' | 'closed';
    priority?: 'low' | 'medium' | 'high' | 'urgent';
}

export default (ticketId: number, data: UpdateTicketData): Promise<Ticket> => {
    return new Promise((resolve, reject) => {
        http.patch(`/api/client/tickets/${ticketId}`, data)
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};
