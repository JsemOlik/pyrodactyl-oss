import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { toast } from 'sonner';
import useSWR from 'swr';

import ActionButton from '@/components/elements/ActionButton';
import { MainPageHeader } from '@/components/elements/MainPageHeader';

import getCreditsEnabled from '@/api/billing/getCreditsEnabled';
import createCheckoutSession, { CheckoutSessionData } from '@/api/hosting/createCheckoutSession';
import getHostingPlans, {
    CustomPlanCalculation,
    HostingPlan,
    calculateCustomPlan,
} from '@/api/hosting/getHostingPlans';
import getVpsDistributions, { VpsDistribution } from '@/api/hosting/getVpsDistributions';
import { AvailableDomain, checkSubdomainAvailability, getAvailableDomains } from '@/api/hosting/subdomain';
import { httpErrorToHuman } from '@/api/http';
import getNests from '@/api/nests/getNests';

type HostingType = 'game-server' | 'vps';
type CheckoutStep = 'configuration' | 'subdomain' | 'payment';

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

    // Step management
    const [currentStep, setCurrentStep] = useState<CheckoutStep>('configuration');
    const [stepDirection, setStepDirection] = useState<'forward' | 'backward'>('forward');
    const [isTransitioning, setIsTransitioning] = useState(false);

    // State
    const [serverName, setServerName] = useState('');
    const [serverDescription, setServerDescription] = useState('');
    const [subdomain, setSubdomain] = useState('');
    const [selectedDomainId, setSelectedDomainId] = useState<number | null>(null);
    const [isSubdomainOptional, setIsSubdomainOptional] = useState(true);
    const [checkingAvailability, setCheckingAvailability] = useState(false);
    const [availabilityStatus, setAvailabilityStatus] = useState<{
        checked: boolean;
        available: boolean;
        message: string;
    } | null>(null);
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

    // Load available domains for subdomain selection
    const { data: availableDomains } = useSWR('/api/client/hosting/subdomain/domains', getAvailableDomains, {
        revalidateOnFocus: false,
    });

    // Check if credits are enabled
    const { data: creditsEnabledData } = useSWR('/api/client/billing/credits/enabled', getCreditsEnabled, {
        revalidateOnFocus: false,
    });

    const creditsEnabled = creditsEnabledData?.data?.enabled ?? false;
    const currency = creditsEnabledData?.data?.currency || 'USD';

    // Calculate custom plan pricing
    const [customPlanCalculation, setCustomPlanCalculation] = useState<CustomPlanCalculation | null>(null);

    const debounceTimeoutRef = useRef<NodeJS.Timeout | null>(null);

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

        // Set default domain if available
        if (availableDomains && availableDomains.length > 0 && !selectedDomainId) {
            const defaultDomain = availableDomains.find((d) => d.is_default) || availableDomains[0];
            setSelectedDomainId(defaultDomain.id);
        }
    }, [
        hostingType,
        nestId,
        eggId,
        distributionId,
        planId,
        isCustom,
        memory,
        interval,
        navigate,
        availableDomains,
        selectedDomainId,
    ]);

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
            return `${price.toFixed(2)} credits`;
        }
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

    const getFirstMonthPrice = (price: number, plan?: HostingPlan): number => {
        if (plan?.attributes.first_month_sales_percentage && plan.attributes.first_month_sales_percentage > 0) {
            const discount = plan.attributes.first_month_sales_percentage / 100;
            return Math.round(price * (1 - discount) * 100) / 100;
        }
        return price;
    };

    const getFirstMonthDiscount = (plan?: HostingPlan): number | null => {
        if (plan?.attributes.first_month_sales_percentage && plan.attributes.first_month_sales_percentage > 0) {
            return plan.attributes.first_month_sales_percentage;
        }
        return null;
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
    const firstMonthPrice = getFirstMonthPrice(monthlyPrice, selectedPlan || undefined);
    const firstMonthDiscount = getFirstMonthDiscount(selectedPlan || undefined);

    // Subdomain availability checking
    const checkSubdomain = useCallback(async (subdomainValue: string, domainIdValue: number) => {
        if (!subdomainValue?.trim() || !domainIdValue) {
            setAvailabilityStatus(null);
            return;
        }

        try {
            setCheckingAvailability(true);
            const response = await checkSubdomainAvailability(subdomainValue.trim(), domainIdValue);
            setAvailabilityStatus({
                checked: true,
                available: response.attributes.available,
                message: response.attributes.message,
            });
        } catch (error) {
            setAvailabilityStatus({
                checked: true,
                available: false,
                message: 'Failed to check availability. Please try again.',
            });
        } finally {
            setCheckingAvailability(false);
        }
    }, []);

    const debouncedCheckSubdomain = useCallback(
        (subdomainValue: string, domainIdValue: number) => {
            if (debounceTimeoutRef.current) {
                clearTimeout(debounceTimeoutRef.current);
            }

            debounceTimeoutRef.current = setTimeout(() => {
                checkSubdomain(subdomainValue, domainIdValue);
            }, 500);
        },
        [checkSubdomain],
    );

    useEffect(() => {
        return () => {
            if (debounceTimeoutRef.current) {
                clearTimeout(debounceTimeoutRef.current);
            }
        };
    }, []);

    // Step navigation with animations
    const goToStep = (step: CheckoutStep, direction: 'forward' | 'backward' = 'forward') => {
        setStepDirection(direction);
        setIsTransitioning(true);
        setTimeout(() => {
            setCurrentStep(step);
            setIsTransitioning(false);
        }, 300);
    };

    const handleNextStep = () => {
        if (currentStep === 'configuration') {
            if (!serverName.trim()) {
                toast.error('Please enter a server name.');
                return;
            }
            // Check if subdomain step should be shown (only for game servers that support subdomains)
            // For now, always show subdomain step if domains are available
            if (availableDomains && availableDomains.length > 0) {
                goToStep('subdomain', 'forward');
            } else {
                goToStep('payment', 'forward');
            }
        } else if (currentStep === 'subdomain') {
            // Validate subdomain if provided
            if (subdomain.trim()) {
                if (!selectedDomainId) {
                    toast.error('Please select a domain.');
                    return;
                }
                if (availabilityStatus && !availabilityStatus.available) {
                    toast.error('Please choose an available subdomain.');
                    return;
                }
                if (checkingAvailability) {
                    toast.error('Please wait for availability check to complete.');
                    return;
                }
            }
            goToStep('payment', 'forward');
        }
    };

    const handlePreviousStep = () => {
        if (currentStep === 'payment') {
            if (availableDomains && availableDomains.length > 0) {
                goToStep('subdomain', 'backward');
            } else {
                goToStep('configuration', 'backward');
            }
        } else if (currentStep === 'subdomain') {
            goToStep('configuration', 'backward');
        }
    };

    const handleCheckout = async () => {
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

            // Add subdomain if provided
            if (subdomain.trim() && selectedDomainId) {
                checkoutData.subdomain = subdomain.trim();
                checkoutData.domain_id = selectedDomainId;
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
        return null;
    }
    if (hostingType === 'vps' && !distributionId) {
        return null;
    }
    if (!planId && !isCustom) {
        return null;
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

    // Step indicator component
    const StepIndicator = () => {
        const steps = [
            { id: 'configuration', label: 'Configuration', number: 1 },
            ...(availableDomains && availableDomains.length > 0
                ? [{ id: 'subdomain', label: 'Subdomain', number: 2 }]
                : []),
            { id: 'payment', label: 'Payment', number: availableDomains && availableDomains.length > 0 ? 3 : 2 },
        ];

        const getStepNumber = (stepId: CheckoutStep): number => {
            if (stepId === 'configuration') return 1;
            if (stepId === 'subdomain') return 2;
            return availableDomains && availableDomains.length > 0 ? 3 : 2;
        };

        const currentStepNumber = getStepNumber(currentStep);

        return (
            <div className='flex items-center justify-center mb-8'>
                <div className='flex items-center gap-4'>
                    {steps.map((step, index) => {
                        const stepNum = step.number;
                        const isActive = stepNum === currentStepNumber;
                        const isCompleted = stepNum < currentStepNumber;

                        return (
                            <div key={step.id} className='flex items-center'>
                                <div className='flex flex-col items-center'>
                                    <div
                                        className={`w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-all duration-300 ${
                                            isActive
                                                ? 'bg-brand text-white scale-110'
                                                : isCompleted
                                                  ? 'bg-green-500 text-white'
                                                  : 'bg-[#ffffff12] text-white/50'
                                        }`}
                                    >
                                        {isCompleted ? (
                                            <svg className='w-6 h-6' fill='currentColor' viewBox='0 0 20 20'>
                                                <path
                                                    fillRule='evenodd'
                                                    d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z'
                                                    clipRule='evenodd'
                                                />
                                            </svg>
                                        ) : (
                                            stepNum
                                        )}
                                    </div>
                                    <span
                                        className={`mt-2 text-xs font-medium transition-colors ${
                                            isActive ? 'text-white' : 'text-white/50'
                                        }`}
                                    >
                                        {step.label}
                                    </span>
                                </div>
                                {index < steps.length - 1 && (
                                    <div
                                        className={`h-0.5 w-16 mx-2 transition-colors ${
                                            isCompleted ? 'bg-green-500' : 'bg-[#ffffff12]'
                                        }`}
                                    />
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        );
    };

    // Animation styles
    const getStepAnimationClass = () => {
        if (isTransitioning) {
            return stepDirection === 'forward'
                ? 'opacity-0 translate-x-8 pointer-events-none'
                : 'opacity-0 -translate-x-8 pointer-events-none';
        }
        return 'opacity-100 translate-x-0';
    };

    return (
        <>
            <style>{`
                @keyframes fadeIn {
                    from {
                        opacity: 0;
                    }
                    to {
                        opacity: 1;
                    }
                }
                
                @keyframes slideUp {
                    from {
                        opacity: 0;
                        transform: translateY(20px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
            `}</style>

            <div className='h-full min-h-screen bg-[#0a0a0a] overflow-y-auto -mx-2 -my-2 w-[calc(100%+1rem)]'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8'>
                    <MainPageHeader title='Complete Your Purchase' />

                    <StepIndicator />

                    <div className='grid grid-cols-1 lg:grid-cols-3 gap-6'>
                        {/* Left Column: Steps Content */}
                        <div className='lg:col-span-2'>
                            <div
                                className={`transition-all duration-300 ease-in-out ${getStepAnimationClass()}`}
                                style={{
                                    transitionTimingFunction: 'cubic-bezier(0.42, 0, 0.58, 1)',
                                }}
                            >
                                {/* Step 1: Configuration */}
                                {currentStep === 'configuration' && (
                                    <div
                                        className='space-y-6'
                                        style={{
                                            animation: 'fadeIn 0.5s ease-out, slideUp 0.6s ease-out',
                                        }}
                                    >
                                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6'>
                                            <h3 className='text-lg font-semibold text-white mb-6 flex items-center gap-2'>
                                                <span className='w-8 h-8 rounded-full bg-brand/20 flex items-center justify-center text-brand font-bold'>
                                                    1
                                                </span>
                                                Server Configuration
                                            </h3>

                                            <div className='space-y-4'>
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
                                                        className='w-full px-4 py-3 bg-[#ffffff08] border border-[#ffffff12] rounded-lg text-white placeholder-white/30 focus:outline-none focus:border-brand transition-all'
                                                        maxLength={191}
                                                    />
                                                    <p className='mt-1 text-xs text-white/50'>
                                                        Only letters, numbers, spaces, dashes, underscores, and dots are
                                                        allowed.
                                                    </p>
                                                </div>

                                                <div>
                                                    <label
                                                        htmlFor='server-description'
                                                        className='block text-sm font-medium text-white/70 mb-2'
                                                    >
                                                        Server Description{' '}
                                                        <span className='text-white/30'>(Optional)</span>
                                                    </label>
                                                    <textarea
                                                        id='server-description'
                                                        value={serverDescription}
                                                        onChange={(e) => setServerDescription(e.target.value)}
                                                        placeholder='A brief description of your server...'
                                                        rows={3}
                                                        className='w-full px-4 py-3 bg-[#ffffff08] border border-[#ffffff12] rounded-lg text-white placeholder-white/30 focus:outline-none focus:border-brand transition-all resize-none'
                                                        maxLength={500}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Step 2: Subdomain Selection */}
                                {currentStep === 'subdomain' && availableDomains && availableDomains.length > 0 && (
                                    <div
                                        className='space-y-6'
                                        style={{
                                            animation: 'fadeIn 0.5s ease-out, slideUp 0.6s ease-out',
                                        }}
                                    >
                                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6'>
                                            <h3 className='text-lg font-semibold text-white mb-2 flex items-center gap-2'>
                                                <span className='w-8 h-8 rounded-full bg-brand/20 flex items-center justify-center text-brand font-bold'>
                                                    2
                                                </span>
                                                Subdomain (Optional)
                                            </h3>
                                            <p className='text-sm text-white/60 mb-6 ml-10'>
                                                Choose a custom subdomain for your server. This will make it easier for
                                                players to connect.
                                            </p>

                                            <div className='space-y-4'>
                                                <div>
                                                    <label
                                                        htmlFor='subdomain'
                                                        className='block text-sm font-medium text-white/70 mb-2'
                                                    >
                                                        Subdomain
                                                    </label>
                                                    <div className='flex items-center border border-[#ffffff12] rounded-lg overflow-hidden hover:border-[#ffffff25] focus-within:border-brand transition-colors'>
                                                        <input
                                                            id='subdomain'
                                                            type='text'
                                                            value={subdomain}
                                                            onChange={(e) => {
                                                                const value = e.target.value
                                                                    .toLowerCase()
                                                                    .replace(/[^a-z0-9-]/g, '');
                                                                setSubdomain(value);
                                                                if (selectedDomainId && value.trim()) {
                                                                    debouncedCheckSubdomain(value, selectedDomainId);
                                                                } else {
                                                                    setAvailabilityStatus(null);
                                                                    if (debounceTimeoutRef.current) {
                                                                        clearTimeout(debounceTimeoutRef.current);
                                                                    }
                                                                }
                                                            }}
                                                            placeholder='myserver'
                                                            className='flex-1 px-4 py-3 bg-transparent text-white placeholder-white/30 focus:outline-none'
                                                            maxLength={63}
                                                        />
                                                        <div className='border-l border-[#ffffff12]'>
                                                            <select
                                                                value={selectedDomainId || ''}
                                                                onChange={(e) => {
                                                                    const domainId = parseInt(e.target.value);
                                                                    setSelectedDomainId(domainId);
                                                                    if (subdomain.trim()) {
                                                                        debouncedCheckSubdomain(subdomain, domainId);
                                                                    }
                                                                }}
                                                                className='min-w-[140px] px-4 py-3 bg-[#ffffff08] border-0 text-white focus:outline-none cursor-pointer'
                                                            >
                                                                {availableDomains.map((domain) => (
                                                                    <option key={domain.id} value={domain.id}>
                                                                        .{domain.name}
                                                                    </option>
                                                                ))}
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <p className='mt-1 text-xs text-white/50'>
                                                        Only lowercase letters, numbers, and hyphens. Must start and end
                                                        with a letter or number.
                                                    </p>
                                                </div>

                                                {(checkingAvailability || availabilityStatus) && (
                                                    <div
                                                        className={`rounded-lg p-4 border transition-all ${
                                                            checkingAvailability
                                                                ? 'bg-blue-500/10 border-blue-500/20'
                                                                : availabilityStatus?.available
                                                                  ? 'bg-green-500/10 border-green-500/20'
                                                                  : 'bg-red-500/10 border-red-500/20'
                                                        }`}
                                                    >
                                                        {checkingAvailability ? (
                                                            <div className='flex items-center text-sm text-blue-300'>
                                                                <div className='animate-spin rounded-full h-4 w-4 border-b-2 border-blue-400 mr-3'></div>
                                                                Checking availability...
                                                            </div>
                                                        ) : (
                                                            availabilityStatus && (
                                                                <div
                                                                    className={`text-sm flex items-center font-medium ${
                                                                        availabilityStatus.available
                                                                            ? 'text-green-300'
                                                                            : 'text-red-300'
                                                                    }`}
                                                                >
                                                                    <div
                                                                        className={`w-3 h-3 rounded-full mr-3 ${
                                                                            availabilityStatus.available
                                                                                ? 'bg-green-400'
                                                                                : 'bg-red-400'
                                                                        }`}
                                                                    ></div>
                                                                    {availabilityStatus.message}
                                                                </div>
                                                            )
                                                        )}
                                                    </div>
                                                )}

                                                <div className='bg-[#ffffff05] border border-[#ffffff08] rounded-lg p-4'>
                                                    <p className='text-sm text-white/60'>
                                                        <span className='font-medium text-white/80'>Note:</span> You can
                                                        skip this step if you don't want a custom subdomain. A subdomain
                                                        can be added later from your server's network settings.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Step 3: Payment */}
                                {currentStep === 'payment' && (
                                    <div
                                        className='space-y-6'
                                        style={{
                                            animation: 'fadeIn 0.5s ease-out, slideUp 0.6s ease-out',
                                        }}
                                    >
                                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6'>
                                            <h3 className='text-lg font-semibold text-white mb-6 flex items-center gap-2'>
                                                <span className='w-8 h-8 rounded-full bg-brand/20 flex items-center justify-center text-brand font-bold'>
                                                    {availableDomains && availableDomains.length > 0 ? '3' : '2'}
                                                </span>
                                                Review & Payment
                                            </h3>

                                            <div className='space-y-4'>
                                                <div className='bg-[#ffffff05] border border-[#ffffff08] rounded-lg p-4'>
                                                    <h4 className='text-sm font-semibold text-white mb-3'>
                                                        Order Summary
                                                    </h4>
                                                    <div className='space-y-2 text-sm text-white/70'>
                                                        <div className='flex justify-between'>
                                                            <span>Plan:</span>
                                                            <span className='text-white'>
                                                                {selectedPlan
                                                                    ? selectedPlan.attributes.name
                                                                    : 'Custom Plan'}
                                                            </span>
                                                        </div>
                                                        {selectedPlan?.attributes.memory && (
                                                            <div className='flex justify-between'>
                                                                <span>RAM:</span>
                                                                <span>
                                                                    {formatMemory(selectedPlan.attributes.memory)}
                                                                </span>
                                                            </div>
                                                        )}
                                                        {hostingType === 'game-server' && (
                                                            <>
                                                                <div className='flex justify-between'>
                                                                    <span>Game:</span>
                                                                    <span>{selectedEgg?.attributes.name}</span>
                                                                </div>
                                                            </>
                                                        )}
                                                        {subdomain.trim() && selectedDomainId && (
                                                            <div className='flex justify-between'>
                                                                <span>Subdomain:</span>
                                                                <span className='font-mono'>
                                                                    {subdomain}.
                                                                    {
                                                                        availableDomains?.find(
                                                                            (d) => d.id === selectedDomainId,
                                                                        )?.name
                                                                    }
                                                                </span>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>

                                                <div className='bg-gradient-to-r from-brand/10 to-brand/5 border border-brand/20 rounded-lg p-4'>
                                                    <div className='flex items-center justify-between mb-2'>
                                                        <span className='text-sm text-white/70'>Total</span>
                                                        <span className='text-2xl font-bold text-white'>
                                                            {firstMonthDiscount
                                                                ? formatPrice(firstMonthPrice)
                                                                : formatPrice(monthlyPrice)}
                                                        </span>
                                                    </div>
                                                    {firstMonthDiscount && (
                                                        <div className='flex items-center justify-between text-xs text-white/60'>
                                                            <span>
                                                                First month ({firstMonthDiscount.toFixed(0)}% off)
                                                            </span>
                                                            <span className='line-through'>
                                                                {formatPrice(monthlyPrice)}
                                                            </span>
                                                        </div>
                                                    )}
                                                    <div className='text-xs text-white/50 mt-1'>
                                                        Then {formatPrice(monthlyPrice)} per {getInterval()}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Navigation Buttons */}
                            <div className='flex gap-4 mt-6'>
                                {currentStep !== 'configuration' && (
                                    <ActionButton
                                        variant='secondary'
                                        size='lg'
                                        onClick={handlePreviousStep}
                                        disabled={isCreatingCheckout || isTransitioning}
                                    >
                                        Previous
                                    </ActionButton>
                                )}
                                {currentStep === 'configuration' && (
                                    <ActionButton
                                        variant='secondary'
                                        size='lg'
                                        onClick={handleBack}
                                        disabled={isCreatingCheckout || isTransitioning}
                                    >
                                        Back to Plans
                                    </ActionButton>
                                )}
                                {currentStep !== 'payment' ? (
                                    <ActionButton
                                        variant='primary'
                                        size='lg'
                                        onClick={handleNextStep}
                                        disabled={
                                            (currentStep === 'configuration' && !serverName.trim()) ||
                                            (currentStep === 'subdomain' &&
                                                subdomain.trim() &&
                                                (!selectedDomainId ||
                                                    (availabilityStatus && !availabilityStatus.available) ||
                                                    checkingAvailability)) ||
                                            isTransitioning
                                        }
                                        className='flex-1'
                                    >
                                        Continue
                                    </ActionButton>
                                ) : (
                                    <ActionButton
                                        variant='primary'
                                        size='lg'
                                        onClick={handleCheckout}
                                        disabled={!isReady || isCreatingCheckout || isTransitioning}
                                        className='flex-1'
                                    >
                                        {isCreatingCheckout ? 'Processing...' : 'Proceed to Payment'}
                                    </ActionButton>
                                )}
                            </div>
                        </div>

                        {/* Right Column: Order Summary (Sticky) */}
                        <div className='lg:col-span-1'>
                            <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6 sticky top-6'>
                                <h3 className='text-lg font-semibold text-white mb-4'>Order Summary</h3>

                                <div className='space-y-4'>
                                    <div className='flex justify-between items-start'>
                                        <div>
                                            <div className='font-medium text-white'>
                                                {selectedPlan ? selectedPlan.attributes.name : 'Custom Plan'}
                                            </div>
                                            {selectedPlan?.attributes.description && (
                                                <div className='text-sm text-white/60 mt-1'>
                                                    {selectedPlan.attributes.description}
                                                </div>
                                            )}
                                        </div>
                                    </div>

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

                                    {hostingType === 'game-server' ? (
                                        <div className='pt-4 border-t border-[#ffffff12] space-y-2 text-sm'>
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

                                    {isReady ? (
                                        <div className='pt-4 border-t border-[#ffffff12] space-y-3'>
                                            {firstMonthDiscount ? (
                                                <>
                                                    <div className='flex justify-between text-white/70'>
                                                        <span>First Month ({firstMonthDiscount.toFixed(0)}% off)</span>
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
                                                </>
                                            ) : (
                                                <>
                                                    <div className='flex justify-between text-lg font-semibold text-white'>
                                                        <span>Price</span>
                                                        <span>{formatPrice(monthlyPrice)}</span>
                                                    </div>
                                                    <div className='text-sm text-white/70'>
                                                        Billed per {getInterval()}
                                                    </div>
                                                </>
                                            )}
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
        </>
    );
};

export default HostingCheckoutContainer;
