import http from '@/api/http';

export default async (gravatarStyle: string): Promise<void> => {
    await http.put('/api/client/account/gravatar-style', { gravatar_style: gravatarStyle });
};

