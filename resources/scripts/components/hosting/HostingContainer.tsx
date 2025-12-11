import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import useSWR from 'swr';

import Navbar from '@/components/Navbar';
import ActionButton from '@/components/elements/ActionButton';

import getHostingPlans, {
    CustomPlanCalculation,
    HostingPlan,
    calculateCustomPlan,
} from '@/api/hosting/getHostingPlans';
import http, { httpErrorToHuman } from '@/api/http';

import { useStoreState } from '@/state/hooks';

type HostingType = 'game-server' | 'vps';

const HostingContainer = () => {
    const navigate = useNavigate();
    const [hostingType, setHostingType] = useState<HostingType>('game-server');
    const {
        data: plans,
        error,
        isLoading,
    } = useSWR<HostingPlan[]>(['/api/client/hosting/plans', hostingType], () => getHostingPlans(hostingType));
    const isAuthenticated = useStoreState((state) => !!state.user.data?.uuid);

    const [customMemory, setCustomMemory] = useState<number>(2048);
    const customInterval = 'month';
    const [customPlanCalculation, setCustomPlanCalculation] = useState<CustomPlanCalculation | null>(null);
    const [isCalculating, setIsCalculating] = useState(false);

    // Check server creation status
    const { data: serverCreationStatus } = useSWR<{ enabled: boolean }>(
        '/api/client/hosting/server-creation-status',
        async (url: string) => {
            const response = await http.get(url);
            return response.data.data;
        },
    );

    useEffect(() => {
        document.title = 'Oasis Cloud - Complete I.T. Solutions';
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

        const timeoutId = setTimeout(calculatePrice, 500);
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

    const getVCores = (cpu: number | null): number => {
        if (!cpu) return 0;
        return Math.round(cpu / 100);
    };

    const handlePlanSelect = (plan: HostingPlan) => {
        if (serverCreationStatus && !serverCreationStatus.enabled) {
            navigate('/hosting/server-creation-disabled');
            return;
        }

        if (!isAuthenticated) {
            navigate(`/auth/login`, {
                state: {
                    from: `/hosting/checkout?plan=${plan.attributes.id}&type=${hostingType}`,
                },
                replace: false,
            });
            return;
        }
        navigate(`/hosting/checkout?plan=${plan.attributes.id}&type=${hostingType}`);
    };

    const handleCustomPlanSelect = () => {
        if (!customPlanCalculation) {
            return;
        }

        if (serverCreationStatus && !serverCreationStatus.enabled) {
            navigate('/hosting/server-creation-disabled');
            return;
        }

        if (!isAuthenticated) {
            navigate(`/auth/login`, {
                state: {
                    from: `/hosting/checkout?custom=true&memory=${customMemory}&interval=${customInterval}&type=${hostingType}`,
                },
                replace: false,
            });
            return;
        }
        navigate(`/hosting/checkout?custom=true&memory=${customMemory}&interval=${customInterval}&type=${hostingType}`);
    };

    const scrollToPricing = () => {
        const pricingSection = document.getElementById('services');
        if (pricingSection) {
            pricingSection.scrollIntoView({ behavior: 'smooth' });
        }
    };

    return (
        <div className='h-full min-h-screen bg-[#0a0a0a] overflow-y-auto -mx-2 -my-2 w-[calc(100%+1rem)]'>
            <Navbar />

            {/* Hero Section */}
            <section className='relative overflow-hidden'>
                <div className='absolute inset-0 bg-gradient-to-br from-[var(--color-brand)]/10 via-transparent to-transparent' />
                <div className='relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-32 pb-24'>
                    <div className='text-center'>
                        <div className='inline-block mb-6'>
                            <span className='text-xs font-semibold text-white/70 uppercase tracking-wider px-4 py-2 bg-white/5 rounded-full border border-white/10'>
                                IT SOLUTIONS
                            </span>
                            <span className='text-xs font-semibold text-white/70 uppercase tracking-wider px-4 py-2 bg-white/5 rounded-full border border-white/10 ml-2'>
                                CONSULTING
                            </span>
                        </div>
                        <h1 className='text-6xl md:text-7xl font-bold text-white mb-6 leading-tight'>
                            Complete I.T. <span className='text-[var(--color-brand)]'>Clean & Simple</span>
                        </h1>
                        <p className='text-xl text-white/60 mb-10 max-w-3xl mx-auto leading-relaxed'>
                            At Oasis Cloud our goal is to provide you with I.T. support that helps your business
                            succeed.
                        </p>
                        <div className='flex flex-col sm:flex-row gap-4 justify-center items-center'>
                            <ActionButton
                                variant='primary'
                                size='lg'
                                onClick={scrollToPricing}
                                className='bg-[var(--color-brand)] hover:bg-[var(--color-brand)]/90'
                            >
                                GET STARTED
                            </ActionButton>
                            <ActionButton
                                variant='secondary'
                                size='lg'
                                className='border-white/20 hover:border-white/40'
                            >
                                LEARN MORE
                            </ActionButton>
                        </div>
                    </div>
                </div>
            </section>

            {/* Core Features */}
            <section className='py-20'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='grid md:grid-cols-3 gap-8'>
                        {/* Tailor-made Strategies */}
                        <div className='bg-[#0a0a0a] border border-white/10 rounded-lg p-8 text-center hover:border-[var(--color-brand)]/50 transition-all'>
                            <div className='w-16 h-16 bg-[var(--color-brand)]/10 rounded-lg flex items-center justify-center mx-auto mb-6'>
                                <svg
                                    className='w-8 h-8 text-[var(--color-brand)]'
                                    fill='none'
                                    viewBox='0 0 24 24'
                                    stroke='currentColor'
                                >
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'
                                    />
                                </svg>
                            </div>
                            <h3 className='text-2xl font-bold text-white mb-4'>Tailor-made Strategies</h3>
                            <p className='text-white/60 leading-relaxed'>
                                We do not believe in one-size-fits-all. Our solutions are customized to your business
                                needs.
                            </p>
                        </div>

                        {/* Experienced Team */}
                        <div className='bg-[var(--color-brand)] rounded-lg p-8 text-center transform hover:scale-105 transition-all'>
                            <div className='w-16 h-16 bg-white/20 rounded-lg flex items-center justify-center mx-auto mb-6'>
                                <svg
                                    className='w-8 h-8 text-white'
                                    fill='none'
                                    viewBox='0 0 24 24'
                                    stroke='currentColor'
                                >
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'
                                    />
                                </svg>
                            </div>
                            <h3 className='text-2xl font-bold text-white mb-4'>Experienced Team</h3>
                            <p className='text-white/90 leading-relaxed'>
                                We have professionals with experience on our team. Each project benefits from their
                                expertise and enthusiasm.
                            </p>
                        </div>

                        {/* Quality Assurance */}
                        <div className='bg-[#0a0a0a] border border-white/10 rounded-lg p-8 text-center hover:border-[var(--color-brand)]/50 transition-all'>
                            <div className='w-16 h-16 bg-[var(--color-brand)]/10 rounded-lg flex items-center justify-center mx-auto mb-6'>
                                <svg
                                    className='w-8 h-8 text-[var(--color-brand)]'
                                    fill='none'
                                    viewBox='0 0 24 24'
                                    stroke='currentColor'
                                >
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'
                                    />
                                </svg>
                            </div>
                            <h3 className='text-2xl font-bold text-white mb-4'>Quality Assurance</h3>
                            <p className='text-white/60 leading-relaxed'>
                                We take quality seriously. It is essential to our workflow, ensuring high-quality
                                deliverables.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {/* About Us Section with 3D Element */}
            <section className='py-24 bg-[#0f0f0f]'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='grid lg:grid-cols-2 gap-16 items-center'>
                        {/* Left Content */}
                        <div>
                            <span className='text-sm font-semibold text-[var(--color-brand)] uppercase tracking-wider'>
                                ABOUT US
                            </span>
                            <h2 className='text-5xl font-bold text-white mt-4 mb-8 leading-tight'>
                                Our Core Offerings - Meeting Your Needs
                            </h2>
                            <p className='text-white/60 mb-8 leading-relaxed'>
                                Technology is transforming the business floor. Many are stuck ramping up strategies to
                                meet the new demands of Digital modernization. Oasis Technologies combines deep
                                technical expertise with strategic consulting prowess, enabling businesses to navigate
                                digital transformation and unlock unprecedented growth.
                            </p>
                            <p className='text-white/60 mb-12 leading-relaxed'>
                                Our commitment to excellence is reflected in our comprehensive range of services - from
                                custom software development and cloud migration to cybersecurity and IT infrastructure
                                management, we provide end-to-end solutions that are scalable, secure, and tailored to
                                your unique business requirements.
                            </p>

                            <div className='space-y-6'>
                                <div className='flex gap-4'>
                                    <div className='flex-shrink-0'>
                                        <div className='w-12 h-12 bg-[var(--color-brand)]/10 rounded-none flex items-center justify-center'>
                                            <svg
                                                className='w-6 h-6 text-[var(--color-brand)]'
                                                fill='currentColor'
                                                viewBox='0 0 20 20'
                                            >
                                                <path
                                                    fillRule='evenodd'
                                                    d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z'
                                                    clipRule='evenodd'
                                                />
                                            </svg>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 className='text-xl font-bold text-white mb-2'>Customer Support Focus</h4>
                                        <p className='text-white/60'>
                                            24/7 dedicated support team ensuring your business operations run smoothly
                                            without interruption. We're here when you need us most.
                                        </p>
                                    </div>
                                </div>

                                <div className='flex gap-4'>
                                    <div className='flex-shrink-0'>
                                        <div className='w-12 h-12 bg-[var(--color-brand)]/10 rounded-none flex items-center justify-center'>
                                            <svg
                                                className='w-6 h-6 text-[var(--color-brand)]'
                                                fill='currentColor'
                                                viewBox='0 0 20 20'
                                            >
                                                <path
                                                    fillRule='evenodd'
                                                    d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z'
                                                    clipRule='evenodd'
                                                />
                                            </svg>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 className='text-xl font-bold text-white mb-2'>Professional Support</h4>
                                        <p className='text-white/60'>
                                            Expert consultation and technical guidance from certified professionals with
                                            proven track records in enterprise IT solutions.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <button
                                className='mt-8 text-[var(--color-brand)] hover:text-[var(--color-brand)]/80 font-semibold flex items-center gap-2'
                                style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                            >
                                READ MORE
                                <svg className='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M9 5l7 7-7 7'
                                    />
                                </svg>
                            </button>
                        </div>

                        {/* Right - 3D Element */}
                        <div className='relative'>
                            <div className='relative w-full aspect-square'>
                                {/* 3D Opera-like element simulation */}
                                <div className='absolute inset-0 flex items-center justify-center'>
                                    <div className='relative w-full h-full max-w-[500px] max-h-[500px]'>
                                        {/* Outer ring */}
                                        <div className='absolute inset-0 rounded-full bg-gradient-to-br from-[var(--color-brand)] to-[var(--color-brand)] opacity-90 blur-3xl'></div>
                                        {/* Middle ring */}
                                        <div className='absolute inset-[10%] rounded-full bg-gradient-to-br from-[var(--color-brand)] to-[var(--color-brand)] opacity-80 blur-2xl'></div>
                                        {/* Inner hole */}
                                        <div className='absolute inset-[30%] rounded-full bg-[#0a0a0a]'></div>
                                        {/* Highlight */}
                                        <div className='absolute inset-[5%] rounded-full bg-gradient-to-tr from-transparent via-white/10 to-transparent'></div>
                                    </div>
                                </div>
                                {/* Stats overlay */}
                                <div className='absolute bottom-8 right-8 bg-[#0a0a0a] border border-white/10 rounded-none p-6 backdrop-blur-sm'>
                                    <div className='text-6xl font-bold text-white mb-2'>62+</div>
                                    <div className='text-white/60'>Global Clients</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Elevate Your Digital Image */}
            <section className='py-20 bg-[#0a0a0a]'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='text-center mb-16'>
                        <h2 className='text-5xl font-bold text-white mb-6'>
                            <span className='text-white/20'>ELEVATE YOUR DIGITAL IMAGE.</span>
                        </h2>
                        <div className='h-px bg-gradient-to-r from-transparent via-white/20 to-transparent mb-12'></div>
                        <p className='text-xl text-white/60 max-w-4xl mx-auto leading-relaxed mb-4'>
                            Oasis Technologies specializes in comprehensive IT solutions
                        </p>
                        <h3 className='text-4xl font-bold text-white mb-6'>
                            Crafting digital solutions tailored to your unique business needs.
                        </h3>
                        <p className='text-white/60 max-w-3xl mx-auto leading-relaxed'>
                            From cloud infrastructure to cybersecurity, we deliver cutting-edge technology solutions
                            that empower your business to thrive in the digital age. Our expert team ensures seamless
                            integration and maximum efficiency.
                        </p>
                    </div>
                </div>
            </section>

            {/* Services Grid */}
            <section id='services' className='py-20 bg-[#0f0f0f]'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='grid md:grid-cols-2 lg:grid-cols-3 gap-6'>
                        {/* Managed IT */}
                        <div className='bg-[#0a0a0a] border border-[var(--color-brand)] rounded-none p-8 hover:border-[var(--color-brand)]/50 transition-all group'>
                            <div className='mb-6'>
                                <svg
                                    className='w-12 h-12 text-[var(--color-brand)]'
                                    fill='none'
                                    viewBox='0 0 24 24'
                                    stroke='currentColor'
                                >
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z'
                                    />
                                </svg>
                            </div>
                            <h3 className='text-2xl font-bold text-white mb-3'>Managed IT</h3>
                            <p className='text-white/60 mb-6 leading-relaxed'>
                                Comprehensive IT management services ensuring your infrastructure runs at peak
                                performance. From monitoring to maintenance, we handle it all.
                            </p>
                            <button
                                className='text-[var(--color-brand)] hover:text-[var(--color-brand)]/80 font-semibold flex items-center gap-2 group-hover:gap-3 transition-all'
                                style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                            >
                                SERVICE DETAILS
                                <svg className='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M9 5l7 7-7 7'
                                    />
                                </svg>
                            </button>
                        </div>

                        {/* Co-Managed IT */}
                        <div className='bg-[#0a0a0a] border border-white/10 rounded-none p-8 hover:border-[var(--color-brand)]/50 transition-all group'>
                            <div className='mb-6'>
                                <svg
                                    className='w-12 h-12 text-[var(--color-brand)]'
                                    fill='none'
                                    viewBox='0 0 24 24'
                                    stroke='currentColor'
                                >
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'
                                    />
                                </svg>
                            </div>
                            <h3 className='text-2xl font-bold text-white mb-3'>Co-Managed IT</h3>
                            <p className='text-white/60 mb-6 leading-relaxed'>
                                Collaborative IT support that works alongside your existing team. We fill the gaps and
                                provide expertise where you need it most.
                            </p>
                            <button
                                className='text-[var(--color-brand)] hover:text-[var(--color-brand)]/80 font-semibold flex items-center gap-2 group-hover:gap-3 transition-all'
                                style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                            >
                                SERVICE DETAILS
                                <svg className='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M9 5l7 7-7 7'
                                    />
                                </svg>
                            </button>
                        </div>

                        {/* Compliance & Security */}
                        <div className='bg-[#0a0a0a] border border-white/10 rounded-none p-8 hover:border-[var(--color-brand)]/50 transition-all group'>
                            <div className='mb-6'>
                                <svg
                                    className='w-12 h-12 text-[var(--color-brand)]'
                                    fill='none'
                                    viewBox='0 0 24 24'
                                    stroke='currentColor'
                                >
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'
                                    />
                                </svg>
                            </div>
                            <h3 className='text-2xl font-bold text-white mb-3'>Compliance & Security</h3>
                            <p className='text-white/60 mb-6 leading-relaxed'>
                                Stay compliant and secure with our comprehensive security solutions. We protect your
                                data and ensure regulatory compliance.
                            </p>
                            <button
                                className='text-[var(--color-brand)] hover:text-[var(--color-brand)]/80 font-semibold flex items-center gap-2 group-hover:gap-3 transition-all'
                                style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                            >
                                SERVICE DETAILS
                                <svg className='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M9 5l7 7-7 7'
                                    />
                                </svg>
                            </button>
                        </div>

                        {/* Web Services */}
                        <div className='bg-[#0a0a0a] border border-white/10 rounded-none p-8 hover:border-[var(--color-brand)]/50 transition-all group'>
                            <div className='mb-6'>
                                <svg
                                    className='w-12 h-12 text-[var(--color-brand)]'
                                    fill='none'
                                    viewBox='0 0 24 24'
                                    stroke='currentColor'
                                >
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9'
                                    />
                                </svg>
                            </div>
                            <h3 className='text-2xl font-bold text-white mb-3'>Web Services</h3>
                            <p className='text-white/60 mb-6 leading-relaxed'>
                                Custom web development and hosting solutions that scale with your business. Fast,
                                secure, and reliable web services.
                            </p>
                            <button
                                className='text-[var(--color-brand)] hover:text-[var(--color-brand)]/80 font-semibold flex items-center gap-2 group-hover:gap-3 transition-all'
                                style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                            >
                                SERVICE DETAILS
                                <svg className='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M9 5l7 7-7 7'
                                    />
                                </svg>
                            </button>
                        </div>

                        {/* Proactive */}
                        <div className='bg-[#0a0a0a] border border-white/10 rounded-none p-8 hover:border-[var(--color-brand)]/50 transition-all group'>
                            <div className='mb-6'>
                                <svg
                                    className='w-12 h-12 text-[var(--color-brand)]'
                                    fill='none'
                                    viewBox='0 0 24 24'
                                    stroke='currentColor'
                                >
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M13 10V3L4 14h7v7l9-11h-7z'
                                    />
                                </svg>
                            </div>
                            <h3 className='text-2xl font-bold text-white mb-3'>Proactive</h3>
                            <p className='text-white/60 mb-6 leading-relaxed'>
                                Anticipate and prevent IT issues before they impact your business. Our proactive
                                approach keeps you ahead of problems.
                            </p>
                            <button
                                className='text-[var(--color-brand)] hover:text-[var(--color-brand)]/80 font-semibold flex items-center gap-2 group-hover:gap-3 transition-all'
                                style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                            >
                                SERVICE DETAILS
                                <svg className='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M9 5l7 7-7 7'
                                    />
                                </svg>
                            </button>
                        </div>

                        {/* VoIP */}
                        <div className='bg-[#0a0a0a] border border-[var(--color-brand)] rounded-none p-8 hover:border-[var(--color-brand)]/50 transition-all group'>
                            <div className='mb-6'>
                                <svg
                                    className='w-12 h-12 text-[var(--color-brand)]'
                                    fill='none'
                                    viewBox='0 0 24 24'
                                    stroke='currentColor'
                                >
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z'
                                    />
                                </svg>
                            </div>
                            <h3 className='text-2xl font-bold text-white mb-3'>VoIP</h3>
                            <p className='text-white/60 mb-6 leading-relaxed'>
                                Modern communication solutions with crystal-clear voice quality. Reduce costs while
                                improving connectivity and collaboration.
                            </p>
                            <button
                                className='text-[var(--color-brand)] hover:text-[var(--color-brand)]/80 font-semibold flex items-center gap-2 group-hover:gap-3 transition-all'
                                style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                            >
                                LEARN MAINTENANCE
                                <svg className='w-5 h-5' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M9 5l7 7-7 7'
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            {/* Trusted Partners */}
            <section className='py-20 bg-[#0a0a0a]'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='text-center mb-12'>
                        <span className='text-sm font-semibold text-white/50 uppercase tracking-wider'>
                            OUR PARTNERS
                        </span>
                    </div>
                    <div className='grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-8 items-center opacity-50'>
                        {['Microsoft', 'Lenovo', 'Dell', 'WatchGuard', 'RapidX', 'XCPEP', 'NetApp'].map(
                            (partner, index) => (
                                <div key={index} className='flex items-center justify-center'>
                                    <span className='text-white/70 font-semibold text-xl'>{partner}</span>
                                </div>
                            ),
                        )}
                    </div>
                </div>
            </section>

            {/* CTA Section - Partner with us */}
            <section className='py-20 bg-[#0f0f0f]'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='grid lg:grid-cols-2 gap-12'>
                        {/* Left - Red Box */}
                        <div className='bg-[var(--color-brand)] rounded-none p-12'>
                            <span className='text-sm font-semibold text-white/90 uppercase tracking-wider'>
                                LET'S CONNECT
                            </span>
                            <h3 className='text-4xl font-bold text-white mt-4 mb-8'>
                                Your partner in digital success.
                            </h3>
                            <p className='text-white/90 mb-8 leading-relaxed'>
                                Whether you're looking to modernize your IT infrastructure, enhance security, or
                                streamline operations, Oasis Technologies is your trusted partner. Our team of experts
                                is ready to transform your business with innovative solutions tailored to your needs.
                            </p>
                            <div className='space-y-4'>
                                <div className='flex items-start gap-3'>
                                    <svg
                                        className='w-6 h-6 text-white flex-shrink-0 mt-1'
                                        fill='currentColor'
                                        viewBox='0 0 20 20'
                                    >
                                        <path
                                            fillRule='evenodd'
                                            d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z'
                                            clipRule='evenodd'
                                        />
                                    </svg>
                                    <span className='text-white/90'>Highly qualified team of tech experts</span>
                                </div>
                                <div className='flex items-start gap-3'>
                                    <svg
                                        className='w-6 h-6 text-white flex-shrink-0 mt-1'
                                        fill='currentColor'
                                        viewBox='0 0 20 20'
                                    >
                                        <path
                                            fillRule='evenodd'
                                            d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z'
                                            clipRule='evenodd'
                                        />
                                    </svg>
                                    <span className='text-white/90'>Strengthen your team with our consultants</span>
                                </div>
                                <div className='flex items-start gap-3'>
                                    <svg
                                        className='w-6 h-6 text-white flex-shrink-0 mt-1'
                                        fill='currentColor'
                                        viewBox='0 0 20 20'
                                    >
                                        <path
                                            fillRule='evenodd'
                                            d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z'
                                            clipRule='evenodd'
                                        />
                                    </svg>
                                    <span className='text-white/90'>Guaranteed results for exceptional clients</span>
                                </div>
                            </div>
                        </div>

                        {/* Right - Stats & Image */}
                        <div className='space-y-6'>
                            <div className='bg-[#0a0a0a] border border-white/10 rounded-none overflow-hidden'>
                                <div className='aspect-video bg-gradient-to-br from-blue-600/20 to-purple-600/20 flex items-center justify-center'>
                                    <span className='text-white/50 text-lg'>[Team Image]</span>
                                </div>
                            </div>
                            <div className='grid grid-cols-2 gap-6'>
                                <div className='bg-[#0a0a0a] border border-white/10 rounded-none p-6 text-center'>
                                    <div className='text-5xl font-bold text-[var(--color-brand)] mb-2'>17+</div>
                                    <div className='text-white/60'>Years Experience</div>
                                </div>
                                <div className='bg-[#0a0a0a] border border-white/10 rounded-none p-6 text-center'>
                                    <div className='text-5xl font-bold text-[var(--color-brand)] mb-2'>71+</div>
                                    <div className='text-white/60'>Expert Team</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Business Solutions Checklist */}
            <section className='py-20 bg-[#0a0a0a]'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='grid lg:grid-cols-2 gap-16'>
                        <div>
                            <span className='text-sm font-semibold text-[var(--color-brand)] uppercase tracking-wider'>
                                WHY CHOOSE US
                            </span>
                            <h2 className='text-5xl font-bold text-white mt-4 mb-8 leading-tight'>
                                Crafting experiences, delivering success.
                            </h2>
                            <p className='text-white/60 leading-relaxed'>
                                At Oasis Technologies, we understand that every business is unique. That's why we offer
                                customized IT solutions designed to meet your specific needs and goals. From initial
                                consultation to ongoing support, we're with you every step of the way.
                            </p>
                        </div>
                        <div className='space-y-4'>
                            {[
                                'BYOD (Managed Endpoint)',
                                'Professional Cybersecurity',
                                'Ransomware Protection',
                                'Compliance (SOX MAS)',
                                'On-Cloud Project Delivery',
                                'Public, Private, Hybrid cloud',
                            ].map((item, index) => (
                                <div
                                    key={index}
                                    className='flex items-center justify-between py-4 border-b border-white/10'
                                >
                                    <span className='text-white/70'>{item}</span>
                                    <svg
                                        className='w-5 h-5 text-[var(--color-brand)]'
                                        fill='none'
                                        viewBox='0 0 24 24'
                                        stroke='currentColor'
                                    >
                                        <path
                                            strokeLinecap='round'
                                            strokeLinejoin='round'
                                            strokeWidth={2}
                                            d='M9 5l7 7-7 7'
                                        />
                                    </svg>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* Method to the Creativity */}
            <section className='py-20 bg-[#0f0f0f]'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='text-center mb-16'>
                        <span className='text-sm font-semibold text-[var(--color-brand)] uppercase tracking-wider'>
                            OUR PROCESS
                        </span>
                        <h2 className='text-5xl font-bold text-white mt-4 mb-6'>Method to the creativity</h2>
                        <p className='text-white/60 max-w-3xl mx-auto leading-relaxed'>
                            Innovation combined with proven methodologies. Our streamlined process ensures every project
                            is delivered on time, within budget, and exceeds expectations.
                        </p>
                    </div>

                    <div className='grid md:grid-cols-2 lg:grid-cols-4 gap-6'>
                        {[
                            {
                                number: '01',
                                title: 'Discovery',
                                description: 'We start by understanding your business, challenges, and goals.',
                            },
                            {
                                number: '02',
                                title: 'Strategy',
                                description: 'Develop a comprehensive strategy aligned with your objectives.',
                            },
                            {
                                number: '03',
                                title: 'Execution',
                                description: 'Implement solutions with precision and attention to detail.',
                            },
                            {
                                number: '04',
                                title: 'Launch',
                                description: 'Deploy your solution and provide ongoing support and optimization.',
                            },
                        ].map((step, index) => (
                            <div
                                key={index}
                                className='bg-[#0a0a0a] border border-white/10 rounded-none p-8 hover:border-[var(--color-brand)]/50 transition-all'
                            >
                                <div className='text-6xl font-bold text-[var(--color-brand)]/20 mb-4'>
                                    {step.number}
                                </div>
                                <h3 className='text-2xl font-bold text-white mb-3'>{step.title}</h3>
                                <p className='text-white/60 leading-relaxed'>{step.description}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Footer */}
            <footer className='bg-[#0a0a0a] border-t border-white/10 py-16'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='grid md:grid-cols-4 gap-12 mb-12'>
                        {/* Company Info */}
                        <div className='md:col-span-2'>
                            <h3 className='text-white font-bold text-2xl mb-4'>Oasis Cloud</h3>
                            <p className='text-white/60 mb-6 leading-relaxed'>
                                Oasis Technologies was founded in 2019 with a vision to become the preeminent provider
                                of comprehensive IT solutions globally. We deliver excellence in every project.
                            </p>
                            <div className='flex gap-4'>
                                <a
                                    href='#'
                                    className='w-10 h-10 bg-white/5 rounded-none flex items-center justify-center hover:bg-[var(--color-brand)]/20 transition-colors'
                                >
                                    <span className='text-white/70'>f</span>
                                </a>
                                <a
                                    href='#'
                                    className='w-10 h-10 bg-white/5 rounded-none flex items-center justify-center hover:bg-[var(--color-brand)]/20 transition-colors'
                                >
                                    <span className='text-white/70'>t</span>
                                </a>
                                <a
                                    href='#'
                                    className='w-10 h-10 bg-white/5 rounded-none flex items-center justify-center hover:bg-[var(--color-brand)]/20 transition-colors'
                                >
                                    <span className='text-white/70'>in</span>
                                </a>
                            </div>
                        </div>

                        {/* Quick Links */}
                        <div>
                            <h4 className='text-white font-semibold mb-4'>QUICK LINKS</h4>
                            <ul className='space-y-3'>
                                <li>
                                    <a
                                        href='#'
                                        className='text-white/60 hover:text-[var(--color-brand)] transition-colors'
                                    >
                                        About Us
                                    </a>
                                </li>
                                <li>
                                    <a
                                        href='#'
                                        className='text-white/60 hover:text-[var(--color-brand)] transition-colors'
                                    >
                                        Our Services
                                    </a>
                                </li>
                                <li>
                                    <a
                                        href='#'
                                        className='text-white/60 hover:text-[var(--color-brand)] transition-colors'
                                    >
                                        Case Studies
                                    </a>
                                </li>
                                <li>
                                    <a
                                        href='#'
                                        className='text-white/60 hover:text-[var(--color-brand)] transition-colors'
                                    >
                                        Contact
                                    </a>
                                </li>
                            </ul>
                        </div>

                        {/* Newsletter */}
                        <div>
                            <h4 className='text-white font-semibold mb-4'>NEWSLETTER</h4>
                            <p className='text-white/60 mb-4 text-sm'>Subscribe to our newsletter for updates</p>
                            <div className='flex gap-2'>
                                <input
                                    type='email'
                                    placeholder='Email Address'
                                    className='flex-1 bg-white/5 border border-white/10 rounded-none px-4 py-2 text-white placeholder-white/30 focus:outline-none focus:border-[var(--color-brand)]/50'
                                />
                                <button
                                    className='bg-[var(--color-brand)] hover:bg-[var(--color-brand)]/90 text-white px-4 py-2 transition-colors'
                                    style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                                >
                                    →
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className='border-t border-white/10 pt-8 flex flex-col md:flex-row items-center justify-between gap-4'>
                        <div className='text-sm text-white/50'>
                            © 2025 Oasis Technologies. All rights reserved. Designed by Oliver ❤️
                        </div>
                        <div className='flex gap-6 text-sm text-white/50'>
                            <a href='#' className='hover:text-[var(--color-brand)] transition-colors'>
                                Privacy Policy
                            </a>
                            <a href='#' className='hover:text-[var(--color-brand)] transition-colors'>
                                Terms of Service
                            </a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    );
};

export default HostingContainer;
