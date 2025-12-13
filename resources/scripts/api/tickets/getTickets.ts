import http from '@/api/http';

export interface Ticket {
    object: string;
    attributes: {
        id: number;
        subject: string;
        description: string;
        category: 'billing' | 'technical' | 'general' | 'other';
        status: 'open' | 'in_progress' | 'resolved' | 'closed';
        priority: 'low' | 'medium' | 'high' | 'urgent';
        server_id: number | null;
        subscription_id: number | null;
        assigned_to: number | null;
        resolved_at: string | null;
        resolved_by: number | null;
        created_at: string;
        updated_at: string;
    };
}

export interface PaginatedTickets {
    data: Ticket[];
    meta: {
        pagination: {
            total: number;
            count: number;
            per_page: number;
            current_page: number;
            total_pages: number;
        };
    };
}

export default (params?: {
    status?: string;
    category?: string;
    priority?: string;
    per_page?: number;
}): Promise<PaginatedTickets> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/tickets', { params })
            .then(({ data }) => resolve(data))
            .catch(reject);
    });
};
