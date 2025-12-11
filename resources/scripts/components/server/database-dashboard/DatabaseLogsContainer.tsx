import { FileText, Funnel, Refresh } from '@gravity-ui/icons';
import { useState } from 'react';
import useSWR from 'swr';

import FlashMessageRender from '@/components/FlashMessageRender';
import ActionButton from '@/components/elements/ActionButton';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import Select from '@/components/elements/Select';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';

import { httpErrorToHuman } from '@/api/http';
import getDatabaseConnectionInfo from '@/api/server/database-dashboard/getDatabaseConnectionInfo';
import getLogs, { LogEntry, LogsResponse } from '@/api/server/database-dashboard/getLogs';

import { ServerContext } from '@/state/server';

import useFlash from '@/plugins/useFlash';

const DatabaseLogsContainer = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name);

    const { addError, clearFlashes } = useFlash();
    const [logType, setLogType] = useState<'error' | 'slow' | 'general'>('general');
    const [limit, setLimit] = useState(100);

    const { data: connectionInfo } = useSWR(
        uuid ? [`/api/client/servers/${uuid}/database/connection`, uuid] : null,
        () => getDatabaseConnectionInfo(uuid!),
    );

    const {
        data: logsData,
        error,
        isLoading,
        mutate,
    } = useSWR<LogsResponse>(
        uuid && connectionInfo
            ? [`/api/client/servers/${uuid}/database/logs`, logType, limit, connectionInfo.database]
            : null,
        () => getLogs(uuid!, logType, limit, connectionInfo?.database),
        {
            revalidateOnFocus: false,
            onError: (err) => {
                addError({ key: 'logs', message: httpErrorToHuman(err) || 'Failed to load logs' });
            },
        },
    );

    const formatTimestamp = (timestamp: string) => {
        try {
            return new Date(timestamp).toLocaleString();
        } catch {
            return timestamp;
        }
    };

    return (
        <ServerContentBlock title='Logs'>
            <FlashMessageRender byKey='logs' />
            <MainPageHeader title={serverName || 'Database'} />
            <div className='w-full h-full min-h-full flex-1 flex flex-col px-2 sm:px-0 mt-6'>
                <div className='bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-4'>
                    <div className='flex items-center justify-between mb-4'>
                        <h3 className='text-lg font-semibold text-white'>Database Logs</h3>
                        <div className='flex items-center gap-3'>
                            <div className='flex items-center gap-2'>
                                <label className='text-sm text-white/60'>Log Type:</label>
                                <Select value={logType} onChange={(e) => setLogType(e.target.value as any)}>
                                    <option value='general'>General Query Log</option>
                                    <option value='slow'>Slow Query Log</option>
                                    <option value='error'>Error Log</option>
                                </Select>
                            </div>
                            <div className='flex items-center gap-2'>
                                <label className='text-sm text-white/60'>Limit:</label>
                                <Select value={limit} onChange={(e) => setLimit(Number(e.target.value))}>
                                    <option value={50}>50</option>
                                    <option value={100}>100</option>
                                    <option value={250}>250</option>
                                    <option value={500}>500</option>
                                </Select>
                            </div>
                            <ActionButton variant='secondary' size='sm' onClick={() => mutate()}>
                                <Refresh className='w-4 h-4 mr-2' fill='currentColor' />
                                Refresh
                            </ActionButton>
                        </div>
                    </div>

                    {isLoading ? (
                        <div className='flex justify-center py-8'>
                            <Spinner />
                        </div>
                    ) : error ? (
                        <div className='text-center py-8 text-red-400'>
                            Failed to load logs. Some log types may not be available on your MySQL server.
                        </div>
                    ) : logsData && logsData.logs.length > 0 ? (
                        <div className='space-y-2 max-h-[70vh] overflow-y-auto'>
                            {logsData.logs.map((log: LogEntry, idx: number) => (
                                <div
                                    key={idx}
                                    className='bg-[#00000040] border border-[#ffffff11] rounded-lg p-4 font-mono text-sm'
                                >
                                    <div className='flex items-start justify-between mb-2'>
                                        <div className='flex items-center gap-3'>
                                            <span className='text-white/60'>{formatTimestamp(log.timestamp)}</span>
                                            {log.level && (
                                                <span
                                                    className={`px-2 py-1 rounded text-xs ${
                                                        log.level === 'ERROR'
                                                            ? 'bg-red-500/20 text-red-400'
                                                            : log.level === 'WARNING'
                                                              ? 'bg-yellow-500/20 text-yellow-400'
                                                              : 'bg-blue-500/20 text-blue-400'
                                                    }`}
                                                >
                                                    {log.level}
                                                </span>
                                            )}
                                            {log.user_host && (
                                                <span className='text-white/40 text-xs'>{log.user_host}</span>
                                            )}
                                        </div>
                                        {log.query_time && (
                                            <span className='text-white/60 text-xs'>
                                                {log.query_time}s (lock: {log.lock_time}s)
                                            </span>
                                        )}
                                    </div>
                                    {log.query && (
                                        <div className='mt-2 p-2 bg-[#00000060] rounded text-white/80 break-all'>
                                            {log.query}
                                        </div>
                                    )}
                                    {log.message && <div className='mt-2 text-white/80 break-all'>{log.message}</div>}
                                    {log.rows_sent !== undefined && (
                                        <div className='mt-2 text-xs text-white/60'>
                                            Rows: {log.rows_sent} sent, {log.rows_examined} examined
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className='text-center py-8 text-white/60'>
                            <FileText className='w-12 h-12 mx-auto mb-4 text-white/40' fill='currentColor' />
                            <p>No logs found for this log type.</p>
                            <p className='text-sm mt-2'>
                                Note: Some log types require specific MySQL configuration to be enabled.
                            </p>
                        </div>
                    )}
                </div>
            </div>
        </ServerContentBlock>
    );
};

export default DatabaseLogsContainer;
