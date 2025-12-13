import {
    ArrowRight,
    ArrowUpToLine,
    ChevronRight,
    Database,
    Gear,
    Link as LinkIcon,
    Magnifier,
    Play,
    Server,
    Shield,
} from '@gravity-ui/icons';
import { motion } from 'framer-motion';
import React, { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import useSWR from 'swr';

import Navbar from '@/components/Navbar';

import getHostingPlans, {
    CustomPlanCalculation,
    HostingPlan,
    calculateCustomPlan,
} from '@/api/hosting/getHostingPlans';
import http, { httpErrorToHuman } from '@/api/http';

import { useStoreState } from '@/state/hooks';

type HostingType = 'game-server' | 'vps';

// --- ANIMATION VARIANTS ---
const containerVar = {
    hidden: { opacity: 0 },
    show: {
        opacity: 1,
        transition: { staggerChildren: 0.1 },
    },
};

const itemVar = {
    hidden: { opacity: 0, y: 30 },
    show: { opacity: 1, y: 0, transition: { type: 'spring' as const, stiffness: 50 } },
};

// --- DATA: PRICING CONFIGURATION ---
const BILLING_CYCLES = [
    { label: 'Monthly', discount: 0, interval: 'month' },
    { label: 'Quarterly', discount: 0.05, interval: 'quarter' },
    { label: 'Bi-Annual', discount: 0.1, interval: 'half-year' },
    { label: 'Yearly', discount: 0.2, interval: 'year' },
];

const CATEGORIES = ['Game', 'VPS'];

const HostingContainer = () => {
    const navigate = useNavigate();
    const [hostingType, setHostingType] = useState<HostingType>('game-server');
    const [activeCategory, setActiveCategory] = useState('Game');
    const [billingIndex, setBillingIndex] = useState(0);
    const [customRam, _setCustomRam] = useState(16);

    const {
        data: plans,
        error,
        isLoading,
    } = useSWR<HostingPlan[]>(['/api/client/hosting/plans', hostingType], () => getHostingPlans(hostingType));
    const isAuthenticated = useStoreState((state) => !!state.user.data?.uuid);

    const [customMemory, setCustomMemory] = useState<number>(16384); // 16GB in MB
    const [customPlanCalculation, setCustomPlanCalculation] = useState<CustomPlanCalculation | null>(null);
    const [_isCalculating, setIsCalculating] = useState(false);

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

        // Apply border radius CSS variable only for hosting page
        // Read from data attribute set by wrapper, or use default
        const hostingButtonRadius = document.documentElement.getAttribute('data-hosting-button-radius') || '0.5rem';
        const root = document.documentElement;
        root.style.setProperty('--button-border-radius', hostingButtonRadius);

        return () => {
            // Reset to default when component unmounts (don't set anything, let it use default)
            root.style.removeProperty('--button-border-radius');
        };
    }, []);

    // Sync category with hosting type
    useEffect(() => {
        if (activeCategory === 'Game') {
            setHostingType('game-server');
        } else if (activeCategory === 'VPS') {
            setHostingType('vps');
        }
    }, [activeCategory]);

    // Sync custom RAM slider with custom memory
    useEffect(() => {
        setCustomMemory(customRam * 1024); // Convert GB to MB
    }, [customRam]);

    useEffect(() => {
        const calculatePrice = async () => {
            if (customMemory < 512 || customMemory > 32768) {
                return;
            }

            setIsCalculating(true);
            try {
                const cycle = BILLING_CYCLES[billingIndex];
                if (!cycle) return;
                const interval = cycle.interval;
                const calculation = await calculateCustomPlan(customMemory, interval);
                setCustomPlanCalculation(calculation);
            } catch (err) {
                console.error('Failed to calculate custom plan:', httpErrorToHuman(err));
            } finally {
                setIsCalculating(false);
            }
        };

        const timeoutId = setTimeout(calculatePrice, 500);
        return () => clearTimeout(timeoutId);
    }, [customMemory, billingIndex]);

    const formatMemory = (memory: number | null): string => {
        if (!memory) return 'N/A';
        if (memory < 1024) return `${memory} MB`;
        return `${(memory / 1024).toFixed(0)} GB`;
    };

    const getVCores = (cpu: number | null): number => {
        if (!cpu) return 0;
        return Math.round(cpu / 100);
    };

    // Helper to calculate price with discount
    const getPrice = (base: number, plan?: HostingPlan): number => {
        const cycle = BILLING_CYCLES[billingIndex];
        if (!cycle) return base;

        const discount = cycle.discount;
        let price = base;

        // Apply first month discount if available
        if (plan?.attributes.first_month_sales_percentage && plan.attributes.first_month_sales_percentage > 0) {
            const firstMonthDiscount = plan.attributes.first_month_sales_percentage / 100;
            price = price * (1 - firstMonthDiscount);
        }

        // Apply billing cycle discount
        price = price * (1 - discount);
        return parseFloat(price.toFixed(2));
    };

    // Get price for a plan based on billing cycle
    const getPlanPrice = (plan: HostingPlan): number => {
        const cycle = BILLING_CYCLES[billingIndex];
        if (!cycle) return plan.attributes.pricing.monthly;

        const interval = cycle.interval;
        const pricing = plan.attributes.pricing;

        switch (interval) {
            case 'month':
                return pricing.monthly;
            case 'quarter':
                return pricing.quarterly;
            case 'half-year':
                return pricing.half_year;
            case 'year':
                return pricing.yearly;
            default:
                return pricing.monthly;
        }
    };

    const handlePlanSelect = (plan: HostingPlan) => {
        if (serverCreationStatus && !serverCreationStatus.enabled) {
            navigate('/server-creation-disabled');
            return;
        }

        if (!isAuthenticated) {
            navigate(`/auth/login`, {
                state: {
                    from: `/checkout?plan=${plan.attributes.id}&type=${hostingType}`,
                },
                replace: false,
            });
            return;
        }
        navigate(`/checkout?plan=${plan.attributes.id}&type=${hostingType}`);
    };

    const _handleCustomPlanSelect = () => {
        if (!customPlanCalculation) {
            return;
        }

        if (serverCreationStatus && !serverCreationStatus.enabled) {
            navigate('/server-creation-disabled');
            return;
        }

        const cycle = BILLING_CYCLES[billingIndex];
        if (!cycle) return;

        if (!isAuthenticated) {
            navigate(`/auth/login`, {
                state: {
                    from: `/checkout?custom=true&memory=${customMemory}&interval=${cycle.interval}&type=${hostingType}`,
                },
                replace: false,
            });
            return;
        }
        navigate(`/checkout?custom=true&memory=${customMemory}&interval=${cycle.interval}&type=${hostingType}`);
    };

    const scrollToPricing = () => {
        const pricingSection = document.getElementById('pricing');
        if (pricingSection) {
            pricingSection.scrollIntoView({ behavior: 'smooth' });
        }
    };

    // --- COMPONENTS ---

    const ShimmerButton = ({ text, onClick }: { text: string; onClick?: () => void }) => (
        <motion.button
            whileHover={{ scale: 1 }}
            whileTap={{ scale: 0.95 }}
            onClick={onClick}
            className='relative overflow-hidden bg-transparent hover:border-brand hover:border-[1.5px] px-8 py-4 font-bold text-white group'
            style={{
                borderRadius: 'var(--button-border-radius, 0.5rem)',
                boxShadow: '0 0 20px color-mix(in srgb, var(--color-brand) 40%, transparent)',
            }}
        >
            {/* Brand color overlay that sweeps from right to left on hover */}
            <div
                className='absolute inset-y-0 right-0 bg-brand transition-all duration-300 ease-in-out w-full group-hover:w-0'
                style={{
                    borderRadius: 'var(--button-border-radius, 0.5rem)',
                }}
            />

            <span className='relative z-10 flex items-center gap-2'>
                {text} <ChevronRight width={16} height={16} />
            </span>
            <div className='absolute inset-0 -translate-x-full group-hover:animate-[shimmer_1.5s_infinite] bg-gradient-to-r from-transparent via-white/20 to-transparent z-0' />
        </motion.button>
    );

    const ServiceCard = ({
        icon: Icon,
        title,
        desc,
        colSpan = 'col-span-1',
        accent = false,
        slug,
    }: {
        icon: React.ComponentType<{ width?: number; height?: number; className?: string }>;
        title: string;
        desc: string;
        colSpan?: string;
        accent?: boolean;
        slug: string;
    }) => (
        <motion.div
            variants={itemVar}
            whileHover={{
                y: -8,
                boxShadow: '0 20px 40px -15px color-mix(in srgb, var(--color-brand) 20%, transparent)',
            }}
            className={`${colSpan} group relative overflow-hidden bg-neutral-900 border-l-2 ${accent ? 'border-brand' : 'border-neutral-700 hover:border-brand'} p-8 transition-colors duration-300`}
        >
            <div className='absolute inset-0 bg-gradient-to-br from-neutral-800 to-black opacity-0 group-hover:opacity-100 transition-opacity duration-500' />
            <div className='relative z-10 h-full flex flex-col'>
                <div
                    className={`mb-6 inline-flex p-3 ${accent ? 'bg-brand text-white' : 'bg-neutral-800 text-brand group-hover:bg-brand group-hover:text-white'} transition-colors`}
                    style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                >
                    <Icon width={28} height={28} />
                </div>
                <h3 className='text-xl font-bold text-white mb-3 uppercase tracking-wide'>{title}</h3>
                <p className='text-neutral-400 text-sm leading-relaxed mb-6 flex-grow'>{desc}</p>
                <Link
                    to={`/services/${slug}`}
                    className='flex items-center text-xs font-bold text-brand opacity-0 group-hover:opacity-100 transform translate-y-4 group-hover:translate-y-0 transition-all duration-300'
                >
                    SERVICE DETAILS <ArrowRight width={12} height={12} className='ml-1' />
                </Link>
            </div>
        </motion.div>
    );

    // Memoize testimonials to prevent re-rendering when billing/category changes
    const testimonialsContent = useMemo(
        () => [
            {
                n: 'Oliver',
                r: 'Game Developer',
                t: 'The latency is non-existent. Best Rust servers I&apos;ve ever hosted.',
            },
            {
                n: 'Sarah J.',
                r: 'SysAdmin',
                t: 'The S3 compatible storage saved us thousands compared to AWS.',
            },
            {
                n: 'Mark R.',
                r: 'Engineer',
                t: 'I love the open source ethos. The Proton panel is a joy to use.',
            },
            {
                n: 'David K.',
                r: 'CTO, TechCorp',
                t: 'Scalability was our main concern. Oasis handled our spike perfectly.',
            },
            {
                n: 'Jessica L.',
                r: 'Web Agency',
                t: 'We host 50+ client sites here. Uptime has been 100%.',
            },
        ],
        [],
    );

    const InfiniteMarquee = React.memo(
        ({
            children,
            direction = 'right',
            speed = 20,
        }: {
            children: React.ReactNode;
            direction?: 'left' | 'right';
            speed?: number;
        }) => {
            return (
                <div className='w-full inline-flex flex-nowrap overflow-hidden [mask-image:_linear-gradient(to_right,transparent_0,_black_128px,_black_calc(100%-128px),transparent_100%)]'>
                    <motion.div
                        className='flex items-center gap-4 md:gap-6 py-4'
                        animate={{ x: direction === 'right' ? ['0%', '-50%'] : ['-50%', '0%'] }}
                        initial={{ x: direction === 'right' ? '0%' : '-50%' }}
                        transition={{ ease: 'linear', duration: speed, repeat: Infinity, repeatType: 'loop' }}
                    >
                        {children} {children}
                    </motion.div>
                </div>
            );
        },
    );

    InfiniteMarquee.displayName = 'InfiniteMarquee';

    // Star icon component (since Gravity UI doesn't have a star icon)
    const StarIcon = ({ filled = false, size = 14 }: { filled?: boolean; size?: number }) => (
        <svg
            width={size}
            height={size}
            viewBox='0 0 24 24'
            fill={filled ? 'currentColor' : 'none'}
            stroke='currentColor'
            strokeWidth={2}
        >
            <polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2' />
        </svg>
    );

    return (
        <div className='min-h-screen bg-black text-white font-sans overflow-x-hidden'>
            {/* Background Ambience */}
            <div className='fixed inset-0 z-0 pointer-events-none'>
                <div className="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1451187580459-43490279c0fa?q=80&w=2072&auto=format&fit=crop')] bg-cover bg-center opacity-40 mix-blend-overlay" />
                <div className='absolute inset-0 bg-gradient-to-b from-black/50 via-black/70 to-black' />
            </div>

            <Navbar />

            <div className='relative z-10'>
                {/* HERO SECTION */}
                <section className='relative pt-40 pb-20 px-6 max-w-7xl mx-auto flex flex-col justify-center min-h-[85vh]'>
                    <motion.div
                        initial={{ opacity: 0, x: -50 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ duration: 0.8 }}
                        className='max-w-4xl'
                    >
                        <div className='flex items-center gap-4 mb-6'>
                            <span className='w-12 h-[2px] bg-brand' />
                            <span className='text-brand font-bold tracking-widest text-sm uppercase'>
                                Professional Hosting
                            </span>
                        </div>

                        <h1 className='text-5xl md:text-8xl font-black uppercase leading-[0.9] mb-8'>
                            Complete{' '}
                            <span className='text-transparent bg-clip-text bg-gradient-to-r from-neutral-500 to-white'>
                                Power.
                            </span>
                            <br />
                            <span className='text-brand'>Clean & Simple.</span>
                        </h1>

                        <p className='text-lg text-neutral-400 max-w-xl mb-12 leading-relaxed border-l-2 border-white/20 pl-6'>
                            At Oasis Cloud, our goal is to provide you with infrastructure that scales effortlessly.
                            Game servers, VPS, and enterprise storage tailored for success.
                        </p>

                        <div className='flex gap-4'>
                            <ShimmerButton text='GET STARTED' onClick={scrollToPricing} />
                            <button
                                className='px-8 py-4 border border-white/20 hover:bg-white hover:text-black font-bold uppercase text-sm tracking-widest transition-all flex items-center gap-2'
                                style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                            >
                                Our Services <ChevronRight width={16} height={16} />
                            </button>
                        </div>
                    </motion.div>
                </section>

                {/* USED BY (Infinite Scroll) */}
                <section className='py-10 border-y border-white/5 bg-neutral-950/65'>
                    <InfiniteMarquee speed={30}>
                        {[
                            {
                                name: 'Microsoft',
                                logo: 'https://img.icons8.com/color/480/microsoft.png',
                            },
                            {
                                name: 'NVIDIA',
                                logo: 'https://img.icons8.com/color/480/nvidia.png',
                            },
                            {
                                name: 'Riot Games',
                                logo: 'https://img.icons8.com/color/480/riot-games.png',
                            },
                            {
                                name: 'Spotify',
                                logo: 'https://img.icons8.com/color/480/spotify.png',
                            },
                            {
                                name: 'Discord',
                                logo: 'https://img.icons8.com/color/480/discord-logo.png',
                            },
                            {
                                name: 'NASA',
                                logo: 'https://img.icons8.com/color/480/nasa.png',
                            },
                            {
                                name: 'Valve',
                                logo: 'https://img.icons8.com/color/480/valve.png',
                            },
                            {
                                name: 'Epic Games',
                                logo: 'https://img.icons8.com/color/480/epic-games.png',
                            },
                            {
                                name: 'Unity',
                                logo: 'https://img.icons8.com/color/480/unity.png',
                            },
                            {
                                name: 'AMD',
                                logo: 'https://img.icons8.com/color/480/amd.png',
                            },
                            {
                                name: '4CAMPS',
                                logo: 'https://www.4camps.cz/assets/images/logo.svg',
                            },
                            {
                                name: 'ZR GAMES',
                                logo: 'https://www.zrgames.cz/images/logo.svg',
                            },
                            {
                                name: 'QPvP.pro',
                                logo: 'https://cdn.discordapp.com/attachments/1352390864730718339/1424045724278198302/image.png?ex=693e25ae&is=693cd42e&hm=803fb74b37e8b20076e0756b1532e7a44a25693a903f2514b9d8dcc7b7ace858&',
                            },
                            {
                                name: 'BunnyCraft',
                                logo: '/logos/bunnycraft.png',
                            },
                            {
                                name: 'SSPŠ',
                                logo: '/logos/ssps.png',
                            },
                        ].map((company, i) => (
                            <div key={i} className='flex items-center justify-center h-16 px-8 shrink-0 group'>
                                <img
                                    src={company.logo}
                                    alt={company.name}
                                    className='h-20 w-auto max-w-[220px] object-contain opacity-60 group-hover:opacity-100 transition-all duration-300'
                                    onError={(e) => {
                                        // Fallback to text if image fails to load
                                        const target = e.target as HTMLImageElement;
                                        target.style.display = 'none';
                                        const parent = target.parentElement;
                                        if (parent) {
                                            parent.innerHTML = `<span class="text-2xl font-black text-neutral-700 uppercase tracking-tighter">${company.name}</span>`;
                                        }
                                    }}
                                />
                            </div>
                        ))}
                    </InfiniteMarquee>
                </section>

                {/* THE "O" FEATURE SECTION */}
                <section className='py-32 relative overflow-hidden'>
                    <div className='max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-2 gap-16 items-center'>
                        {/* Text Side */}
                        <motion.div initial={{ opacity: 0 }} whileInView={{ opacity: 1 }} viewport={{ once: true }}>
                            <div className='flex items-center gap-2 mb-4'>
                                <div className='w-2 h-2 bg-brand rounded-full animate-pulse' />
                                <span className='text-xs font-bold uppercase tracking-widest text-white'>
                                    About Oasis Core
                                </span>
                            </div>
                            <h2 className='text-4xl md:text-5xl font-bold mb-6'>
                                Our Core Offerings - <br />
                                Meeting Your Needs
                            </h2>
                            <p className='text-neutral-400 mb-8 leading-relaxed'>
                                Technology has the tendency to be frustrating. With our custom &quot;Proton&quot; panel,
                                we relieve the stress of managing failing nodes so that you can focus on building your
                                community.
                            </p>

                            <div className='space-y-6'>
                                {[
                                    { title: 'Customer-centric Focus', desc: '24/7 Support via Discord & Ticket.' },
                                    { title: 'Professional Hardware', desc: 'Ryzen 9 7950X Nodes exclusively.' },
                                ].map((item, i) => (
                                    <div key={i} className='flex gap-4'>
                                        <div className='w-12 h-12 bg-brand/20 rounded-full flex items-center justify-center text-brand shrink-0'>
                                            <ArrowUpToLine width={20} height={20} />
                                        </div>
                                        <div>
                                            <h4 className='font-bold text-lg'>{item.title}</h4>
                                            <p className='text-sm text-neutral-500'>{item.desc}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </motion.div>

                        {/* Visual Side (The Planet) */}
                        <div className='relative flex justify-center'>
                            <motion.div
                                initial={{ scale: 0.8, opacity: 0 }}
                                whileInView={{ scale: 1, opacity: 1 }}
                                transition={{ duration: 1 }}
                                className='w-[300px] h-[300px] md:w-[500px] md:h-[500px] rounded-full bg-black border relative flex items-center justify-center'
                                style={{
                                    borderColor: 'color-mix(in srgb, var(--color-brand) 50%, transparent)',
                                    boxShadow: '0 0 100px color-mix(in srgb, var(--color-brand) 30%, transparent)',
                                }}
                            >
                                <div
                                    className='absolute inset-0 rounded-full bg-gradient-to-tr to-transparent'
                                    style={{
                                        background: `radial-gradient(circle, color-mix(in srgb, var(--color-brand) 40%, transparent) 0%, transparent 100%)`,
                                    }}
                                />
                                <div
                                    className='absolute inset-4 rounded-full border'
                                    style={{ borderColor: 'color-mix(in srgb, var(--color-brand) 20%, transparent)' }}
                                />
                                <div
                                    className='absolute inset-20 rounded-full bg-gradient-to-br to-black opacity-80 mix-blend-multiply'
                                    style={{
                                        background: `linear-gradient(to bottom right, var(--color-brand), black)`,
                                    }}
                                />
                                <span
                                    className='text-[200px] md:text-[300px] font-black select-none'
                                    style={{ color: 'color-mix(in srgb, var(--color-brand) 10%, transparent)' }}
                                >
                                    O
                                </span>

                                <motion.div
                                    animate={{ y: [0, -10, 0] }}
                                    transition={{ repeat: Infinity, duration: 4 }}
                                    className='absolute bottom-10 left-0 bg-neutral-900 border border-neutral-700 p-4 rounded-sm shadow-xl'
                                >
                                    <div className='text-3xl font-bold text-white'>50k+</div>
                                    <div className='text-xs text-neutral-400 uppercase tracking-widest'>
                                        Happy Clients
                                    </div>
                                </motion.div>
                            </motion.div>
                        </div>
                    </div>
                </section>

                {/* SERVICES (BENTO GRID) */}
                <section className='py-24 bg-neutral-950'>
                    <div className='max-w-7xl mx-auto px-6'>
                        <div className='flex flex-col md:flex-row justify-between items-end mb-16'>
                            <div>
                                <div className='flex items-center gap-2 mb-2'>
                                    <div className='w-8 h-[1px] bg-brand'></div>
                                    <span className='text-xs font-bold uppercase tracking-widest'>Our Services</span>
                                </div>
                                <h2 className='text-4xl font-bold max-w-lg'>
                                    Crafting digital solutions tailored to your unique needs.
                                </h2>
                            </div>
                            <button className='hidden md:flex items-center gap-2 text-brand font-bold text-sm uppercase hover:text-white transition-colors'>
                                View All Services <ArrowRight width={16} height={16} />
                            </button>
                        </div>

                        <motion.div
                            variants={containerVar}
                            initial='hidden'
                            whileInView='show'
                            viewport={{ once: false, margin: '0px' }}
                            className='grid grid-cols-1 md:grid-cols-3 gap-6'
                        >
                            <ServiceCard
                                colSpan='md:col-span-1'
                                icon={Server}
                                title='Game Hosting'
                                desc='We offer an array of services for high-performance gaming. Rust, Minecraft, CS2. 128-tick reliable networks.'
                                slug='game-hosting'
                                // accent={true}
                            />
                            <ServiceCard
                                colSpan='md:col-span-1'
                                icon={Server}
                                title='NVMe VPS'
                                desc='Root access. Linux or Windows. Deploy in seconds with our automated hypervisor orchestration.'
                                slug='vps'
                            />
                            <ServiceCard
                                colSpan='md:col-span-1'
                                icon={Database}
                                title='Object Storage'
                                desc='S3-Compatible buckets for your assets. Simply put, infinite storage that scales with your business.'
                                slug='object-storage'
                            />
                            <ServiceCard
                                colSpan='md:col-span-2'
                                icon={LinkIcon}
                                title='Web & Database Clusters'
                                desc='We not only build your site, we host it. Automated Redis and Postgres clusters with daily backups and point-in-time recovery.'
                                slug='web-database'
                            />
                            <ServiceCard
                                colSpan='md:col-span-1'
                                icon={Shield}
                                title='Dedicated Metal'
                                desc='Oasis offers top-tier bare metal hardware. No sharing resources. 100% of the CPU is yours.'
                                slug='dedicated-metal'
                            />
                        </motion.div>
                    </div>
                </section>

                {/* PRICING ENGINE */}
                <section id='pricing' className='py-32 px-6 max-w-7xl mx-auto'>
                    <div className='text-center mb-20'>
                        <div className='w-2 h-2 bg-brand rounded-full mx-auto mb-4' />
                        <h2 className='text-sm font-bold uppercase tracking-widest text-neutral-500 mb-4'>
                            Plans & Pricing
                        </h2>
                        <h3 className='text-4xl font-bold'>Simple Scaling</h3>
                    </div>

                    <div className='w-full'>
                        {/* Category and Billing Cycle Switchers - Stacked */}
                        <div className='flex justify-center mb-16'>
                            <div className='flex flex-col gap-0'>
                                {/* 1. Category Switcher */}
                                <div className='grid grid-cols-2 gap-0 bg-black border border-neutral-800 rounded-none overflow-hidden'>
                                    {CATEGORIES.map((cat) => (
                                        <button
                                            key={cat}
                                            onClick={() => setActiveCategory(cat)}
                                            className={`px-4 py-3 text-xs uppercase tracking-wider font-bold transition-colors border-r border-neutral-800 last:border-0 ${
                                                activeCategory === cat
                                                    ? 'bg-white text-black'
                                                    : 'text-neutral-500 hover:text-white'
                                            }`}
                                        >
                                            {cat}
                                        </button>
                                    ))}
                                </div>

                                {/* 2. Billing Cycle Switcher */}
                                <div className='grid grid-cols-4 gap-0 bg-black border border-neutral-800 border-t-0 rounded-none overflow-hidden'>
                                    {BILLING_CYCLES.map((cycle, i) => (
                                        <button
                                            key={cycle.label}
                                            onClick={() => setBillingIndex(i)}
                                            className={`px-4 py-3 text-xs uppercase tracking-wider font-bold transition-colors border-r border-neutral-800 last:border-0 ${
                                                billingIndex === i
                                                    ? 'bg-white text-black'
                                                    : 'text-neutral-500 hover:text-white'
                                            }`}
                                        >
                                            {cycle.label}
                                            {cycle.discount > 0 && (
                                                <span className='block text-[9px] text-brand mt-1'>
                                                    -{cycle.discount * 100}%
                                                </span>
                                            )}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* 3. The 4 Main Cards */}
                        {isLoading ? (
                            <div className='text-center py-20 text-neutral-400'>Loading plans...</div>
                        ) : error ? (
                            <div className='text-center py-20 text-brand'>
                                Failed to load plans. Please try again later.
                            </div>
                        ) : plans && plans.length > 0 ? (
                            <motion.div
                                key={activeCategory}
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.4 }}
                                className='grid grid-cols-1 md:grid-cols-4 gap-6 mb-16'
                            >
                                {plans
                                    .slice()
                                    .sort((a, b) => a.attributes.sort_order - b.attributes.sort_order)
                                    .slice(0, 4)
                                    .map((plan) => {
                                        const planPrice = getPlanPrice(plan);
                                        const finalPrice = getPrice(planPrice, plan);
                                        const isRecommended = plan?.attributes.is_most_popular ?? false;

                                        return (
                                            <div
                                                key={plan.attributes.id}
                                                className={`p-6 md:p-8 flex flex-col relative ${
                                                    isRecommended
                                                        ? 'bg-neutral-900 border-2 border-brand shadow-[0_0_20px_color-mix(in_srgb,var(--color-brand)_30%,transparent)]'
                                                        : 'bg-neutral-950 border border-neutral-800'
                                                }`}
                                            >
                                                {isRecommended && (
                                                    <div className='absolute -top-3 left-1/2 -translate-x-1/2 bg-brand text-white text-[10px] font-bold px-4 py-1.5 uppercase tracking-wider shadow-lg'>
                                                        Most Popular
                                                    </div>
                                                )}
                                                <div className='flex items-center gap-2 mb-2'>
                                                    <h3 className='text-brand font-bold uppercase tracking-widest text-sm'>
                                                        {plan.attributes.name}
                                                    </h3>
                                                    {isRecommended && (
                                                        <svg
                                                            width={16}
                                                            height={16}
                                                            viewBox='0 0 24 24'
                                                            fill='currentColor'
                                                            className='text-brand'
                                                        >
                                                            <polygon points='12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2' />
                                                        </svg>
                                                    )}
                                                </div>

                                                <div className='mt-auto mb-6'>
                                                    <div className='text-4xl font-bold text-white flex items-start gap-1'>
                                                        <span className='text-lg mt-1'>$</span>
                                                        {finalPrice.toFixed(0)}
                                                    </div>
                                                    <div className='text-xs text-neutral-500 uppercase mt-1'>
                                                        Per Month / Billed{' '}
                                                        {BILLING_CYCLES[billingIndex]?.label ?? 'Monthly'}
                                                    </div>
                                                </div>

                                                <ul className='space-y-3 mb-8 text-sm text-neutral-400'>
                                                    <li className='flex gap-2'>
                                                        <Server width={14} height={14} className='text-white' />{' '}
                                                        {getVCores(plan.attributes.cpu)} vCore
                                                    </li>
                                                    <li className='flex gap-2'>
                                                        <Gear width={14} height={14} className='text-white' />{' '}
                                                        {formatMemory(plan.attributes.memory)} Memory
                                                    </li>
                                                    <li className='flex gap-2'>
                                                        <Shield width={14} height={14} className='text-white' /> DDoS
                                                        Protection
                                                    </li>
                                                </ul>

                                                <button
                                                    onClick={() => handlePlanSelect(plan)}
                                                    className={`w-full py-3 text-xs font-bold uppercase tracking-widest border transition-all ${
                                                        isRecommended
                                                            ? 'bg-brand border-brand text-white'
                                                            : 'border-neutral-700 text-neutral-400 hover:border-white hover:text-white'
                                                    }`}
                                                    style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                                                >
                                                    Deploy
                                                </button>
                                            </div>
                                        );
                                    })}
                            </motion.div>
                        ) : (
                            <div className='text-center py-20 text-neutral-400'>No plans available.</div>
                        )}

                        {/* 4. Custom Slider Section */}
                        {/* <div className='border border-neutral-800 bg-neutral-900/50 p-8 rounded-xl relative overflow-hidden'>
                            <div className='absolute right-0 top-0 w-64 h-full bg-gradient-to-l from-red-900/10 to-transparent' />

                            <div className='flex flex-col md:flex-row gap-12 items-center relative z-10'>
                                <div className='flex-1 w-full'>
                                    <div className='flex items-center gap-3 mb-6'>
                                        <SlidersVertical className='text-red-500' width={24} height={24} />
                                        <h3 className='text-2xl font-bold'>Custom Configuration</h3>
                                    </div>
                                    <p className='text-neutral-400 mb-8'>
                                        Need specific resources? Slide to configure your exact RAM requirements. CPU
                                        cores scale automatically.
                                    </p>

                                    <div className='space-y-6'>
                                        <div className='flex justify-between font-mono text-sm'>
                                            <span>4 GB</span>
                                            <span className='text-red-500 font-bold'>{customRam} GB RAM</span>
                                            <span>32 GB</span>
                                        </div>
                                        <input
                                            type='range'
                                            min='4'
                                            max='32'
                                            step='1'
                                            value={customRam}
                                            onChange={(e) => setCustomRam(Number(e.target.value))}
                                            className='w-full h-2 bg-neutral-800 rounded-lg appearance-none cursor-pointer accent-red-600 hover:accent-red-500'
                                        />
                                        <div className='text-xs text-neutral-500 flex justify-between'>
                                            <span>2 vCore</span>
                                            <span>32 vCore</span>
                                        </div>
                                    </div>
                                </div>

                                <div className='bg-black border border-neutral-800 p-8 rounded-xl min-w-[300px] text-center'>
                                    <div className='text-neutral-500 uppercase text-xs font-bold mb-2'>
                                        Estimated Cost
                                    </div>
                                    {isCalculating ? (
                                        <div className='text-5xl font-black text-white mb-2'>...</div>
                                    ) : customPlanCalculation ? (
                                        <>
                                            <div className='text-5xl font-black text-white mb-2'>
                                                ${customPlanCalculation.price_per_month.toFixed(0)}
                                            </div>
                                            <div className='text-neutral-600 text-sm mb-6'>
                                                / {BILLING_CYCLES[billingIndex]?.label.toLowerCase() ?? 'month'}
                                            </div>
                                        </>
                                    ) : (
                                        <>
                                            <div className='text-5xl font-black text-white mb-2'>$0</div>
                                            <div className='text-neutral-600 text-sm mb-6'>
                                                / {BILLING_CYCLES[billingIndex]?.label.toLowerCase() ?? 'month'}
                                            </div>
                                        </>
                                    )}
                                    <button
                                        onClick={handleCustomPlanSelect}
                                        disabled={!customPlanCalculation || isCalculating}
                                        className='w-full bg-white text-black font-bold py-3 uppercase text-sm hover:bg-neutral-200 transition disabled:opacity-50 disabled:cursor-not-allowed'
                                    >
                                        Create Custom
                                    </button>
                                </div>
                            </div>
                        </div> */}
                    </div>
                </section>

                {/* TESTIMONIALS (Infinite Scroll Right-to-Left) */}
                {useMemo(
                    () => (
                        <section className='py-8 overflow-hidden bg-neutral-900 border-b border-neutral-800'>
                            <div className='max-w-7xl mx-auto px-6 mb-6'>
                                <h2 className='text-2xl font-bold'>Trusted by Developers</h2>
                            </div>

                            <InfiniteMarquee speed={40} direction='right'>
                                {testimonialsContent.map((item, i) => (
                                    <div
                                        key={i}
                                        className='w-[350px] shrink-0 bg-neutral-950 border-l-2 border-neutral-700 p-6 relative group hover:border-brand transition-colors'
                                    >
                                        <div className='flex gap-1 text-brand mb-4'>
                                            {[1, 2, 3, 4, 5].map((j) => (
                                                <StarIcon key={j} filled={true} size={14} />
                                            ))}
                                        </div>
                                        <p className='text-neutral-400 text-sm italic mb-6 leading-relaxed'>
                                            &quot;{item.t}&quot;
                                        </p>
                                        <div className='flex items-center gap-3'>
                                            <div className='w-10 h-10 bg-neutral-800 rounded-full flex items-center justify-center font-bold text-white'>
                                                {item.n[0]}
                                            </div>
                                            <div>
                                                <div className='text-white font-bold text-sm'>{item.n}</div>
                                                <div className='text-neutral-600 text-xs uppercase tracking-wider'>
                                                    {item.r}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </InfiniteMarquee>

                            <InfiniteMarquee speed={40} direction='left'>
                                {testimonialsContent.map((item, i) => (
                                    <div
                                        key={`reverse-${i}`}
                                        className='w-[350px] shrink-0 bg-neutral-950 border-l-2 border-neutral-700 p-6 relative group hover:border-brand transition-colors'
                                    >
                                        <div className='flex gap-1 text-brand mb-4'>
                                            {[1, 2, 3, 4, 5].map((j) => (
                                                <StarIcon key={j} filled={true} size={14} />
                                            ))}
                                        </div>
                                        <p className='text-neutral-400 text-sm italic mb-6 leading-relaxed'>
                                            &quot;{item.t}&quot;
                                        </p>
                                        <div className='flex items-center gap-3'>
                                            <div className='w-10 h-10 bg-neutral-800 rounded-full flex items-center justify-center font-bold text-white'>
                                                {item.n[0]}
                                            </div>
                                            <div>
                                                <div className='text-white font-bold text-sm'>{item.n}</div>
                                                <div className='text-neutral-600 text-xs uppercase tracking-wider'>
                                                    {item.r}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </InfiniteMarquee>
                        </section>
                    ),
                    // eslint-disable-next-line react-hooks/exhaustive-deps
                    [],
                )}

                {/* PROCESS SECTION (With Rotated Hover) */}
                <section className='py-32 px-6 max-w-7xl mx-auto border-t border-neutral-900'>
                    <div className='flex flex-col md:flex-row justify-between items-start mb-20 gap-8'>
                        <div>
                            <h3 className='text-4xl font-bold mb-4'>Method to the Creativity</h3>
                            <p className='text-neutral-400'>
                                Discover how we transform bare metal into digital solutions.
                            </p>
                        </div>
                        <button className='px-6 py-3 border border-neutral-800 text-sm font-bold uppercase hover:bg-white hover:text-black transition-colors'>
                            View Full Documentation
                        </button>
                    </div>

                    <motion.div
                        variants={containerVar}
                        initial='hidden'
                        whileInView='show'
                        viewport={{ once: true }}
                        className='grid grid-cols-2 md:grid-cols-4 gap-12 relative'
                    >
                        {/* Connector Line */}
                        <div className='hidden md:block absolute top-10 left-0 right-0 h-[1px] bg-neutral-800 -z-10' />

                        {[
                            { icon: Magnifier, t: 'Discovery', s: '01' },
                            { icon: Gear, t: 'Config', s: '02' },
                            { icon: ArrowUpToLine, t: 'Deploy', s: '03' },
                            { icon: Play, t: 'Launch', s: '04' },
                        ].map((item, i) => (
                            <motion.div
                                key={i}
                                variants={itemVar}
                                className='flex flex-col items-center text-center group cursor-pointer'
                                whileHover='hover'
                            >
                                <motion.div
                                    variants={{
                                        hover: { rotate: 90 },
                                    }}
                                    transition={{ type: 'spring', stiffness: 200, damping: 10 }}
                                    className='w-20 h-20 border border-neutral-700 bg-neutral-900 flex items-center justify-center mb-6 transition-colors group-hover:border-brand'
                                    style={{
                                        borderRadius: 'var(--button-border-radius, 0.5rem)',
                                    }}
                                >
                                    <motion.div
                                        variants={{
                                            hover: { rotate: -90 },
                                        }}
                                        transition={{ type: 'spring', stiffness: 200, damping: 10 }}
                                    >
                                        <item.icon
                                            className='text-white group-hover:text-brand transition-colors'
                                            width={32}
                                            height={32}
                                        />
                                    </motion.div>
                                </motion.div>
                                <div className='text-brand font-mono text-xs font-bold mb-2'>STEP {item.s}</div>
                                <h4 className='text-white font-bold text-lg uppercase'>{item.t}</h4>
                            </motion.div>
                        ))}
                    </motion.div>
                </section>

                {/* FOOTER */}
                <footer className='bg-neutral-950 pt-20 pb-10 border-t border-neutral-900'>
                    <div className='max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-12 mb-16'>
                        <div>
                            <div className='flex items-center gap-2 mb-6'>
                                <div
                                    className='w-6 h-6 bg-brand'
                                    style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                                />
                                <span className='font-bold text-lg'>OASIS</span>
                            </div>
                            <p className='text-neutral-500 text-sm leading-relaxed'>
                                Professional infrastructure for the next generation of digital experiences.
                            </p>
                        </div>
                        <div>
                            <h4 className='font-bold uppercase text-xs tracking-widest mb-6'>Contact</h4>
                            <ul className='space-y-4 text-sm text-neutral-400'>
                                <li>(405) 555-0123</li>
                                <li>support@oasis.cloud</li>
                            </ul>
                        </div>
                        <div>
                            <h4 className='font-bold uppercase text-xs tracking-widest mb-6'>Legal</h4>
                            <ul className='space-y-4 text-sm text-neutral-400'>
                                <li>Privacy Policy</li>
                                <li>Terms & Conditions</li>
                            </ul>
                        </div>
                        <div>
                            <h4 className='font-bold uppercase text-xs tracking-widest mb-6'>Subscribe</h4>
                            <div className='flex gap-2'>
                                <input
                                    type='email'
                                    placeholder='Email'
                                    className='bg-black border-b border-neutral-700 w-full p-2 text-sm focus:outline-none focus:border-brand'
                                    style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                                />
                                <button
                                    className='bg-brand text-xs font-bold px-4 py-2 uppercase'
                                    style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                                >
                                    GO
                                </button>
                            </div>
                        </div>
                    </div>
                    <div className='text-center text-xs text-neutral-600 pt-8 border-t border-neutral-900'>
                        © 2025 Oasis Technologies. Designed by Oliver.
                    </div>
                </footer>
            </div>
        </div>
    );
};

export default HostingContainer;
