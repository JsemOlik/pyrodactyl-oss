import http from '@/api/http';

export type SortOption = 'default' | 'name_asc' | 'custom';

interface ServerPreferencesResponse {
    order: string[];
    sort_option: SortOption;
}

export interface ServerPreferences {
    order: string[];
    sortOption: SortOption;
}

export const getServerPreferences = (): Promise<ServerPreferences> => {
    return new Promise((resolve, reject) => {
        http.get<ServerPreferencesResponse>('/api/client/servers/order')
            .then(({ data }) =>
                resolve({
                    order: data.order || [],
                    sortOption: data.sort_option || 'default',
                }),
            )
            .catch(reject);
    });
};

export const updateServerPreferences = (
    preferences: Partial<{ order: string[]; sortOption: SortOption }>,
): Promise<ServerPreferences> => {
    return new Promise((resolve, reject) => {
        http.put<ServerPreferencesResponse>('/api/client/servers/order', {
            order: preferences.order,
            sort_option: preferences.sortOption,
        })
            .then(({ data }) =>
                resolve({
                    order: data.order,
                    sortOption: data.sort_option,
                }),
            )
            .catch(reject);
    });
};
