import http from '@/api/http';

export interface AccountData {
    id: number;
    admin: boolean;
    username: string;
    email: string;
    first_name: string | null;
    last_name: string | null;
    language: string;
    gravatar_style: string;
}

export default (): Promise<AccountData> => {
    return new Promise((resolve, reject) => {
        http.get('/api/client/account')
            .then(({ data }) => {
                const attributes = data.attributes || data.data?.attributes;
                resolve({
                    id: attributes.id,
                    admin: attributes.admin,
                    username: attributes.username,
                    email: attributes.email,
                    first_name: attributes.first_name,
                    last_name: attributes.last_name,
                    language: attributes.language,
                    gravatar_style: attributes.gravatar_style || 'identicon',
                });
            })
            .catch(reject);
    });
};

