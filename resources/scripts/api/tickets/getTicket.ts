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
                const ticket = data.data || data;
                const included = data.included || [];
                
                // If ticket has relationships, extract replies from there
                if (ticket.relationships?.replies?.data) {
                    const replyIds = ticket.relationships.replies.data.map((r: any) => r.id);
                    const replies = included.filter((item: any) => 
                        item.type === 'ticket_reply' && replyIds.includes(item.id)
                    );
                    resolve({
                        ...ticket,
                        included: replies,
                    });
                } else {
                    resolve({
                        ...ticket,
                        included,
                    });
                }
            })
            .catch(reject);
    });
};
