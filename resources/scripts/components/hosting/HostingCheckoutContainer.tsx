import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { toast } from 'sonner';
import useSWR from 'swr';

import ActionButton from '@/components/elements/ActionButton';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import PageContentBlock from '@/components/elements/PageContentBlock';

import createCheckoutSession, { CheckoutSessionData } from '@/api/hosting/createCheckoutSession';
import { httpErrorToHuman } from '@/api/http';
import getHostingPlans, { calculateCustomPlan, CustomPlanCalculation, HostingPlan } from '@/api/hosting/getHostingPlans';
import getNests from '@/api/nests/getNests';

const HostingCheckoutContainer = () => {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();

    // URL params
    const planId = searchParams.get('plan');
    const isCustom = searchParams.get('custom') === 'true';
    const memory = searchParams.get('memory');
    const interval = searchParams.get('interval');
    const nestId = searchParams.get('nest');
    const eggId = searchParams.get('egg');

    // State
    const [serverName, setServerName] = useState('');
    const [serverDescription, setServerDescription] = useState('');
    const [isCreatingCheckout, setIsCreatingCheckout] = useState(false);

    // Load plans if predefined plan is selected
    const { data: plans } = useSWR<HostingPlan[]>(
        planId ? '/api/client/hosting/plans' : null,
        () => (planId ? getHostingPlans() : Promise.resolve([])),
    );

    // Load nests/eggs to display selected configuration
    const { data: nests } = useSWR('/api/client/nests', getNests);

    // Calculate custom plan pricing
    const [customPlanCalculation, setCustomPlanCalculation] = useState<CustomPlanCalculation | null>(null);

    useEffect(() => {
        document.title = 'Checkout | Pyrodactyl';

        // Validate required params
        if (!nestId || !eggId || (!planId && !isCustom)) {
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
    }, [nestId, eggId, planId, isCustom, memory, interval, navigate]);

    const selectedPlan = planId ? plans?.find((p) => p.attributes.id === parseInt(planId)) : null;
    const selectedNest = nests?.find((n) => n.attributes.id === parseInt(nestId || '0'));
    const selectedEgg = selectedNest?.attributes.relationships?.eggs?.data.find(
        (e) => e.attributes.id === parseInt(eggId || '0'),
    );

    const formatPrice = (price: number, currency: string = 'USD'): string => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency,
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

        if (!selectedNest || !selectedEgg) {
            toast.error('Invalid configuration. Please start over.');
            navigate('/hosting');
            return;
        }

        setIsCreatingCheckout(true);

        try {
            const checkoutData: CheckoutSessionData = {
                nest_id: parseInt(nestId!),
                egg_id: parseInt(eggId!),
                server_name: serverName.trim(),
                server_description: serverDescription.trim() || undefined,
            };

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
    if (!nestId || !eggId || (!planId && !isCustom)) {
        return null; // Will redirect in useEffect
    }

    if (!selectedNest || !selectedEgg) {
        return (
            <PageContentBlock title='Checkout'>
                <div className='flex items-center justify-center min-h-[400px]'>
                    <div className='text-white/70'>Loading configuration...</div>
                </div>
            </PageContentBlock>
        );
    }

    const isReady = selectedPlan || (isCustom && customPlanCalculation);

    return (
        <PageContentBlock title='Checkout'>
            <MainPageHeader title='Complete Your Purchase' />

            <div className='max-w-3xl space-y-6'>
                {/* Order Summary */}
                <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6'>
                    <h3 className='text-lg font-semibold text-white mb-4'>Order Summary</h3>

                    <div className='space-y-4'>
                        {/* Plan Details */}
                        <div className='flex justify-between items-start'>
                            <div>
                                <div className='font-medium text-white'>
                                    {selectedPlan ? selectedPlan.attributes.name : 'Custom Plan'}
                                </div>
                                {selectedPlan?.attributes.description && (
                                    <div className='text-sm text-white/60'>{selectedPlan.attributes.description}</div>
                                )}
                            </div>
                            <div className='text-right'>
                                <div className='font-medium text-white'>
                                    {formatPrice(firstMonthPrice)} / first month
                                </div>
                                <div className='text-sm text-white/60'>
                                    Then {formatPrice(monthlyPrice)} / {getInterval()}
                                </div>
                            </div>
                        </div>

                        {/* Resources */}
                        {selectedPlan && (
                            <div className='pt-4 border-t border-[#ffffff12] space-y-2 text-sm text-white/70'>
                                {selectedPlan.attributes.memory && (
                                    <div>RAM: {formatMemory(selectedPlan.attributes.memory)}</div>
                                )}
                                {selectedPlan.attributes.disk && (
                                    <div>Storage: {formatMemory(selectedPlan.attributes.disk)}</div>
                                )}
                                {selectedPlan.attributes.cpu && (
                                    <div>CPU: {selectedPlan.attributes.cpu}%</div>
                                )}
                            </div>
                        )}

                        {isCustom && customPlanCalculation && (
                            <div className='pt-4 border-t border-[#ffffff12] space-y-2 text-sm text-white/70'>
                                <div>RAM: {formatMemory(customPlanCalculation.memory)}</div>
                            </div>
                        )}

                        {/* Game Configuration */}
                        <div className='pt-4 border-t border-[#ffffff12] space-y-2 text-sm'>
                            <div className='text-white/70'>
                                <span className='font-medium text-white'>Game Type:</span> {selectedNest.attributes.name}
                            </div>
                            <div className='text-white/70'>
                                <span className='font-medium text-white'>Game:</span> {selectedEgg.attributes.name}
                            </div>
                        </div>
                    </div>
                </div>

                {/* Server Configuration */}
                <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6'>
                    <h3 className='text-lg font-semibold text-white mb-4'>Server Configuration</h3>

                    <div className='space-y-4'>
                        {/* Server Name */}
                        <div>
                            <label htmlFor='server-name' className='block text-sm font-medium text-white/70 mb-2'>
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
                            <label htmlFor='server-description' className='block text-sm font-medium text-white/70 mb-2'>
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

                {/* Pricing Summary */}
                <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6'>
                    <h3 className='text-lg font-semibold text-white mb-4'>Pricing Summary</h3>

                    {isReady ? (
                        <div className='space-y-3'>
                            <div className='flex justify-between text-white/70'>
                                <span>First Month (50% off)</span>
                                <span className='line-through text-white/50'>{formatPrice(monthlyPrice)}</span>
                            </div>
                            <div className='flex justify-between text-lg font-semibold text-white'>
                                <span>First Month Price</span>
                                <span>{formatPrice(firstMonthPrice)}</span>
                            </div>
                            <div className='pt-3 border-t border-[#ffffff12] flex justify-between text-white/70'>
                                <span>Then {formatPrice(monthlyPrice)} per {getInterval()}</span>
                            </div>
                        </div>
                    ) : (
                        <div className='text-white/50'>Calculating pricing...</div>
                    )}
                </div>

                {/* Action Buttons */}
                <div className='flex gap-4 pt-4'>
                    <ActionButton variant='secondary' size='lg' onClick={handleBack} disabled={isCreatingCheckout}>
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
        </PageContentBlock>
    );
};

export default HostingCheckoutContainer;
