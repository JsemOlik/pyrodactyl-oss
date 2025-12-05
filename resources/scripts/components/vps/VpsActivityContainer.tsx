import { ArrowDownToLine, Funnel, Magnifier, Xmark } from '@gravity-ui/icons';
import { useEffect, useMemo, useState } from 'react';

import FlashMessageRender from '@/components/FlashMessageRender';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import Spinner from '@/components/elements/Spinner';
import VpsContentBlock from '@/components/elements/VpsContentBlock';
import ActivityLogEntry from '@/components/elements/activity/ActivityLogEntry';
import { Input } from '@/components/elements/inputs';
import PaginationFooter from '@/components/elements/table/PaginationFooter';

import { VpsActivityLogFilters, useVpsActivityLogs } from '@/api/vps/getVpsActivity';

import { VpsContext } from '@/state/vps';

import { useFlashKey } from '@/plugins/useFlash';
import useLocationHash from '@/plugins/useLocationHash';

const VpsActivityContainer = () => {
    const vps = VpsContext.useStoreState((state) => state.vps.data);
    const { hash } = useLocationHash();
    const { clearAndAddHttpError } = useFlashKey('vps:activity');
    const [filters, setFilters] = useState<VpsActivityLogFilters>({ page: 1, sorts: { timestamp: -1 } });
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedEventType, setSelectedEventType] = useState('');
    const [showFilters, setShowFilters] = useState(false);

    const { data, isValidating, error } = useVpsActivityLogs(vps?.uuid || '', filters, {
        revalidateOnMount: true,
        revalidateOnFocus: false,
    });

    // Extract unique event types for filter dropdown
    const eventTypes = useMemo(() => {
        if (!data?.items) return [];
        const types = [...new Set(data.items.map((item) => item.event))];
        return types.sort();
    }, [data?.items]);

    // Filter data based on search term and event type
    const filteredData = useMemo(() => {
        if (!data?.items) return data;

        let filtered = data.items;

        if (searchTerm) {
            filtered = filtered.filter(
                (item) =>
                    item.event.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    item.ip?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    item.relationships.actor?.username?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    JSON.stringify(item.properties).toLowerCase().includes(searchTerm.toLowerCase()),
            );
        }

        if (selectedEventType) {
            filtered = filtered.filter((item) => item.event === selectedEventType);
        }

        return { ...data, items: filtered };
    }, [data, searchTerm, selectedEventType]);

    const exportLogs = () => {
        if (!filteredData?.items) return;

        const csvContent = [
            ['Timestamp', 'Event', 'Actor', 'IP Address', 'Properties'].join(','),
            ...filteredData.items.map((item) =>
                [
                    new Date(item.timestamp).toISOString(),
                    item.event,
                    item.relationships.actor?.username || 'System',
                    item.ip || '',
                    JSON.stringify(item.properties).replace(/"/g, '""'),
                ]
                    .map((field) => `"${field}"`)
                    .join(','),
            ),
        ].join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `vps-activity-${vps?.uuid || 'logs'}-${new Date().toISOString()}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };

    useEffect(() => {
        if (error) {
            clearAndAddHttpError({ error, key: 'vps:activity' });
        }
    }, [error]);

    useEffect(() => {
        if (hash) {
            const element = document.getElementById(hash);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }, [hash, data]);

    if (!vps) {
        return null;
    }

    return (
        <VpsContentBlock title={'Activity'}>
            <FlashMessageRender byKey={'vps:activity'} />
            <MainPageHeader title={'Activity Log'} description={'View all activity and events related to this VPS'} />

            <div className='mb-4 flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between'>
                <div className='flex-1 flex gap-2 w-full sm:w-auto'>
                    <div className='flex-1 relative'>
                        <Magnifier
                            className='absolute left-3 top-1/2 transform -translate-y-1/2 text-zinc-400'
                            width={18}
                            height={18}
                        />
                        <Input
                            type='text'
                            placeholder='Search activity logs...'
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className='pl-10'
                        />
                    </div>
                    <button
                        onClick={() => setShowFilters(!showFilters)}
                        className={`px-4 py-2 rounded-md border transition-colors ${
                            showFilters
                                ? 'bg-brand border-brand text-white'
                                : 'bg-[#ffffff09] border-[#ffffff12] text-zinc-300 hover:border-[#ffffff20]'
                        }`}
                    >
                        <Funnel width={18} height={18} />
                    </button>
                </div>
                <button
                    onClick={exportLogs}
                    disabled={!filteredData?.items?.length}
                    className='px-4 py-2 rounded-md bg-[#ffffff09] border border-[#ffffff12] text-zinc-300 hover:border-[#ffffff20] transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2'
                >
                    <ArrowDownToLine width={18} height={18} />
                    Export CSV
                </button>
            </div>

            {showFilters && (
                <div className='mb-4 p-4 bg-[#ffffff09] border border-[#ffffff12] rounded-xl'>
                    <div className='flex items-center justify-between mb-3'>
                        <h3 className='text-sm font-semibold'>Filters</h3>
                        <button
                            onClick={() => setShowFilters(false)}
                            className='text-zinc-400 hover:text-white transition-colors'
                        >
                            <Xmark width={18} height={18} />
                        </button>
                    </div>
                    <div className='flex flex-col sm:flex-row gap-4'>
                        <div className='flex-1'>
                            <label className='block text-xs text-zinc-400 mb-1'>Event Type</label>
                            <select
                                value={selectedEventType}
                                onChange={(e) => setSelectedEventType(e.target.value)}
                                className='w-full px-3 py-2 bg-zinc-900 border border-[#ffffff12] rounded-md text-sm text-white focus:outline-none focus:border-brand'
                            >
                                <option value=''>All Events</option>
                                {eventTypes.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                </div>
            )}

            {isValidating && !data ? (
                <div className='flex justify-center items-center py-12'>
                    <Spinner size='large' />
                </div>
            ) : !filteredData?.items?.length ? (
                <div className='text-center py-12'>
                    <p className='text-zinc-400'>No activity logs found.</p>
                </div>
            ) : (
                <>
                    <div className='space-y-2'>
                        {filteredData.items.map((item) => (
                            <ActivityLogEntry key={item.id} activity={item} />
                        ))}
                    </div>

                    {filteredData.pagination && filteredData.pagination.totalPages > 1 && (
                        <PaginationFooter
                            pagination={filteredData.pagination}
                            onPageSelect={(page) => setFilters((prev) => ({ ...prev, page }))}
                        />
                    )}
                </>
            )}
        </VpsContentBlock>
    );
};

export default VpsActivityContainer;
