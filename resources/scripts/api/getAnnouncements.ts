import http from '@/api/http';

export type Announcement = {
    id: number;
    title: string;
    message: string;
    type: 'success' | 'info' | 'warning' | 'danger';
    published_at?: string | null;
    created_at: string;
};

export default async function getAnnouncements(): Promise<Announcement[]> {
    const { data } = await http.get('/api/client/announcements');
    return data;
}
