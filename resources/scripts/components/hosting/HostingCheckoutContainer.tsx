import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { toast } from 'sonner';
import useSWR from 'swr';

import ActionButton from '@/components/elements/ActionButton';
import { MainPageHeader } from '@/components/elements/MainPageHeader';

import createCheckoutSession, { CheckoutSessionData } from '@/api/hosting/createCheckoutSession';
import getHostingPlans, {
    CustomPlanCalculation,
    HostingPlan,
    calculateCustomPlan,
} from '@/api/hosting/getHostingPlans';
import getVpsDistributions, { VpsDistribution } from '@/api/hosting/getVpsDistributions';
import { httpErrorToHuman } from '@/api/http';
import getNests from '@/api/nests/getNests';
import getCreditsEnabled from '@/api/billing/getCreditsEnabled';

type HostingType = 'game-server' | 'vps';

const HostingCheckoutContainer = () => {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();

    // URL params
    const hostingType = (searchParams.get('type') || 'game-server') as HostingType;
    const planId = searchParams.get('plan');
    const isCustom = searchParams.get('custom') === 'true';
    const memory = searchParams.get('memory');
    const interval = searchParams.get('interval');
    const nestId = searchParams.get('nest');
    const eggId = searchParams.get('egg');
    const distributionId = searchParams.get('distribution');

    // State
    const [serverName, setServerName] = useState('');
    const [serverDescription, setServerDescription] = useState('');
    const [isCreatingCheckout, setIsCreatingCheckout] = useState(false);

    // Load plans if predefined plan is selected
    const { data: plans } = useSWR<HostingPlan[]>(planId ? ['/api/client/hosting/plans', hostingType] : null, () =>
        planId ? getHostingPlans(hostingType) : Promise.resolve([]),
    );

    // Load nests/eggs to display selected configuration (game-server only)
    const { data: nests } = useSWR(hostingType === 'game-server' ? '/api/client/nests' : null, getNests);

    // Load distributions to display selected configuration (vps only)
    const { data: distributions } = useSWR(
        hostingType === 'vps' ? '/api/client/hosting/vps-distributions' : null,
        getVpsDistributions,
    );

    // Check if credits are enabled
    const { data: creditsEnabledData } = useSWR('/api/client/billing/credits/enabled', getCreditsEnabled, {
        revalidateOnFocus: false,
    });

    const creditsEnabled = creditsEnabledData?.data?.enabled ?? false;
    const currency = creditsEnabledData?.data?.currency || 'USD';

    // Calculate custom plan pricing
    const [customPlanCalculation, setCustomPlanCalculation] = useState<CustomPlanCalculation | null>(null);

    useEffect(() => {
        document.title = 'Checkout | Pyrodactyl';

        // Validate required params
        if (hostingType === 'game-server' && (!nestId || !eggId)) {
            toast.error('Invalid checkout configuration. Please start over.');
            navigate('/hosting');
            return;
        }
        if (hostingType === 'vps' && !distributionId) {
            toast.error('Invalid checkout configuration. Please start over.');
            navigate('/hosting');
            return;
        }
        if (!planId && !isCustom) {
            toast.error('Invalid checkout configuration. Please start over.');
            navigate('/hosting');
            return;
        }

        // Load custom plan calculation if needed
        if (isCustom && memory) {
            calculateCustomPlan(parseInt(memory), interval || 'month')
                .then(setCustomPlanCalculation)
                .catch((err) => {
                    console.error('Failed to calculate custom plan:', httpErrorToHuman(err));
                    toast.error('Failed to load pricing information.');
                });
        }
    }, [hostingType, nestId, eggId, distributionId, planId, isCustom, memory, interval, navigate]);

    const selectedPlan = planId ? plans?.find((p) => p.attributes.id === parseInt(planId)) : null;
    const selectedNest =
        hostingType === 'game-server' ? nests?.find((n) => n.attributes.id === parseInt(nestId || '0')) : null;
    const selectedEgg =
        hostingType === 'game-server' && selectedNest
            ? selectedNest.attributes.relationships?.eggs?.data.find((e) => e.attributes.id === parseInt(eggId || '0'))
            : null;
    const selectedDistribution = hostingType === 'vps' ? distributions?.find((d) => d.id === distributionId) : null;

    const formatPrice = (price: number): string => {
        if (creditsEnabled) {
            // Show as credits when credits are enabled
            return `${price.toFixed(2)} credits`;
        }
        // Show as currency when using card billing
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency.toLowerCase(),
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(price);
    };

    const formatMemory = (memory: number | null | undefined): string => {
        if (!memory) return 'N/A';
        if (memory < 1024) return `${memory} MB`;
        return `${(memory / 1024).toFixed(1)} GB`;
    };

    const getFirstMonthPrice = (price: number): number => {
        return Math.round(price * 0.5 * 100) / 100; // 50% off first month, rounded to 2 decimals
    };

    const getMonthlyPrice = (): number => {
        if (selectedPlan) {
            return selectedPlan.attributes.pricing.monthly;
        }
        if (customPlanCalculation) {
            return customPlanCalculation.price_per_month;
        }
        return 0;
    };

    const getInterval = (): string => {
        if (selectedPlan) {
            return selectedPlan.attributes.interval;
        }
        return interval || 'month';
    };

    const monthlyPrice = getMonthlyPrice();
    const firstMonthPrice = getFirstMonthPrice(monthlyPrice);

    const handleCheckout = async () => {
        if (!serverName.trim()) {
            toast.error('Please enter a server name.');
            return;
        }

        if (hostingType === 'game-server' && (!selectedNest || !selectedEgg)) {
            toast.error('Invalid configuration. Please start over.');
            navigate('/hosting');
            return;
        }
        if (hostingType === 'vps' && !selectedDistribution) {
            toast.error('Invalid configuration. Please start over.');
            navigate('/hosting');
            return;
        }

        setIsCreatingCheckout(true);

        try {
            const checkoutData: CheckoutSessionData = {
                type: hostingType,
                server_name: serverName.trim(),
                server_description: serverDescription.trim() || undefined,
            };

            if (hostingType === 'game-server') {
                checkoutData.nest_id = parseInt(nestId!);
                checkoutData.egg_id = parseInt(eggId!);
            } else {
                checkoutData.distribution = selectedDistribution!.id;
            }

            if (selectedPlan) {
                checkoutData.plan_id = selectedPlan.attributes.id;
            } else if (isCustom && memory && customPlanCalculation) {
                checkoutData.custom = true;
                checkoutData.memory = parseInt(memory);
                checkoutData.interval = interval || 'month';
            }

            const response = await createCheckoutSession(checkoutData);

            // Redirect to Stripe checkout
            window.location.href = response.checkout_url;
        } catch (error: any) {
            console.error('Checkout error:', error);
            toast.error(httpErrorToHuman(error) || 'Failed to create checkout session. Please try again.');
            setIsCreatingCheckout(false);
        }
    };

    const handleBack = () => {
        const params = new URLSearchParams();
        params.set('type', hostingType);
        if (planId) {
            params.set('plan', planId);
        } else if (isCustom && memory) {
            params.set('custom', 'true');
            params.set('memory', memory);
            if (interval) params.set('interval', interval);
        }
        navigate(`/hosting/configure?${params.toString()}`);
    };

    // Validation checks
    if (hostingType === 'game-server' && (!nestId || !eggId)) {
        return null; // Will redirect in useEffect
    }
    if (hostingType === 'vps' && !distributionId) {
        return null; // Will redirect in useEffect
    }
    if (!planId && !isCustom) {
        return null; // Will redirect in useEffect
    }

    if (hostingType === 'game-server' && (!selectedNest || !selectedEgg)) {
        return (
            <div className='h-full min-h-screen bg-[#0a0a0a] overflow-y-auto -mx-2 -my-2 w-[calc(100%+1rem)]'>
                <div className='flex items-center justify-center min-h-[400px]'>
                    <div className='text-white/70'>Loading configuration...</div>
                </div>
            </div>
        );
    }
    if (hostingType === 'vps' && !selectedDistribution) {
        return (
            <div className='h-full min-h-screen bg-[#0a0a0a] overflow-y-auto -mx-2 -my-2 w-[calc(100%+1rem)]'>
                <div className='flex items-center justify-center min-h-[400px]'>
                    <div className='text-white/70'>Loading configuration...</div>
                </div>
            </div>
        );
    }

    const isReady = selectedPlan || (isCustom && customPlanCalculation);

    return (
        <div className='h-full min-h-screen bg-[#0a0a0a] overflow-y-auto -mx-2 -my-2 w-[calc(100%+1rem)]'>
            <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8'>
                <MainPageHeader title='Complete Your Purchase' />

                <div className='grid grid-cols-1 lg:grid-cols-3 gap-6'>
                    {/* Left Column: Server Configuration */}
                    <div className='lg:col-span-2 space-y-6'>
                        {/* Server Configuration */}
                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6'>
                            <h3 className='text-lg font-semibold text-white mb-4'>Server Configuration</h3>

                            <div className='space-y-4'>
                                {/* Server Name */}
                                <div>
                                    <label
                                        htmlFor='server-name'
                                        className='block text-sm font-medium text-white/70 mb-2'
                                    >
                                        Server Name <span className='text-red-400'>*</span>
                                    </label>
                                    <input
                                        id='server-name'
                                        type='text'
                                        value={serverName}
                                        onChange={(e) => setServerName(e.target.value)}
                                        placeholder='My Awesome Server'
                                        className='w-full px-4 py-2 bg-[#ffffff08] border border-[#ffffff12] rounded-lg text-white placeholder-white/30 focus:outline-none focus:border-[#ffffff30] transition-colors'
                                        maxLength={191}
                                    />
                                    <p className='mt-1 text-xs text-white/50'>
                                        Only letters, numbers, spaces, dashes, underscores, and dots are allowed.
                                    </p>
                                </div>

                                {/* Server Description */}
                                <div>
                                    <label
                                        htmlFor='server-description'
                                        className='block text-sm font-medium text-white/70 mb-2'
                                    >
                                        Server Description <span className='text-white/30'>(Optional)</span>
                                    </label>
                                    <textarea
                                        id='server-description'
                                        value={serverDescription}
                                        onChange={(e) => setServerDescription(e.target.value)}
                                        placeholder='A brief description of your server...'
                                        rows={3}
                                        className='w-full px-4 py-2 bg-[#ffffff08] border border-[#ffffff12] rounded-lg text-white placeholder-white/30 focus:outline-none focus:border-[#ffffff30] transition-colors resize-none'
                                        maxLength={500}
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Action Buttons */}
                        <div className='flex gap-4'>
                            <ActionButton
                                variant='secondary'
                                size='lg'
                                onClick={handleBack}
                                disabled={isCreatingCheckout}
                            >
                                Back
                            </ActionButton>
                            <ActionButton
                                variant='primary'
                                size='lg'
                                onClick={handleCheckout}
                                disabled={!isReady || !serverName.trim() || isCreatingCheckout}
                                className='flex-1'
                            >
                                {isCreatingCheckout ? 'Processing...' : 'Proceed to Payment'}
                            </ActionButton>
                        </div>
                    </div>

                    {/* Right Column: Order Summary */}
                    <div className='lg:col-span-1'>
                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6 sticky top-6'>
                            <h3 className='text-lg font-semibold text-white mb-4'>Order Summary</h3>

                            <div className='space-y-4'>
                                {/* Plan Details */}
                                <div className='flex justify-between items-start'>
                                    <div>
                                        <div className='font-medium text-white'>
                                            {selectedPlan ? selectedPlan.attributes.name : 'Custom Plan'}
                                        </div>
                                        {selectedPlan?.attributes.description && (
                                            <div className='text-sm text-white/60'>
                                                {selectedPlan.attributes.description}
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Resources */}
                                {selectedPlan && (
                                    <div className='pt-4 border-t border-[#ffffff12] space-y-2 text-sm text-white/70 mb-4'>
                                        {selectedPlan.attributes.memory && (
                                            <div>RAM: {formatMemory(selectedPlan.attributes.memory)}</div>
                                        )}
                                        {selectedPlan.attributes.disk && (
                                            <div>Storage: {formatMemory(selectedPlan.attributes.disk)}</div>
                                        )}
                                        {selectedPlan.attributes.cpu && <div>CPU: {selectedPlan.attributes.cpu}%</div>}
                                    </div>
                                )}

                                {isCustom && customPlanCalculation && (
                                    <div className='pt-4 border-t border-[#ffffff12] space-y-2 text-sm text-white/70'>
                                        <div>RAM: {formatMemory(customPlanCalculation.memory)}</div>
                                    </div>
                                )}

                                {/* Game/VPS Configuration */}
                                {hostingType === 'game-server' ? (
                                    <div className='pt-4 border-t border-[#ffffff12] space-y-2 text-sm mb-4'>
                                        <div className='text-white/70'>
                                            <span className='font-medium text-white'>Game Type:</span>{' '}
                                            {selectedNest?.attributes.name}
                                        </div>
                                        <div className='text-white/70'>
                                            <span className='font-medium text-white'>Game:</span>{' '}
                                            {selectedEgg?.attributes.name}
                                        </div>
                                    </div>
                                ) : (
                                    <div className='pt-4 border-t border-[#ffffff12] space-y-2 text-sm'>
                                        <div className='text-white/70'>
                                            <span className='font-medium text-white'>Distribution:</span>{' '}
                                            {selectedDistribution?.name}
                                        </div>
                                    </div>
                                )}

                                {/* Pricing Details */}
                                {isReady ? (
                                    <div className='pt-4 border-t border-[#ffffff12] space-y-3'>
                                        <div className='flex justify-between text-white/70'>
                                            <span>First Month (50% off)</span>
                                            <span className='line-through text-white/50'>
                                                {formatPrice(monthlyPrice)}
                                            </span>
                                        </div>
                                        <div className='flex justify-between text-lg font-semibold text-white'>
                                            <span>First Month Price</span>
                                            <span>{formatPrice(firstMonthPrice)}</span>
                                        </div>
                                        <div className='pt-3 border-t border-[#ffffff12] flex justify-between text-white/70'>
                                            <span>
                                                Then {formatPrice(monthlyPrice)} per {getInterval()}
                                            </span>
                                        </div>
                                    </div>
                                ) : (
                                    <div className='pt-4 border-t border-[#ffffff12] text-white/50 text-sm'>
                                        Calculating pricing...
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default HostingCheckoutContainer;
