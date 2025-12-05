import { toPaginatedSet } from '@definitions/helpers';
import type { ActivityLog } from '@definitions/user';
import { Transformers } from '@definitions/user';
import type { AxiosError } from 'axios';
import type { SWRConfiguration } from 'swr';
import useSWR from 'swr';

import type { PaginatedResult, QueryBuilderParams } from '@/api/http';
import http, { withQueryBuilderParams } from '@/api/http';

import useFilteredObject from '@/plugins/useFilteredObject';

export type VpsActivityLogFilters = QueryBuilderParams<'event', 'timestamp'>;

const useVpsActivityLogs = (
    uuid: string,
    filters?: VpsActivityLogFilters,
    config?: SWRConfiguration<PaginatedResult<ActivityLog>, AxiosError>,
) => {
    const key = uuid ? [`vps-activity-${uuid}`, useFilteredObject(filters || {})] : null;

    return useSWR<PaginatedResult<ActivityLog>>(
        key,
        async () => {
            const { data } = await http.get(`/api/client/vps-servers/${uuid}/activity`, {
                params: {
                    ...withQueryBuilderParams(filters),
                    include: ['actor'],
                },
            });

            return toPaginatedSet(data, Transformers.toActivityLog);
        },
        { revalidateOnMount: false, ...(config || {}) },
    );
};

export { useVpsActivityLogs };

