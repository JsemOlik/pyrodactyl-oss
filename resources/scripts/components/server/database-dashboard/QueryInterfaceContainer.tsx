import { Play, Terminal, TrashBin } from '@gravity-ui/icons';
import { useState } from 'react';
import useSWR from 'swr';

import FlashMessageRender from '@/components/FlashMessageRender';
import ActionButton from '@/components/elements/ActionButton';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';

import { httpErrorToHuman } from '@/api/http';
import executeQuery, { ExecuteQueryResponse } from '@/api/server/database-dashboard/executeQuery';
import getDatabaseConnectionInfo from '@/api/server/database-dashboard/getDatabaseConnectionInfo';

import { ServerContext } from '@/state/server';

import useFlash from '@/plugins/useFlash';

const QueryInterfaceContainer = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name);

    const { addError, clearFlashes } = useFlash();
    const [query, setQuery] = useState('SELECT * FROM ');
    const [isExecuting, setIsExecuting] = useState(false);
    const [queryResult, setQueryResult] = useState<ExecuteQueryResponse | null>(null);
    const [queryHistory, setQueryHistory] = useState<string[]>([]);

    const { data: connectionInfo } = useSWR(
        uuid ? [`/api/client/servers/${uuid}/database/connection`, uuid] : null,
        () => getDatabaseConnectionInfo(uuid!),
    );

    const execute = async () => {
        if (!uuid || !query.trim()) {
            return;
        }

        clearFlashes('query');
        setIsExecuting(true);
        setQueryResult(null);

        try {
            const result = await executeQuery(uuid, {
                query: query.trim(),
                database: connectionInfo?.database,
            });
            setQueryResult(result);
            // Add to history if not already there
            if (!queryHistory.includes(query.trim())) {
                setQueryHistory([query.trim(), ...queryHistory.slice(0, 9)]); // Keep last 10
            }
        } catch (error: any) {
            addError({ key: 'query', message: httpErrorToHuman(error) || 'Query execution failed' });
        } finally {
            setIsExecuting(false);
        }
    };

    const clearQuery = () => {
        setQuery('');
        setQueryResult(null);
    };

    const loadHistoryQuery = (historyQuery: string) => {
        setQuery(historyQuery);
    };

    return (
        <ServerContentBlock title='Query'>
            <FlashMessageRender byKey='query' />
            <MainPageHeader title={serverName || 'Database'} />
            <div className='w-full h-full min-h-full flex-1 flex flex-col px-2 sm:px-0 mt-6'>
                <div className='grid grid-cols-1 lg:grid-cols-3 gap-6 h-full'>
                    {/* Query Editor */}
                    <div className='lg:col-span-2 flex flex-col'>
                        <div className='bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-4 flex-1 flex flex-col'>
                            <div className='flex items-center justify-between mb-4'>
                                <h3 className='text-lg font-semibold text-white'>SQL Query</h3>
                                <div className='flex gap-2'>
                                    <ActionButton variant='secondary' size='sm' onClick={clearQuery}>
                                        <TrashBin className='w-4 h-4 mr-2' fill='currentColor' />
                                        Clear
                                    </ActionButton>
                                    <ActionButton
                                        variant='primary'
                                        size='sm'
                                        onClick={execute}
                                        disabled={isExecuting || !query.trim()}
                                    >
                                        {isExecuting ? (
                                            <Spinner size='small' />
                                        ) : (
                                            <Play className='w-4 h-4 mr-2' fill='currentColor' />
                                        )}
                                        Execute
                                    </ActionButton>
                                </div>
                            </div>
                            <textarea
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                className='flex-1 w-full bg-[#00000040] border border-[#ffffff11] rounded-lg p-4 text-white font-mono text-sm resize-none focus:outline-none focus:border-[var(--color-brand)]/50'
                                placeholder='SELECT * FROM table_name LIMIT 100;'
                                spellCheck={false}
                            />
                            <p className='text-xs text-white/60 mt-2'>
                                Only SELECT queries are allowed for security. Queries are limited to 10,000 characters.
                            </p>
                        </div>
                    </div>

                    {/* Query History */}
                    <div className='flex flex-col'>
                        <div className='bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-4'>
                            <h3 className='text-lg font-semibold text-white mb-4'>Query History</h3>
                            {queryHistory.length === 0 ? (
                                <p className='text-sm text-white/60'>No query history</p>
                            ) : (
                                <div className='space-y-2 max-h-[200px] overflow-y-auto'>
                                    {queryHistory.map((historyQuery, idx) => (
                                        <button
                                            key={idx}
                                            onClick={() => loadHistoryQuery(historyQuery)}
                                            className='w-full text-left p-2 bg-[#00000040] rounded hover:bg-[#00000060] transition-colors'
                                        >
                                            <p className='text-xs text-white/80 font-mono truncate'>{historyQuery}</p>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Results */}
                {queryResult && (
                    <div className='mt-6 bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-4'>
                        <div className='flex items-center justify-between mb-4'>
                            <h3 className='text-lg font-semibold text-white'>Results</h3>
                            <div className='flex items-center gap-4 text-sm text-white/60'>
                                <span>{queryResult.rowCount} rows</span>
                                <span>{queryResult.executionTime}ms</span>
                            </div>
                        </div>
                        {queryResult.data.length > 0 ? (
                            <div className='overflow-x-auto'>
                                <table className='w-full border-collapse'>
                                    <thead>
                                        <tr className='border-b border-white/10'>
                                            {queryResult.columns.map((col) => (
                                                <th
                                                    key={col}
                                                    className='text-left p-2 text-sm text-white/60 font-semibold'
                                                >
                                                    {col}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {queryResult.data.map((row, idx) => (
                                            <tr key={idx} className='border-b border-white/5 hover:bg-white/5'>
                                                {queryResult.columns.map((col) => (
                                                    <td key={col} className='p-2 text-white/80 font-mono text-sm'>
                                                        {row[col] !== null && row[col] !== undefined
                                                            ? String(row[col])
                                                            : 'NULL'}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className='text-center py-8 text-white/60'>No results returned</div>
                        )}
                    </div>
                )}
            </div>
        </ServerContentBlock>
    );
};

export default QueryInterfaceContainer;
