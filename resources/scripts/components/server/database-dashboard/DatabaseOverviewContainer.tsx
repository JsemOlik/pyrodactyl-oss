import { useEffect, useState } from 'react';
import useSWR from 'swr';

import ActionButton from '@/components/elements/ActionButton';
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
                <MainPageHeader title={serverName || 'Database'} />

                {/* Connection Information */}
                {connectionInfo && (
                    <div className='grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6'>
                        <div className='bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-6'>
                            <h3 className='text-xl font-bold text-white mb-4'>Connection Information</h3>
                            <div className='space-y-4'>
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
