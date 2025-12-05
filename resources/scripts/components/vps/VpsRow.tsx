import { Fragment, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import styled from 'styled-components';

import { bytesToString, ip } from '@/lib/formatters';

import { Vps } from '@/api/vps/types';
import getVpsMetrics from '@/api/vps/getVpsMetrics';
import { VpsMetrics } from '@/api/vps/types';
import sendVpsPower from '@/api/vps/sendVpsPower';

// Determines if the current value is in an alarm threshold
const isAlarmState = (current: number, limit: number): boolean => limit > 0 && current / limit >= 0.9;

const StatusIndicatorBox = styled.div<{ $status: string | null }>`
    background: #ffffff11;
    border: 1px solid #ffffff12;
    transition: all 250ms ease-in-out;
    padding: 1.75rem 2rem;
    cursor: pointer;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;

    &:hover {
        border: 1px solid #ffffff19;
        background: #ffffff19;
        transition-duration: 0ms;
    }

    & .status-bar {
        width: 12px;
        height: 12px;
        min-width: 12px;
        min-height: 12px;
        background-color: #ffffff11;
        z-index: 20;
        border-radius: 9999px;
        transition: all 250ms ease-in-out;

        box-shadow: ${({ $status }) =>
            !$status || $status === 'stopped' || $status === 'error'
                ? '0 0 12px 1px #C74343'
                : $status === 'running'
                  ? '0 0 12px 1px #43C760'
                  : '0 0 12px 1px #c7aa43'};

        background: ${({ $status }) =>
            !$status || $status === 'stopped' || $status === 'error'
                ? `linear-gradient(180deg, #C74343 0%, #C74343 100%)`
                : $status === 'running'
                  ? `linear-gradient(180deg, #91FFA9 0%, #43C760 100%)`
                  : `linear-gradient(180deg, #c7aa43 0%, #c7aa43 100%)`};
    }
