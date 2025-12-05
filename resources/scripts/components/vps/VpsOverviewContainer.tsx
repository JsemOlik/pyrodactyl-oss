import { useEffect, useState } from 'react';

import { MainPageHeader } from '@/components/elements/MainPageHeader';
import VpsContentBlock from '@/components/elements/VpsContentBlock';
import { Alert } from '@/components/elements/alert';

import { VpsContext } from '@/state/vps';
import PowerButtons from '@/components/vps/console/PowerButtons';
import VpsDetailsBlock from '@/components/vps/console/VpsDetailsBlock';
import getVpsMetrics from '@/api/vps/getVpsMetrics';
import { VpsMetrics } from '@/api/vps/types';

import UptimeDuration from '@/components/server/UptimeDuration';

const VpsOverviewContainer = () => {
    const vps = VpsContext.useStoreState((state) => state.vps.data);
    const isInstalling = VpsContext.useStoreState((state) => state.vps.isInstalling);
    const inConflictState = VpsContext.useStoreState((state) => state.vps.inConflictState);
    const [uptime, setUptime] = useState<number>(0);
    const [metrics, setMetrics] = useState<VpsMetrics | null>(null);

    useEffect(() => {
        if (!vps || !vps.isRunning) {
            setUptime(0);
            return;
        }

        const loadMetrics = async () => {
            try {
                const data = await getVpsMetrics(vps.uuid);
                setMetrics(data);
                setUptime(data.uptime);
            } catch (error) {
                console.error('Failed to load VPS metrics', error);
            }
        };

        loadMetrics();
        const interval = setInterval(loadMetrics, 1000); // Update every 1 second

        return () => clearInterval(interval);
    }, [vps?.uuid, vps?.isRunning]);

    if (!vps) {
        return null;
    }

    return (
        <VpsContentBlock title={'Overview'}>
            <div className='w-full h-full min-h-full flex-1 flex flex-col px-2 sm:px-0'>
                {inConflictState && (
                    <div
                        className='transform-gpu skeleton-anim-2 mb-3 sm:mb-4'
                        style={{
                            animationDelay: '50ms',
                            animationTimingFunction:
                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                        }}
                    >
                        <Alert type={'warning'}>
                            {isInstalling
                                ? 'This VPS is currently being set up and most actions are unavailable.'
                                : vps.status === 'creating'
                                  ? 'This VPS is currently being created. Please wait...'
                                  : vps.status === 'error'
                                    ? 'An error occurred with this VPS. Please contact support.'
                                    : 'This VPS is currently in a transitional state. Please wait...'}
                        </Alert>
                    </div>
                )}
                <div
                    className='transform-gpu skeleton-anim-2 mb-3 sm:mb-4'
                    style={{
                        animationDelay: '75ms',
                        animationTimingFunction:
                            'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                    }}
                >
                    <MainPageHeader
                        title={vps.name}
                        headChildren={
                            vps.isRunning && uptime > 0 ? (
                                <p className='hidden bg-color ms:block ms:inline-flex md:inline-flex md:block absolute left-0 mb-4 mt-8 p-1 text-zinc-300 border-2 bg-gradient-to-b from-[#ffffff08] to-[#ffffff05] border-[#ffffff12] rounded-lg hover:border-[#ffffff20] transition-all duration-150 shadow-sm '>
                                    Uptime: {UptimeDuration({ uptime })}
                                </p>
                            ) : null
                        }
                        titleChildren={
                            <div
                                className='transform-gpu skeleton-anim-2'
                                style={{
                                    animationDelay: '100ms',
                                    animationTimingFunction:
                                        'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                                }}
                            >
                                {vps.isRunning && uptime > 0 && (
                                    <p className='inline-flex relative max-w-50 min-w-35 block ms:hidden md:hidden justify-left left-0 mb-4 p-1 text-zinc-300 border-2 bg-gradient-to-b from-[#ffffff08] to-[#ffffff05] border-[#ffffff12] rounded-lg hover:border-[#ffffff20] transition-all duration-150 shadow-sm '>
                                        Uptime: {UptimeDuration({ uptime })}
                                    </p>
                                )}
                                <PowerButtons className='flex gap-1 items-center justify-center' />
                            </div>
                        }
                    />
                </div>
                {vps.description && (
                    <div
                        className='transform-gpu skeleton-anim-2 mb-3 mt-3 sm:mb-4'
                        style={{
                            animationDelay: '100ms',
                            animationTimingFunction:
                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                        }}
                    >
                        <div className='bg-gradient-to-b from-[#ffffff08] to-[#ffffff05] border-[1px] border-[#ffffff12] rounded-xl p-3 sm:p-4 hover:border-[#ffffff20] transition-all duration-150 shadow-sm'>
                            <p className='text-sm text-zinc-300 leading-relaxed'>{vps.description}</p>
                        </div>
                    </div>
                )}
                <div className='flex flex-col gap-3 sm:gap-4'>
                    <div
                        className='transform-gpu skeleton-anim-2'
                        style={{
                            animationDelay: '125ms',
                            animationTimingFunction:
                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                        }}
                    >
                        <div className='bg-gradient-to-b from-[#ffffff08] to-[#ffffff05] border-[1px] border-[#ffffff12] rounded-xl p-3 sm:p-4 hover:border-[#ffffff20] transition-all duration-150 shadow-sm'>
                            <VpsDetailsBlock />
                        </div>
                    </div>
                </div>
            </div>
        </VpsContentBlock>
    );
};

export default VpsOverviewContainer;

