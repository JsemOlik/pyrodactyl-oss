import http, { PaginatedResult, getPaginationSet } from '@/api/http';
import { Vps, rawDataToVpsObject } from '@/api/vps/getVps';

interface QueryParams {
    query?: string;
    page?: number;
    per_page?: number;
    filter?: {
        status?: string;
        name?: string;
    };
}

export default ({ query, filter, ...params }: QueryParams): Promise<PaginatedResult<Vps>> => {
    return new Promise((resolve, reject) => {
        const requestParams: any = {
            ...params,
        };

        if (query) {
            requestParams['filter[*]'] = query;
        }

        if (filter?.status) {
            requestParams['filter[status]'] = filter.status;
        }

        if (filter?.name) {
            requestParams['filter[name]'] = filter.name;
        }

        http.get('/api/client/vps-servers', {
            params: requestParams,
        })
            .then(({ data }) =>
                resolve({
                    items: (data.data || []).map((datum: any) => rawDataToVpsObject(datum)),
                    pagination: getPaginationSet(data.meta?.pagination || {}),
                }),
            )
            .catch(reject);
    });
};