`;

type Timer = ReturnType<typeof setInterval>;

const VpsRow = ({ vps, className }: { vps: Vps; className?: string }) => {
    const interval = useRef<Timer>(null) as React.MutableRefObject<Timer | null>;
    const [metrics, setMetrics] = useState<VpsMetrics | null>(null);
    const [copied, setCopied] = useState(false);
    const [isStarting, setIsStarting] = useState(false);

    const getMetrics = () =>
        getVpsMetrics(vps.uuid)
            .then((data) => setMetrics(data))
            .catch((error) => console.error(error));

    useEffect(() => {
        if (vps.isSuspended || !vps.isRunning) return;

        getMetrics().then(() => {
            interval.current = setInterval(() => getMetrics(), 1000); // Update every 1 second
        });

        return () => {
            if (interval.current) clearInterval(interval.current);
        };
    }, [vps.isSuspended, vps.isRunning, vps.uuid]);

    const alarms = { cpu: false, memory: false, disk: false };
    if (metrics) {
        alarms.cpu = metrics.cpu.usage_percent >= 90;
        alarms.memory = isAlarmState(metrics.memory.used_bytes, metrics.memory.total_bytes);
        alarms.disk = isAlarmState(metrics.disk.used_bytes, metrics.disk.total_bytes);
    }

    // Build IP address string
    const ipText = vps.network.ip_address || vps.network.ipv6_address || 'No IP assigned';

    const handleCopyIp = async (e: React.MouseEvent<HTMLButtonElement>) => {
        e.preventDefault();
        e.stopPropagation();

        if (!ipText || ipText === 'No IP assigned') return;

        try {
            await navigator.clipboard.writeText(ipText);
            setCopied(true);
            setTimeout(() => setCopied(false), 1200);
        } catch (err) {
            console.error('Failed to copy IP to clipboard', err);
        }
    };

    const isOffline = !vps.isRunning || vps.status === 'stopped' || vps.status === 'error';

    const handleStart = async (e: React.MouseEvent<HTMLButtonElement>) => {
        e.preventDefault();
        e.stopPropagation();
        if (isStarting) return;

        setIsStarting(true);
        try {
            await sendVpsPower(vps.uuid, 'start');
        } catch (error) {
            console.error('Failed to start VPS', error);
        } finally {
            setIsStarting(false);
        }
    };

    return (
        <StatusIndicatorBox $status={vps.status} className={className}>
            <Link to={`/vps-server/${vps.id}`} className='flex flex-row items-center gap-4 flex-grow'>
                <div className='status-bar' />
                <div className='flex flex-col gap-1 flex-grow'>
                    <div className='flex flex-row items-center gap-2'>
                        <p className='text-base font-semibold'>{vps.name}</p>
                        {vps.isSuspended && (
                            <span className='px-2 py-0.5 bg-yellow-500/20 text-yellow-500 text-xs rounded-full'>
                                Suspended
                            </span>
                        )}
                    </div>
                    <div className='flex flex-row items-center gap-4 text-sm text-zinc-400'>
                        <button
                            onClick={handleCopyIp}
                            className='hover:text-white transition-colors flex items-center gap-1'
                        >
                            {copied ? (
                                <span className='text-green-500'>Copied!</span>
                            ) : (
                                <>
                                    <span>{ipText}</span>
                                    <svg
                                        width='14'
                                        height='14'
                                        viewBox='0 0 14 14'
                                        fill='none'
                                        xmlns='http://www.w3.org/2000/svg'
                                    >
                                        <path
                                            d='M4.5 2C3.67157 2 3 2.67157 3 3.5V9.5C3 10.3284 3.67157 11 4.5 11H10.5C11.3284 11 12 10.3284 12 9.5V3.5C12 2.67157 11.3284 2 10.5 2H4.5Z'
                                            stroke='currentColor'
                                            strokeWidth='1.5'
                                        />
                                        <path
                                            d='M1 5.5C1 4.67157 1.67157 4 2.5 4H8.5'
                                            stroke='currentColor'
                                            strokeWidth='1.5'
                                            strokeLinecap='round'
                                        />
                                    </svg>
                                </>
                            )}
                        </button>
                        <span className='text-zinc-500'>•</span>
                        <span>
                            {vps.limits.memory}MB RAM • {vps.limits.cpu_cores} CPU
                        </span>
                    </div>
                </div>
            </Link>
            <div className='flex flex-row items-center gap-4'>
                {isOffline ? (
                    <button
                        onClick={handleStart}
                        disabled={isStarting}
                        className='px-4 py-2 bg-brand text-white rounded-md text-sm font-semibold hover:bg-brand/90 transition-colors disabled:opacity-50'
                    >
                        {isStarting ? 'Starting...' : 'Start'}
                    </button>
                ) : metrics ? (
                    <Fragment>
                        <div className='sm:flex hidden'>
                            <div className='flex justify-center gap-2 w-fit'>
                                <p className='text-xs text-zinc-400 font-medium w-fit whitespace-nowrap'>CPU</p>
                                <p className='text-xs font-bold w-fit whitespace-nowrap'>
                                    {metrics.cpu.usage_percent.toFixed(2)}%
                                </p>
                            </div>
                        </div>
                        <div className='sm:flex hidden'>
                            <div className='flex justify-center gap-2 w-fit'>
                                <p className='text-xs text-zinc-400 font-medium w-fit whitespace-nowrap'>RAM</p>
                                <p className='text-xs font-bold w-fit whitespace-nowrap'>
                                    {bytesToString(metrics.memory.used_bytes, 0)}
                                </p>
                            </div>
                        </div>
                        <div className='sm:flex hidden'>
                            <div className='flex justify-center gap-2 w-fit'>
                                <p className='text-xs text-zinc-400 font-medium w-fit whitespace-nowrap'>Storage</p>
                                <p className='text-xs font-bold w-fit whitespace-nowrap'>
                                    {bytesToString(metrics.disk.used_bytes, 0)}
                                </p>
                            </div>
                        </div>
                    </Fragment>
                ) : (
                    <div className='text-xs text-zinc-400'>Loading metrics...</div>
                )}
            </div>
        </StatusIndicatorBox>
    );
};

export default VpsRow;

