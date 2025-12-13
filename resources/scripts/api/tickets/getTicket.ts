import http from '@/api/http';
import { Ticket } from './getTickets';

export default (ticketId: number): Promise<Ticket> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/tickets/${ticketId}`, {
            params: {
                include: 'user,server,subscription,replies.user',
            },
        })
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};
