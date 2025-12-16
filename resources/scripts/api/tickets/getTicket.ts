import http from '@/api/http';

import { Ticket } from './getTickets';

export default (ticketId: number): Promise<any> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/tickets/${ticketId}`, {
            params: {
                include: 'user,server,subscription,replies.user',
            },
        })
            .then(({ data }) => {
                // Handle JSON:API format response
                // The response structure is:
                // { data: { type, id, attributes, relationships }, included: [...] }
                resolve(data);
            })
            .catch(reject);
    });
};
