import http from '@/api/http';

export default (ticketId: number): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.delete(`/api/client/tickets/${ticketId}`)
            .then(() => resolve())
            .catch(reject);
    });
};
