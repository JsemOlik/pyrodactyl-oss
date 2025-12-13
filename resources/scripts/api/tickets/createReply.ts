import http from '@/api/http';

export interface TicketReply {
    object: string;
    attributes: {
        id: number;
        ticket_id: number;
        message: string;
        is_internal: boolean;
        created_at: string;
        updated_at: string;
    };
}

export interface CreateReplyData {
    message: string;
}

export default (ticketId: number, data: CreateReplyData): Promise<TicketReply> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/tickets/${ticketId}/replies`, data)
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};
