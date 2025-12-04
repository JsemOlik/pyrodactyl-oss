import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import useSWR from 'swr';

import ActionButton from '@/components/elements/ActionButton';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import PageContentBlock from '@/components/elements/PageContentBlock';
import HugeIconsCPU from '@/components/elements/hugeicons/CPU';
import HugeIconsZap from '@/components/elements/hugeicons/Zap';

import {
    CustomPlanCalculation,
    HostingPlan,
    calculateCustomPlan,
    default as getHostingPlans,
} from '@/api/hosting/getHostingPlans';
import { httpErrorToHuman } from '@/api/http';

const HostingContainer = () => {
    const navigate = useNavigate();
    const { data: plans, error, isLoading } = useSWR<HostingPlan[]>('/api/client/hosting/plans', getHostingPlans);

    const [customMemory, setCustomMemory] = useState<number>(2048); // Default 2GB
    const customInterval = 'month'; // Always use monthly for custom plans
    const [customPlanCalculation, setCustomPlanCalculation] = useState<CustomPlanCalculation | null>(null);
    const [isCalculating, setIsCalculating] = useState(false);

    useEffect(() => {
        document.title = 'Hosting | Pyrodactyl';
    }, []);

    useEffect(() => {
        const calculatePrice = async () => {
            if (customMemory < 512 || customMemory > 32768) {
                return;
            }

            setIsCalculating(true);
            try {
                const calculation = await calculateCustomPlan(customMemory, customInterval);
                setCustomPlanCalculation(calculation);
            } catch (err) {
                console.error('Failed to calculate custom plan:', httpErrorToHuman(err));
            } finally {
                setIsCalculating(false);
            }
        };

        const timeoutId = setTimeout(calculatePrice, 500); // Debounce
        return () => clearTimeout(timeoutId);
    }, [customMemory, customInterval]);

    const formatPrice = (price: number, currency: string = 'USD'): string => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(price);
    };

    const formatMemory = (memory: number | null): string => {
        if (!memory) return 'N/A';
        if (memory < 1024) return `${memory} MB`;
        return `${(memory / 1024).toFixed(0)} GB`;
    };

    const getFirstMonthPrice = (price: number): number => {
        return Math.round(price * 0.5); // 50% off first month
    };

    const getVCores = (cpu: number | null): number => {
        if (!cpu) return 0;
        return Math.round(cpu / 100); // Convert percentage to vCores
    };

    const handlePlanSelect = (plan: HostingPlan) => {
        // Navigate to configuration page with plan selected
        navigate(`/hosting/configure?plan=${plan.attributes.id}`);
    };

    const handleCustomPlanSelect = () => {
        if (!customPlanCalculation) {
            return;
        }
        // Navigate to configuration page with custom plan
        navigate(`/hosting/configure?custom=true&memory=${customMemory}&interval=${customInterval}`);
    };

    if (isLoading) {
        return (
            <PageContentBlock title='Hosting'>
                <div className='flex items-center justify-center min-h-[400px]'>
                    <div className='text-white/70'>Loading plans...</div>
                </div>
            </PageContentBlock>
        );
    }

    if (error) {
        return (
            <PageContentBlock title='Hosting'>
                <div className='flex items-center justify-center min-h-[400px]'>
                    <div className='text-red-400'>Failed to load hosting plans. Please try again later.</div>
                </div>
            </PageContentBlock>
        );
    }

    return (
        <PageContentBlock title='Hosting'>
            <MainPageHeader title='Choose Your Plan' />

            <div className='space-y-8'>
                {/* Predefined Plans */}
                {plans && plans.length > 0 && (
                    <div>
                        <div className='grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4'>
                            {plans.map((plan, index) => {
                                const attrs = plan.attributes;
                                const monthlyPrice = attrs.pricing.monthly;
                                const firstMonthPrice = getFirstMonthPrice(monthlyPrice);
                                const isMostPopular = index === 2; // Mark 3rd plan as most popular
                                const vCores = getVCores(attrs.cpu);

                                return (
                                    <div
                                        key={plan.attributes.id}
                                        className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6 hover:border-[#ffffff20] transition-all relative flex flex-col'
                                    >
                                        {/* Icon */}
                                        <div className='mb-4'>
                                            <HugeIconsCPU fill='#ff6b35' className='w-8 h-8' />
                                        </div>

                                        {/* Most Popular Badge */}
                                        {isMostPopular && (
                                            <div className='absolute top-4 right-4'>
                                                <span className='bg-brand text-white text-xs font-semibold px-3 py-1 rounded-full'>
                                                    Most Popular
                                                </span>
                                            </div>
                                        )}

                                        {/* Plan Name */}
                                        <h3 className='text-2xl font-bold text-white mb-4'>{attrs.name}</h3>

                                        {/* Pricing */}
                                        <div className='mb-6'>
                                            <div className='flex items-baseline gap-2 mb-1'>
                                                <span className='text-sm text-white/70 line-through'>
                                                    {formatPrice(monthlyPrice)}
                                                </span>
                                                <span className='text-xl font-bold text-white'>
                                                    {formatPrice(firstMonthPrice)}
                                                </span>
                                            </div>
                                            <div className='text-xs text-white/50 mb-1'>FIRST MONTH</div>
                                            <div className='text-sm text-white/70'>
                                                Then {formatPrice(monthlyPrice)}/month
                                            </div>
                                        </div>

                                        {/* Specifications */}
                                        <div className='space-y-2 mb-6 flex-1'>
                                            <div className='text-sm text-white/70'>AMD Ryzen™ 9 9950X ³</div>
                                            {vCores > 0 && (
                                                <div className='text-sm text-white/70'>{vCores} vCores @ ~5.7 GHz</div>
                                            )}
                                            {attrs.memory && (
                                                <div className='text-sm text-white/70'>
                                                    {formatMemory(attrs.memory)} DDR5 RAM
                                                </div>
                                            )}
                                            <div className='text-sm text-white/70'>Unlimited NVMe Storage</div>
                                            <div className='text-sm text-white/70'>128 Free Backup Slots</div>
                                            <div className='text-sm text-white/70'>12 Port Allocations</div>
                                            <div className='text-sm text-white/70'>Free pyro.social subdomain</div>
                                            <div className='text-sm text-white/70'>Always-On DDoS Protection</div>
                                        </div>

                                        {/* Recommended Games Placeholder */}
                                        <div className='mb-6'>
                                            <div className='flex items-center gap-2'>
                                                <div className='flex gap-1'>
                                                    {[1, 2, 3, 4].map((i) => (
                                                        <div
                                                            key={i}
                                                            className='w-8 h-8 bg-[#ffffff12] rounded flex items-center justify-center text-xs text-white/50'
                                                        >
                                                            {i}
                                                        </div>
                                                    ))}
                                                </div>
                                                <span className='text-xs text-white/50'>+{5 + index * 2}</span>
                                            </div>
                                        </div>

                                        {/* Configure Button */}
                                        <ActionButton
                                            variant={isMostPopular ? 'primary' : 'secondary'}
                                            size='lg'
                                            className='w-full'
                                            onClick={() => handlePlanSelect(plan)}
                                        >
                                            Configure Server
                                        </ActionButton>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* Custom Plan */}
                <div>
                    <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6'>
                        <div className='flex flex-col md:flex-row gap-8'>
                            {/* Left Side - Configuration */}
                            <div className='flex-1'>
                                {/* Icon and Title */}
                                <div className='flex items-center gap-3 mb-6'>
                                    <HugeIconsZap fill='#ff6b35' className='w-8 h-8' />
                                    <h3 className='text-2xl font-bold text-white'>Custom</h3>
                                </div>

                                {/* RAM Slider */}
                                <div className='mb-6'>
                                    <label className='block text-sm font-medium text-white/70 mb-4'>
                                        RAM: {formatMemory(customMemory)}
                                    </label>
                                    <input
                                        type='range'
                                        min={2048}
                                        max={32768}
                                        step={1024}
                                        value={customMemory}
                                        onChange={(e) => setCustomMemory(parseInt(e.target.value))}
                                        className='w-full h-2 bg-[#ffffff17] rounded-lg appearance-none cursor-pointer accent-brand'
                                    />
                                    <div className='flex justify-between text-xs text-white/50 mt-1'>
                                        <span>2GB</span>
                                        <span>32GB</span>
                                    </div>
                                </div>

                                {/* Specifications */}
                                <div className='space-y-2 mb-6'>
                                    <div className='text-sm text-white/70'>AMD Ryzen™ 9 9950X ³</div>
                                    <div className='text-sm text-white/70'>{formatMemory(customMemory)} DDR5 RAM</div>
                                    <div className='text-sm text-white/70'>128 Free Backup Slots</div>
                                    <div className='text-sm text-white/70'>Free pyro.social subdomain</div>
                                    <div className='text-sm text-white/70'>8 vCores @ ~5.7 GHz</div>
                                    <div className='text-sm text-white/70'>Unlimited NVMe Storage</div>
                                    <div className='text-sm text-white/70'>12 Port Allocations</div>
                                    <div className='text-sm text-white/70'>Always-On DDoS Protection</div>
                                </div>

                                {/* Recommended Games Placeholder */}
                                <div className='mb-6'>
                                    <div className='flex items-center gap-2'>
                                        <div className='flex gap-1'>
                                            {[1, 2, 3, 4].map((i) => (
                                                <div
                                                    key={i}
                                                    className='w-8 h-8 bg-[#ffffff12] rounded flex items-center justify-center text-xs text-white/50'
                                                >
                                                    {i}
                                                </div>
                                            ))}
                                        </div>
                                        <span className='text-xs text-white/50'>+8</span>
                                    </div>
                                </div>
                            </div>

                            {/* Right Side - Pricing */}
                            <div className='md:w-80 flex flex-col justify-between'>
                                {customPlanCalculation && (
                                    <div className='mb-6'>
                                        <div className='flex items-baseline gap-2 mb-1'>
                                            <span className='text-sm text-white/70 line-through'>
                                                {formatPrice(
                                                    customPlanCalculation.price,
                                                    customPlanCalculation.currency,
                                                )}
                                            </span>
                                            <span className='text-xl font-bold text-white'>
                                                {formatPrice(
                                                    getFirstMonthPrice(customPlanCalculation.price),
                                                    customPlanCalculation.currency,
                                                )}
                                            </span>
                                        </div>
                                        <div className='text-xs text-white/50 mb-1'>FIRST MONTH</div>
                                        <div className='text-sm text-white/70'>
                                            Then{' '}
                                            {formatPrice(customPlanCalculation.price, customPlanCalculation.currency)}
                                            /month
                                        </div>
                                    </div>
                                )}

                                <ActionButton
                                    variant='secondary'
                                    size='lg'
                                    className='w-full'
                                    onClick={handleCustomPlanSelect}
                                    disabled={!customPlanCalculation || isCalculating}
                                >
                                    {isCalculating ? 'Calculating...' : 'Configure Server'}
                                </ActionButton>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </PageContentBlock>
    );
};

export default HostingContainer;
