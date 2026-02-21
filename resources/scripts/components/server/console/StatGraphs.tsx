import { ArrowDownToLine, ArrowUpToLine } from '@gravity-ui/icons';
import * as Tooltip from '@radix-ui/react-tooltip';
import { useEffect, useRef, useState } from 'react';
import { Line } from 'react-chartjs-2';

import ChartBlock from '@/components/server/console/ChartBlock';
import { useChart, useChartTickLabel } from '@/components/server/console/chart';
import { SocketEvent } from '@/components/server/events';

import { bytesToString } from '@/lib/formatters';
import { hexToRgba } from '@/lib/helpers';

import { getServerMetrics } from '@/api/server/getServerMetrics';

import { ServerContext } from '@/state/server';

import useWebsocketEvent from '@/plugins/useWebsocketEvent';

const StatGraphs = () => {
    const status = ServerContext.useStoreState((state) => state.status.value);
    const limits = ServerContext.useStoreState((state) => state.server.data!.limits);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const previous = useRef<Record<'tx' | 'rx', number>>({ tx: -1, rx: -1 });

    const [windowLabel, setWindowLabel] = useState<'5m' | '15m' | '1h' | '6h' | '24h'>('5m');

    const cpu = useChartTickLabel('CPU', limits.cpu, '%', 2);
    const memory = useChartTickLabel('Memory', limits.memory, 'MiB');
    const network = useChart('Network', {
        sets: 2,
        options: {
            scales: {
                y: {
                    ticks: {
                        callback(value) {
                            return bytesToString(typeof value === 'string' ? parseInt(value, 10) : value);
                        },
                    },
                },
            },
        },
        callback(opts, index) {
            return {
                ...opts,
                label: !index ? 'Network In' : 'Network Out',
                borderColor: !index ? '#facc15' : '#60a5fa',
                backgroundColor: hexToRgba(!index ? '#facc15' : '#60a5fa', 0.09),
            };
        },
    });

    useEffect(() => {
        if (status === 'offline') {
            cpu.clear();
            memory.clear();
            network.clear();
        }
    }, [status]);

    useWebsocketEvent(SocketEvent.STATS, (data: string) => {
        let values: any = {};
        try {
            values = JSON.parse(data);
        } catch (e) {
            return;
        }
        cpu.push(values.cpu_absolute);
        memory.push(Math.floor(values.memory_bytes / 1024 / 1024));
        network.push([
            previous.current.tx < 0 ? 0 : Math.max(0, values.network.tx_bytes - previous.current.tx),
            previous.current.rx < 0 ? 0 : Math.max(0, values.network.rx_bytes - previous.current.rx),
        ]);

        previous.current = { tx: values.network.tx_bytes, rx: values.network.rx_bytes };
    });

    // Map label to number of points (assuming 15s per point)
    const windowMap: Record<typeof windowLabel, number> = {
        '5m': 20, // 5 * 60 / 15
        '15m': 60,
        '1h': 240,
        '6h': 240, // cap for now
        '24h': 240, // cap for now
    };

    // Load historical metrics when the window changes.
    useEffect(() => {
        let isCancelled = false;

        const loadHistory = async () => {
            try {
                const pointsCount = windowMap[windowLabel];
                cpu.setWindow(pointsCount);
                memory.setWindow(pointsCount);
                network.setWindow(pointsCount);

                cpu.clear();
                memory.clear();
                network.clear();

                const data = await getServerMetrics(uuid, windowLabel);

                if (isCancelled) return;

                const pts = data.points || [];

                let lastRx = -1;
                let lastTx = -1;

                pts.forEach((p, index) => {
                    cpu.push(p.cpu ?? 0);
                    memory.push(Math.floor((p.memory_bytes ?? 0) / 1024 / 1024));

                    if (index === 0) {
                        network.push([0, 0]);
                    } else {
                        const prev = pts[index - 1];
                        network.push([
                            Math.max(0, (p.network_tx_bytes ?? 0) - (prev.network_tx_bytes ?? 0)),
                            Math.max(0, (p.network_rx_bytes ?? 0) - (prev.network_rx_bytes ?? 0)),
                        ]);
                    }

                    lastTx = p.network_tx_bytes ?? lastTx;
                    lastRx = p.network_rx_bytes ?? lastRx;
                });

                if (lastTx >= 0 && lastRx >= 0) {
                    previous.current = { tx: lastTx, rx: lastRx };
                }
            } catch (e) {
                // swallow for now; charts will just show live data
                console.error('Failed to load server metrics history', e);
            }
        };

        loadHistory();

        return () => {
            isCancelled = true;
        };
    }, [uuid, windowLabel]);

    return (
        <Tooltip.Provider>
            <div className='flex justify-between items-center mb-3'>
                <h2 className='text-xs font-semibold tracking-wide text-zinc-300 uppercase'>Resource Metrics</h2>
                <div className='flex items-center gap-1 text-[11px] text-zinc-300'>
                    {(
                        [
                            ['5m', '5m'],
                            ['15m', '15m'],
                            ['1h', '1h'],
                            ['6h', '6h'],
                            ['24h', '24h'],
                        ] as const
                    ).map(([value, label]) => (
                        <button
                            key={value}
                            type='button'
                            onClick={() => setWindowLabel(value)}
                            className={`px-3 py-1 rounded-md border transition-colors ${
                                windowLabel === value
                                    ? 'bg-[#ffffff10] border-[#ffffff33] text-white'
                                    : 'bg-transparent border-transparent text-zinc-400 hover:text-zinc-100 hover:border-[#ffffff22]'
                            }`}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>
            <div className='grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4'>
                <div
                    className='transform-gpu skeleton-anim-2'
                    style={{
                        animationDelay: `250ms`,
                        animationTimingFunction:
                            'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                    }}
                >
                    <ChartBlock title={'CPU'}>
                        <Line aria-label='CPU Usage' role='img' {...cpu.props} />
                    </ChartBlock>
                </div>
                <div
                    className='transform-gpu skeleton-anim-2'
                    style={{
                        animationDelay: `275ms`,
                        animationTimingFunction:
                            'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                    }}
                >
                    <ChartBlock title={'RAM'}>
                        <Line aria-label='Memory Usage' role='img' {...memory.props} />
                    </ChartBlock>
                </div>
                <div
                    className='transform-gpu skeleton-anim-2'
                    style={{
                        animationDelay: `300ms`,
                        animationTimingFunction:
                            'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                    }}
                >
                    <ChartBlock
                        title={'Network Activity'}
                        legend={
                            <div className='flex gap-2'>
                                <Tooltip.Root delayDuration={200}>
                                    <Tooltip.Trigger asChild>
                                        <div className='flex items-center cursor-default'>
                                            <ArrowDownToLine
                                                width={22}
                                                height={22}
                                                fill='currentColor'
                                                className='mr-2 text-yellow-400'
                                            />
                                        </div>
                                    </Tooltip.Trigger>
                                    <Tooltip.Portal>
                                        <Tooltip.Content
                                            side='top'
                                            className='px-2 py-1 text-sm bg-gray-800 text-gray-100 rounded shadow-lg'
                                            sideOffset={5}
                                        >
                                            Inbound
                                            <Tooltip.Arrow className='fill-gray-800' />
                                        </Tooltip.Content>
                                    </Tooltip.Portal>
                                </Tooltip.Root>

                                <Tooltip.Root delayDuration={200}>
                                    <Tooltip.Trigger asChild>
                                        <div className='flex items-center cursor-default'>
                                            <ArrowUpToLine
                                                width={22}
                                                height={22}
                                                fill='currentColor'
                                                className='text-blue-400'
                                            />
                                        </div>
                                    </Tooltip.Trigger>
                                    <Tooltip.Portal>
                                        <Tooltip.Content
                                            side='top'
                                            className='px-2 py-1 text-sm bg-gray-800 text-gray-100 rounded shadow-lg'
                                            sideOffset={5}
                                        >
                                            Outbound
                                            <Tooltip.Arrow className='fill-gray-800' />
                                        </Tooltip.Content>
                                    </Tooltip.Portal>
                                </Tooltip.Root>
                            </div>
                        }
                    >
                        <Line
                            aria-label='Network Activity. Download and upload activity'
                            role='img'
                            {...network.props}
                        />
                    </ChartBlock>
                </div>
            </div>
        </Tooltip.Provider>
    );
};

export default StatGraphs;
