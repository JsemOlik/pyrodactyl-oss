import useServerEggFeatures from '@/hooks/useServerEggFeatures';
import { useState } from 'react';
import useSWR from 'swr';

import ActionButton from '@/components/elements/ActionButton';
import Can from '@/components/elements/Can';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import { Alert } from '@/components/elements/alert';

import http from '@/api/http';
import { httpErrorToHuman } from '@/api/http';
import getDatabaseConnectionInfo from '@/api/server/database-dashboard/getDatabaseConnectionInfo';
import getDatabaseMetrics from '@/api/server/database-dashboard/getDatabaseMetrics';

import { ServerContext } from '@/state/server';

interface DatabaseConnectionInfo {
    host: string;
    port: number;
    database: string;
    username: string;
    password?: string;
    connectionStrings: {
        mysql: string;
        pdo: string;
    };
}

interface DatabaseMetrics {
    size: number;
    sizeFormatted: string;
    tableCount: number;
    connectionCount: number;
    maxConnections: number;
    queryCount: number;
    uptime: number;
}

const DatabaseOverviewContainer = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name);

    // Egg/feature helper (normalizes egg features, docker image hints, and allocations)
    const { hasFeature, dockerImage, getAllocationMap } = useServerEggFeatures();
    const allocMap = getAllocationMap();

    const [showPassword, setShowPassword] = useState(false);
    const [connectionTestResult, setConnectionTestResult] = useState<{
        success: boolean;
        message: string;
    } | null>(null);

    const {
        data: connectionInfo,
        error: connectionError,
        isLoading: connectionLoading,
    } = useSWR<DatabaseConnectionInfo>(uuid ? [`/api/client/servers/${uuid}/database/connection`, uuid] : null, () =>
        getDatabaseConnectionInfo(uuid!),
    );

    const {
        data: metrics,
        error: metricsError,
        isLoading: metricsLoading,
    } = useSWR<DatabaseMetrics>(
        uuid ? [`/api/client/servers/${uuid}/database/metrics`, uuid] : null,
        () => getDatabaseMetrics(uuid!),
        {
            refreshInterval: 30000, // Refresh every 30 seconds
        },
    );

    const testConnection = async () => {
        if (!uuid) {
            return;
        }

        setConnectionTestResult(null);
        try {
            const response = await http.post(`/api/client/servers/${uuid}/database/connection/test`);
            setConnectionTestResult({
                success: true,
                message: 'Connection successful!',
            });
        } catch (error: any) {
            setConnectionTestResult({
                success: false,
                message: httpErrorToHuman(error) || 'Connection failed',
            });
        }
    };

    const copyToClipboard = (text: string) => {
        navigator.clipboard.writeText(text);
    };

    if (connectionLoading || metricsLoading) {
        return (
            <ServerContentBlock title='Overview'>
                <Spinner />
            </ServerContentBlock>
        );
    }

    if (connectionError || metricsError) {
        return (
            <ServerContentBlock title='Overview'>
                <Alert type='error'>
                    {httpErrorToHuman(connectionError || metricsError) || 'Failed to load database information'}
                </Alert>
            </ServerContentBlock>
        );
    }

    return (
        <ServerContentBlock title='Overview'>
            <div className='w-full h-full min-h-full flex-1 flex flex-col px-2 sm:px-0'>
                <MainPageHeader
                    title={serverName || 'Database'}
                    titleChildren={
                        <div className='flex items-center gap-3'>
                            {dockerImage && (
                                <div className='text-sm text-zinc-400'>
                                    Engine:
                                    <code className='ml-2 px-2 py-1 bg-[#00000040] rounded text-xs'>{dockerImage}</code>
                                </div>
                            )}
                            <div className='flex gap-2'>
                                {hasFeature('databases') && (
                                    <span className='px-2 py-1 text-xs rounded bg-[#ffffff0a] text-white/80'>
                                        Databases
                                    </span>
                                )}
                                {hasFeature('query-interface') && (
                                    <span className='px-2 py-1 text-xs rounded bg-[#ffffff0a] text-white/80'>
                                        Query
                                    </span>
                                )}
                                {hasFeature('table-browser') && (
                                    <span className='px-2 py-1 text-xs rounded bg-[#ffffff0a] text-white/80'>
                                        Tables
                                    </span>
                                )}
                                {hasFeature('backups') && (
                                    <span className='px-2 py-1 text-xs rounded bg-[#ffffff0a] text-white/80'>
                                        Backups
                                    </span>
                                )}
                            </div>
                        </div>
                    }
                />

                {/* Connection Information */}
                {connectionInfo && (
                    <div className='grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6'>
                        <div className='bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-6'>
                            <h3 className='text-xl font-bold text-white mb-4'>Connection Information</h3>
                            <div className='space-y-4'>
                                {allocMap && allocMap.cluster ? (
                                    <>
                                        <div>
                                            <label className='text-sm text-white/60'>Cluster (port)</label>
                                            <div className='flex items-center gap-2 mt-1'>
                                                <code className='flex-1 bg-[#00000040] px-3 py-2 rounded text-white'>
                                                    {allocMap.cluster.ip ??
                                                        allocMap.cluster.address ??
                                                        allocMap.cluster.host}
                                                    :{allocMap.cluster.port ?? allocMap.cluster.port}
                                                </code>
                                                <ActionButton
                                                    variant='secondary'
                                                    size='sm'
                                                    onClick={() =>
                                                        copyToClipboard(
                                                            `${allocMap.cluster.ip ?? allocMap.cluster.address ?? allocMap.cluster.host}:${allocMap.cluster.port}`,
                                                        )
                                                    }
                                                >
                                                    Copy
                                                </ActionButton>
                                            </div>
                                        </div>
                                        <div>
                                            <label className='text-sm text-white/60'>Driver (port)</label>
                                            <div className='flex items-center gap-2 mt-1'>
                                                <code className='flex-1 bg-[#00000040] px-3 py-2 rounded text-white'>
                                                    {allocMap.driver.ip ??
                                                        allocMap.driver.address ??
                                                        allocMap.driver.host}
                                                    :{allocMap.driver.port}
                                                </code>
                                                <ActionButton
                                                    variant='secondary'
                                                    size='sm'
                                                    onClick={() =>
                                                        copyToClipboard(
                                                            `${allocMap.driver.ip ?? allocMap.driver.address ?? allocMap.driver.host}:${allocMap.driver.port}`,
                                                        )
                                                    }
                                                >
                                                    Copy
                                                </ActionButton>
                                            </div>
                                        </div>
                                        <div>
                                            <label className='text-sm text-white/60'>HTTP (port)</label>
                                            <div className='flex items-center gap-2 mt-1'>
                                                <code className='flex-1 bg-[#00000040] px-3 py-2 rounded text-white'>
                                                    {allocMap.http.ip ?? allocMap.http.address ?? allocMap.http.host}:
                                                    {allocMap.http.port}
                                                </code>
                                                <ActionButton
                                                    variant='secondary'
                                                    size='sm'
                                                    onClick={() =>
                                                        copyToClipboard(
                                                            `${allocMap.http.ip ?? allocMap.http.address ?? allocMap.http.host}:${allocMap.http.port}`,
                                                        )
                                                    }
                                                >
                                                    Copy
                                                </ActionButton>
                                            </div>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <div>
                                            <label className='text-sm text-white/60'>Host</label>
                                            <div className='flex items-center gap-2 mt-1'>
                                                <code className='flex-1 bg-[#00000040] px-3 py-2 rounded text-white'>
                                                    {connectionInfo.host}
                                                </code>
                                                <ActionButton
                                                    variant='secondary'
                                                    size='sm'
                                                    onClick={() => copyToClipboard(connectionInfo.host)}
                                                >
                                                    Copy
                                                </ActionButton>
                                            </div>
                                        </div>
                                        <div>
                                            <label className='text-sm text-white/60'>Port</label>
                                            <div className='flex items-center gap-2 mt-1'>
                                                <code className='flex-1 bg-[#00000040] px-3 py-2 rounded text-white'>
                                                    {connectionInfo.port}
                                                </code>
                                                <ActionButton
                                                    variant='secondary'
                                                    size='sm'
                                                    onClick={() => copyToClipboard(connectionInfo.port.toString())}
                                                >
                                                    Copy
                                                </ActionButton>
                                            </div>
                                        </div>
                                    </>
                                )}
                                <div>
                                    <label className='text-sm text-white/60'>Database</label>
                                    <div className='flex items-center gap-2 mt-1'>
                                        <code className='flex-1 bg-[#00000040] px-3 py-2 rounded text-white'>
                                            {connectionInfo.database}
                                        </code>
                                        <ActionButton
                                            variant='secondary'
                                            size='sm'
                                            onClick={() => copyToClipboard(connectionInfo.database)}
                                        >
                                            Copy
                                        </ActionButton>
                                    </div>
                                </div>
                                <div>
                                    <label className='text-sm text-white/60'>Username</label>
                                    <div className='flex items-center gap-2 mt-1'>
                                        <code className='flex-1 bg-[#00000040] px-3 py-2 rounded text-white'>
                                            {connectionInfo.username}
                                        </code>
                                        <ActionButton
                                            variant='secondary'
                                            size='sm'
                                            onClick={() => copyToClipboard(connectionInfo.username)}
                                        >
                                            Copy
                                        </ActionButton>
                                    </div>
                                </div>
                                <Can action={'database.view_password'} matchAny>
                                    <div>
                                        <label className='text-sm text-white/60'>Password</label>
                                        <div className='flex items-center gap-2 mt-1'>
                                            <code className='flex-1 bg-[#00000040] px-3 py-2 rounded text-white'>
                                                {showPassword && connectionInfo.password
                                                    ? connectionInfo.password
                                                    : '••••••••'}
                                            </code>
                                            <ActionButton
                                                variant='secondary'
                                                size='sm'
                                                onClick={() => setShowPassword(!showPassword)}
                                            >
                                                {showPassword ? 'Hide' : 'Show'}
                                            </ActionButton>
                                            {connectionInfo.password && (
                                                <ActionButton
                                                    variant='secondary'
                                                    size='sm'
                                                    onClick={() => copyToClipboard(connectionInfo.password || '')}
                                                >
                                                    Copy
                                                </ActionButton>
                                            )}
                                        </div>
                                    </div>
                                </Can>
                                <div className='pt-2'>
                                    <ActionButton variant='primary' onClick={testConnection}>
                                        Test Connection
                                    </ActionButton>
                                    {connectionTestResult && (
                                        <div
                                            className={`mt-2 text-sm ${connectionTestResult.success ? 'text-green-400' : 'text-red-400'}`}
                                        >
                                            {connectionTestResult.message}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Connection Strings */}
                        <div className='bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-6'>
                            <h3 className='text-xl font-bold text-white mb-4'>Connection Strings</h3>
                            <div className='space-y-4'>
                                <div>
                                    <label className='text-sm text-white/60'>MySQL</label>
                                    <div className='flex items-center gap-2 mt-1'>
                                        <code className='flex-1 bg-[#00000040] px-3 py-2 rounded text-white text-xs break-all'>
                                            {connectionInfo.connectionStrings.mysql}
                                        </code>
                                        <ActionButton
                                            variant='secondary'
                                            size='sm'
                                            onClick={() => copyToClipboard(connectionInfo.connectionStrings.mysql)}
                                        >
                                            Copy
                                        </ActionButton>
                                    </div>
                                </div>
                                <div>
                                    <label className='text-sm text-white/60'>PDO</label>
                                    <div className='flex items-center gap-2 mt-1'>
                                        <code className='flex-1 bg-[#00000040] px-3 py-2 rounded text-white text-xs break-all'>
                                            {connectionInfo.connectionStrings.pdo}
                                        </code>
                                        <ActionButton
                                            variant='secondary'
                                            size='sm'
                                            onClick={() => copyToClipboard(connectionInfo.connectionStrings.pdo)}
                                        >
                                            Copy
                                        </ActionButton>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {/* Metrics */}
                {metrics && (
                    <div className='grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6'>
                        <div className='bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-6'>
                            <h3 className='text-lg font-semibold text-white/60 mb-2'>Database Size</h3>
                            <p className='text-3xl font-bold text-white'>{metrics.sizeFormatted}</p>
                        </div>
                        <div className='bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-6'>
                            <h3 className='text-lg font-semibold text-white/60 mb-2'>Tables</h3>
                            <p className='text-3xl font-bold text-white'>{metrics.tableCount}</p>
                        </div>
                        <div className='bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-6'>
                            <h3 className='text-lg font-semibold text-white/60 mb-2'>Connections</h3>
                            <p className='text-3xl font-bold text-white'>
                                {metrics.connectionCount} / {metrics.maxConnections}
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </ServerContentBlock>
    );
};

export default DatabaseOverviewContainer;
