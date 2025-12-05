import clsx from 'clsx';
import { useEffect, useMemo, useState } from 'react';

import StatBlock from '@/components/server/console/StatBlock';

import { bytesToString, mbToBytes } from '@/lib/formatters';

import getVpsMetrics from '@/api/vps/getVpsMetrics';
import { VpsMetrics } from '@/api/vps/types';

import { VpsContext } from '@/state/vps';

// @ts-expect-error - Unused parameter in component definition
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const Limit = ({ limit, children }: { limit: string | null; children: React.ReactNode }) => <>{children}</>;

const VpsDetailsBlock = ({ className }: { className?: string }) => {
    const [metrics, setMetrics] = useState<VpsMetrics | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    const status = VpsContext.useStoreState((state) => state.status.value);
    const vps = VpsContext.useStoreState((state) => state.vps.data);
    const limits = vps?.limits;

    const textLimits = useMemo(
        () => ({
            cpu: limits?.cpu_cores ? `${limits.cpu_cores} cores` : null,
            memory: limits?.memory ? bytesToString(mbToBytes(limits.memory)) : null,
            disk: limits?.disk ? bytesToString(mbToBytes(limits.disk)) : null,
        }),
        [limits],
    );

    const ipAddress = useMemo(() => {
        return vps?.network.ip_address || vps?.network.ipv6_address || 'No IP assigned';
    }, [vps?.network]);

    useEffect(() => {
        if (!vps || !vps.isRunning) {
            setIsLoading(false);
            return;
        }

        const loadMetrics = async () => {
            try {
                setIsLoading(true);
                const data = await getVpsMetrics(vps.uuid);
                setMetrics(data);
            } catch (error) {
                console.error('Failed to load VPS metrics', error);
            } finally {
                setIsLoading(false);
            }
        };

        loadMetrics();
        const interval = setInterval(loadMetrics, 1000); // Update every 1 second

        return () => clearInterval(interval);
    }, [vps?.uuid, vps?.isRunning]);

    const currentStatus = status || (vps?.isRunning ? 'running' : vps?.isStopped ? 'offline' : null);

    return (
        <div className={clsx('grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4', className)}>
            <div
                className='transform-gpu skeleton-anim-2'
                style={{
                    animationDelay: `50ms`,
                    animationTimingFunction:
                        'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                }}
            >
                <StatBlock title={'IP Address'} copyOnClick={ipAddress !== 'No IP assigned' ? ipAddress : undefined}>
                    {ipAddress}
                </StatBlock>
            </div>
            <div
                className='transform-gpu skeleton-anim-2'
                style={{
                    animationDelay: `75ms`,
                    animationTimingFunction:
                        'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                }}
            >
                <StatBlock title={'CPU'}>
                    {currentStatus === 'offline' || isLoading ? (
                        <span className={'text-zinc-400'}>{isLoading ? 'Loading...' : 'Offline'}</span>
                    ) : metrics ? (
                        <Limit limit={textLimits.cpu}>
                            {`${metrics.cpu.usage_percent.toFixed(2)}% / ${textLimits.cpu ?? 'Unlimited'}`}
                        </Limit>
                    ) : (
                        <span className={'text-zinc-400'}>N/A</span>
                    )}
                </StatBlock>
            </div>
            <div
                className='transform-gpu skeleton-anim-2'
                style={{
                    animationDelay: `100ms`,
                    animationTimingFunction:
                        'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                }}
            >
                <StatBlock title={'RAM'}>
                    {currentStatus === 'offline' || isLoading ? (
                        <span className={'text-zinc-400'}>{isLoading ? 'Loading...' : 'Offline'}</span>
                    ) : metrics ? (
                        <Limit limit={textLimits.memory}>
                            {`${bytesToString(metrics.memory.used_bytes)} / ${textLimits.memory ?? 'Unlimited'}`}
                        </Limit>
                    ) : (
                        <span className={'text-zinc-400'}>N/A</span>
                    )}
                </StatBlock>
            </div>
            <div
                className='transform-gpu skeleton-anim-2'
                style={{
                    animationDelay: `125ms`,
                    animationTimingFunction:
                        'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                }}
            >
                <StatBlock title={'Storage'}>
                    {isLoading ? (
                        <span className={'text-zinc-400'}>Loading...</span>
                    ) : metrics ? (
                        <Limit limit={textLimits.disk}>
                            {`${bytesToString(metrics.disk.used_bytes)} / ${textLimits.disk ?? 'Unlimited'}`}
                        </Limit>
                    ) : (
                        <span className={'text-zinc-400'}>N/A</span>
                    )}
                </StatBlock>
            </div>
        </div>
    );
};

export default VpsDetailsBlock;
