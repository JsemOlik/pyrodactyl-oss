import http from '@/api/http';

import { Ticket } from './getTickets';

export default (ticketId: number): Promise<Ticket> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/tickets/${ticketId}/resolve`)
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};
