import * as Tooltip from '@radix-ui/react-tooltip';
import {
    CategoryScale,
    Chart as ChartJS,
    type ChartOptions,
    Filler,
    LineElement,
    LinearScale,
    PointElement,
} from 'chart.js';
import { useEffect, useState } from 'react';
import { Line } from 'react-chartjs-2';

import { MainPageHeader } from '@/components/elements/MainPageHeader';
import VpsContentBlock from '@/components/elements/VpsContentBlock';
import ChartBlock from '@/components/server/console/ChartBlock';

import { bytesToString } from '@/lib/formatters';
import { hexToRgba } from '@/lib/helpers';

import getVpsMetrics from '@/api/vps/getVpsMetrics';
import { VpsMetrics } from '@/api/vps/types';

import { VpsContext } from '@/state/vps';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler);

const VpsMetricsContainer = () => {
    const vps = VpsContext.useStoreState((state) => state.vps.data);
    const [metrics, setMetrics] = useState<VpsMetrics | null>(null);
    const [metricsHistory, setMetricsHistory] = useState<{
        cpu: number[];
        memory: number[];
        disk: number[];
        network: { rx: number[]; tx: number[] };
        timestamps: number[];
    }>({
        cpu: [],
        memory: [],
        disk: [],
        network: { rx: [], tx: [] },
        timestamps: [],
    });

    useEffect(() => {
        if (!vps || !vps.isRunning) {
            return;
        }

        const loadMetrics = async () => {
            try {
                const data = await getVpsMetrics(vps.uuid);
                setMetrics(data);

                // Update history (keep last 20 data points)
                setMetricsHistory((prev) => {
                    const newHistory = {
                        cpu: [...prev.cpu.slice(-19), data.cpu.usage_percent],
                        memory: [...prev.memory.slice(-19), data.memory.usage_percent],
                        disk: [...prev.disk.slice(-19), data.disk.usage_percent],
                        network: {
                            rx: [...prev.network.rx.slice(-19), data.network.rx_bytes],
                            tx: [...prev.network.tx.slice(-19), data.network.tx_bytes],
                        },
                        timestamps: [...prev.timestamps.slice(-19), data.timestamp],
                    };
                    return newHistory;
                });
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

    const chartOptions: ChartOptions<'line'> = {
        maintainAspectRatio: false,
        animation: false,
        plugins: {
            legend: { display: false },
            title: { display: false },
            tooltip: { enabled: false },
        },
        layout: {
            padding: 0,
        },
        scales: {
            x: {
                min: 0,
                max: 19,
                type: 'linear',
                grid: {
                    display: false,
                },
                ticks: {
                    display: false,
                },
            },
            y: {
                min: 0,
                max: 100,
                type: 'linear',
                grid: {
                    display: false,
                },
                ticks: {
                    display: true,
                    count: 3,
                    font: {
                        size: 11,
                        weight: 600,
                    },
                },
            },
        },
        elements: {
            point: {
                radius: 0,
            },
            line: {
                tension: 0.15,
            },
        },
    };

    const cpuData = {
        labels: Array(20).fill(''),
        datasets: [
            {
                label: 'CPU Usage',
                data: Array(20 - metricsHistory.cpu.length)
                    .fill(null)
                    .concat(metricsHistory.cpu.map((v) => (v === null ? null : Number(v.toFixed(2))))),
                borderColor: '#facc15',
                backgroundColor: hexToRgba('#facc15', 0.09),
                fill: true,
            },
        ],
    };

    const memoryData = {
        labels: Array(20).fill(''),
        datasets: [
            {
                label: 'Memory Usage',
                data: Array(20 - metricsHistory.memory.length)
                    .fill(null)
                    .concat(metricsHistory.memory.map((v) => (v === null ? null : Number(v.toFixed(2))))),
                borderColor: '#60a5fa',
                backgroundColor: hexToRgba('#60a5fa', 0.09),
                fill: true,
            },
        ],
    };

    const networkData = {
        labels: Array(20).fill(''),
        datasets: [
            {
                label: 'Network In',
                data: Array(20 - metricsHistory.network.rx.length)
                    .fill(null)
                    .concat(metricsHistory.network.rx.map((v) => (v === null ? null : v))),
                borderColor: '#facc15',
                backgroundColor: hexToRgba('#facc15', 0.09),
                fill: true,
            },
            {
                label: 'Network Out',
                data: Array(20 - metricsHistory.network.tx.length)
                    .fill(null)
                    .concat(metricsHistory.network.tx.map((v) => (v === null ? null : v))),
                borderColor: '#60a5fa',
                backgroundColor: hexToRgba('#60a5fa', 0.09),
                fill: true,
            },
        ],
    };

    const networkChartOptions: ChartOptions<'line'> = {
        ...chartOptions,
        scales: {
            ...chartOptions.scales,
            y: {
                ...chartOptions.scales?.y,
                ticks: {
                    ...chartOptions.scales?.y?.ticks,
                    callback(value) {
                        return bytesToString(typeof value === 'string' ? parseInt(value, 10) : value);
                    },
                },
            },
        },
    };

    return (
        <VpsContentBlock title={'Metrics'}>
            <MainPageHeader title={'Metrics'} description={'Real-time resource usage statistics for your VPS'} />

            {!vps.isRunning ? (
                <div className='text-center py-12'>
                    <p className='text-zinc-400'>VPS must be running to display metrics.</p>
                </div>
            ) : !metrics ? (
                <div className='text-center py-12'>
                    <p className='text-zinc-400'>Loading metrics...</p>
                </div>
            ) : (
                <Tooltip.Provider>
                    <div className='grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4'>
                        <div className='transform-gpu skeleton-anim-2'>
                            <ChartBlock title={'CPU Usage'}>
                                <Line aria-label='CPU Usage' role='img' data={cpuData} options={chartOptions} />
                            </ChartBlock>
                        </div>
                        <div className='transform-gpu skeleton-anim-2'>
                            <ChartBlock title={'Memory Usage'}>
                                <Line aria-label='Memory Usage' role='img' data={memoryData} options={chartOptions} />
                            </ChartBlock>
                        </div>
                        <div className='transform-gpu skeleton-anim-2'>
                            <ChartBlock title={'Network I/O'}>
                                <Line
                                    aria-label='Network I/O'
                                    role='img'
                                    data={networkData}
                                    options={networkChartOptions}
                                />
                            </ChartBlock>
                        </div>
                    </div>

                    <div className='mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4'>
                        <div className='bg-gradient-to-b from-[#ffffff08] to-[#ffffff05] border-[1px] border-[#ffffff12] rounded-xl p-4'>
                            <p className='text-sm text-zinc-400 mb-1'>CPU Usage</p>
                            <p className='text-2xl font-bold'>{metrics.cpu.usage_percent.toFixed(2)}%</p>
                            <p className='text-xs text-zinc-500 mt-1'>{metrics.cpu.cores} cores</p>
                        </div>
                        <div className='bg-gradient-to-b from-[#ffffff08] to-[#ffffff05] border-[1px] border-[#ffffff12] rounded-xl p-4'>
                            <p className='text-sm text-zinc-400 mb-1'>Memory Usage</p>
                            <p className='text-2xl font-bold'>{metrics.memory.usage_percent.toFixed(2)}%</p>
                            <p className='text-xs text-zinc-500 mt-1'>
                                {bytesToString(metrics.memory.used_bytes)} / {bytesToString(metrics.memory.total_bytes)}
                            </p>
                        </div>
                        <div className='bg-gradient-to-b from-[#ffffff08] to-[#ffffff05] border-[1px] border-[#ffffff12] rounded-xl p-4'>
                            <p className='text-sm text-zinc-400 mb-1'>Disk Usage</p>
                            <p className='text-2xl font-bold'>{metrics.disk.usage_percent.toFixed(2)}%</p>
                            <p className='text-xs text-zinc-500 mt-1'>
                                {bytesToString(metrics.disk.used_bytes)} / {bytesToString(metrics.disk.total_bytes)}
                            </p>
                        </div>
                        <div className='bg-gradient-to-b from-[#ffffff08] to-[#ffffff05] border-[1px] border-[#ffffff12] rounded-xl p-4'>
                            <p className='text-sm text-zinc-400 mb-1'>Uptime</p>
                            <p className='text-2xl font-bold'>
                                {metrics.uptime > 0
                                    ? `${Math.floor(metrics.uptime / 86400)}d ${Math.floor((metrics.uptime % 86400) / 3600)}h ${Math.floor((metrics.uptime % 3600) / 60)}m`
                                    : '0m'}
                            </p>
                        </div>
                    </div>
                </Tooltip.Provider>
            )}
        </VpsContentBlock>
    );
};

export default VpsMetricsContainer;
