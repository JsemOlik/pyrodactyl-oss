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
        document.title = 'Oasis Hosting - Game Servers & VPS Hosting';
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

    const getFirstMonthPrice = (price: number): number => {
        return Math.round(price * 0.5);
    };

    const getVCores = (cpu: number | null): number => {
        if (!cpu) return 0;
        return Math.round(cpu / 100);
    };

    const handlePlanSelect = (plan: HostingPlan) => {
        // Check if server creation is disabled
        if (serverCreationStatus && !serverCreationStatus.enabled) {
            navigate('/hosting/server-creation-disabled');
            return;
        }

        if (!isAuthenticated) {
            navigate(`/auth/login`, {
                state: {
                    from: `/hosting/configure?plan=${plan.attributes.id}&type=${hostingType}`,
                },
                replace: false,
            });
            return;
        }
        navigate(`/hosting/configure?plan=${plan.attributes.id}&type=${hostingType}`);
    };

    const handleCustomPlanSelect = () => {
        if (!customPlanCalculation) {
            return;
        }

        // Check if server creation is disabled
        if (serverCreationStatus && !serverCreationStatus.enabled) {
            navigate('/hosting/server-creation-disabled');
            return;
        }

        if (!isAuthenticated) {
            navigate(`/auth/login`, {
                state: {
                    from: `/hosting/configure?custom=true&memory=${customMemory}&interval=${customInterval}&type=${hostingType}`,
                },
                replace: false,
            });
            return;
        }
        navigate(
            `/hosting/configure?custom=true&memory=${customMemory}&interval=${customInterval}&type=${hostingType}`,
        );
    };

    const scrollToPricing = () => {
        const pricingSection = document.getElementById('pricing');
        if (pricingSection) {
            pricingSection.scrollIntoView({ behavior: 'smooth' });
        }
    };

    return (
        <div className='h-full min-h-screen bg-[#0a0a0a] overflow-y-auto -mx-2 -my-2 w-[calc(100%+1rem)]'>
            <Navbar />
            {/* Hero Section */}
            <section className='relative overflow-hidden'>
                <div className='absolute inset-0 bg-gradient-to-b from-brand/10 via-transparent to-transparent' />
                <div className='relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-32'>
                    <div className='text-center'>
                        <h1 className='text-5xl md:text-7xl font-bold text-white mb-6'>
                            Elevate your next
                            <br />
                            <span className='text-brand'>Game Server</span>
                        </h1>
                        <p className='text-xl text-white/70 mb-8 max-w-3xl mx-auto'>
                            Premium DDoS-protected high-performance servers powered by AMD Ryzen processors and
                            ultra-fast NVMe storage. Industry-leading uptime and 24/7 support.
                        </p>
                        <div className='flex flex-col sm:flex-row gap-4 justify-center items-center'>
                            <ActionButton variant='primary' size='lg' onClick={scrollToPricing}>
                                Get Started Now
                            </ActionButton>
                            <ActionButton variant='secondary' size='lg' onClick={() => navigate('/')}>
                                Manage your Servers
                            </ActionButton>
                        </div>

                        {/* Stats */}
                        <div className='mt-16 grid grid-cols-2 md:grid-cols-4 gap-8'>
                            <div>
                                <div className='text-4xl font-bold text-brand mb-2'>99.9%</div>
                                <div className='text-sm text-white/70'>Uptime Guarantee</div>
                            </div>
                            <div>
                                <div className='text-4xl font-bold text-brand mb-2'>24/7</div>
                                <div className='text-sm text-white/70'>Expert Support</div>
                            </div>
                            <div>
                                <div className='text-4xl font-bold text-brand mb-2'>10+</div>
                                <div className='text-sm text-white/70'>Global Locations</div>
                            </div>
                            <div>
                                <div className='text-4xl font-bold text-brand mb-2'>500+</div>
                                <div className='text-sm text-white/70'>Supported Games</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Featured Games Section */}
            <section className='py-20 bg-[#ffffff05]'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='text-center mb-12'>
                        <h2 className='text-4xl font-bold text-white mb-4'>Host all your favorite games on Oasis</h2>
                        <p className='text-white/70'>
                            Over 500 supported games with 1-click installation and automatic updates
                        </p>
                    </div>

                    <div className='grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-8'>
                        {[
                            'Minecraft',
                            'Rust',
                            'ARK',
                            'Valheim',
                            'CS2',
                            'Palworld',
                            'Zomboid',
                            'Satisfactory',
                            'Astroneer',
                            'Conan',
                        ].map((game, index) => (
                            <div
                                key={index}
                                className='aspect-video bg-[#ffffff08] border border-[#ffffff12] rounded-lg hover:border-brand/50 transition-all cursor-pointer flex items-center justify-center'
                            >
                                <span className='text-white/70 font-medium'>{game}</span>
                            </div>
                        ))}
                    </div>

                    <div className='text-center'>
                        <button className='text-brand hover:text-brand/80 font-medium'>
                            View All Supported Games →
                        </button>
                    </div>
                </div>
            </section>

            {/* Features Section */}
            <section className='py-20'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='text-center mb-16'>
                        <h2 className='text-4xl font-bold text-white mb-4'>
                            Unmatched Speed, <span className='text-brand'>Incredible Value.</span>
                        </h2>
                        <p className='text-white/70 max-w-2xl mx-auto'>
                            Experience the perfect blend of cutting-edge hardware and competitive pricing. We deliver
                            top-tier performance at a cost that makes sense.
                        </p>
                    </div>

                    <div className='grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-12'>
                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6 hover:border-brand/50 transition-all'>
                            <div className='w-12 h-12 bg-brand/20 rounded-lg flex items-center justify-center mb-4'>
                                <span className='text-2xl'>🔥</span>
                            </div>
                            <h3 className='text-xl font-bold text-white mb-2'>AMD Ryzen™ 9 9950X</h3>
                            <p className='text-white/70'>
                                Powered by the latest AMD Ryzen 9 9950X processors delivering up to 5.7 GHz for
                                unmatched gaming performance.
                            </p>
                        </div>

                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6 hover:border-brand/50 transition-all'>
                            <div className='w-12 h-12 bg-brand/20 rounded-lg flex items-center justify-center mb-4'>
                                <span className='text-2xl'>⚡</span>
                            </div>
                            <h3 className='text-xl font-bold text-white mb-2'>Instant Deployment</h3>
                            <p className='text-white/70'>
                                Get your server online in under 60 seconds. Automated setup with 1-click game
                                installation and mod support.
                            </p>
                        </div>

                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6 hover:border-brand/50 transition-all'>
                            <div className='w-12 h-12 bg-brand/20 rounded-lg flex items-center justify-center mb-4'>
                                <span className='text-2xl'>🛡️</span>
                            </div>
                            <h3 className='text-xl font-bold text-white mb-2'>DDoS Protection</h3>
                            <p className='text-white/70'>
                                Enterprise-grade DDoS protection included with all plans. Keep your server online 24/7
                                without interruption.
                            </p>
                        </div>

                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6 hover:border-brand/50 transition-all'>
                            <div className='w-12 h-12 bg-brand/20 rounded-lg flex items-center justify-center mb-4'>
                                <span className='text-2xl'>💾</span>
                            </div>
                            <h3 className='text-xl font-bold text-white mb-2'>NVMe Storage</h3>
                            <p className='text-white/70'>
                                Lightning-fast NVMe SSDs ensure rapid world loading, minimal lag, and instant backups
                                for your game data.
                            </p>
                        </div>

                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6 hover:border-brand/50 transition-all'>
                            <div className='w-12 h-12 bg-brand/20 rounded-lg flex items-center justify-center mb-4'>
                                <span className='text-2xl'>🔧</span>
                            </div>
                            <h3 className='text-xl font-bold text-white mb-2'>Full Root Access</h3>
                            <p className='text-white/70'>
                                Complete control over your server with FTP, SSH access, custom startup parameters, and
                                unlimited mod support.
                            </p>
                        </div>

                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6 hover:border-brand/50 transition-all'>
                            <div className='w-12 h-12 bg-brand/20 rounded-lg flex items-center justify-center mb-4'>
                                <span className='text-2xl'>💬</span>
                            </div>
                            <h3 className='text-xl font-bold text-white mb-2'>24/7 Expert Support</h3>
                            <p className='text-white/70'>
                                Our expert support team is available around the clock via Discord, ticket system, and
                                live chat.
                            </p>
                        </div>
                    </div>

                    {/* Powered By Section */}
                    <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-8'>
                        <div className='text-center mb-6'>
                            <h4 className='text-sm font-medium text-white/70 mb-4'>POWERED BY</h4>
                            <div className='flex items-center justify-center gap-8'>
                                <div className='text-white/70'>
                                    <div className='text-2xl font-bold'>AMD RYZEN™</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* Testimonials Section */}
            <section className='py-20 bg-[#ffffff05]'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='text-center mb-16'>
                        <h2 className='text-4xl font-bold text-white mb-4'>Loved by gamers worldwide</h2>
                        <p className='text-white/70'>
                            Join thousands of satisfied customers who trust Oasis for their game server hosting needs
                        </p>
                    </div>

                    <div className='grid md:grid-cols-2 lg:grid-cols-3 gap-6'>
                        {[
                            {
                                name: 'Shadowblade',
                                game: 'Minecraft',
                                rating: 5,
                                text: "Best hosting provider I've used. The performance is incredible and the support team is always helpful. Highly recommended!",
                            },
                            {
                                name: 'xXGamerXx',
                                game: 'Rust',
                                rating: 5,
                                text: 'Zero lag, great prices, and amazing customer service. My Rust server has never run better!',
                            },
                            {
                                name: 'CommanderZero',
                                game: 'ARK',
                                rating: 5,
                                text: 'The DDoS protection is top-notch. We had issues with other hosts but Oasis keeps us online 24/7.',
                            },
                            {
                                name: 'PrimeGaming',
                                game: 'Valheim',
                                rating: 5,
                                text: 'Setup was incredibly easy. Had our server running in minutes. The control panel is intuitive and powerful.',
                            },
                            {
                                name: 'NightOwl',
                                game: 'CS2',
                                rating: 5,
                                text: 'Outstanding performance and reliability. The AMD Ryzen processors really make a difference!',
                            },
                            {
                                name: 'PhoenixRising',
                                game: 'Palworld',
                                rating: 5,
                                text: 'Migrated from another host and the difference is night and day. Better performance, better support, better price.',
                            },
                        ].map((review, index) => (
                            <div key={index} className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6'>
                                <div className='flex items-center gap-2 mb-4'>
                                    {[...Array(review.rating)].map((_, i) => (
                                        <span key={i} className='text-brand'>
                                            ★
                                        </span>
                                    ))}
                                </div>
                                <p className='text-white/70 mb-4'>"{review.text}"</p>
                                <div className='flex items-center gap-3'>
                                    <div className='w-10 h-10 bg-brand/20 rounded-full flex items-center justify-center'>
                                        <span className='text-brand font-bold'>{review.name[0]}</span>
                                    </div>
                                    <div>
                                        <div className='text-white font-medium'>{review.name}</div>
                                        <div className='text-xs text-white/50'>{review.game} Server Owner</div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Locations Section */}
            <section className='py-20'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='grid lg:grid-cols-2 gap-12 items-center'>
                        <div>
                            <h2 className='text-4xl font-bold text-white mb-4'>
                                Global Presence, <span className='text-brand'>Local Performance</span>
                            </h2>
                            <p className='text-white/70 mb-8'>
                                Our worldwide network of premium datacenters spans the Americas and Europe, delivering
                                ultra-low ping and lightning-fast performance wherever you play.
                            </p>

                            <div className='space-y-4'>
                                {[
                                    {
                                        region: 'North America',
                                        locations: ['Los Angeles', 'New York', 'Dallas'],
                                    },
                                    {
                                        region: 'Europe',
                                        locations: ['London', 'Frankfurt', 'Paris'],
                                    },
                                    {
                                        region: 'Asia Pacific',
                                        locations: ['Singapore', 'Tokyo', 'Sydney'],
                                    },
                                ].map((region, index) => (
                                    <div
                                        key={index}
                                        className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-4'
                                    >
                                        <div className='flex items-center justify-between'>
                                            <div>
                                                <div className='text-white font-medium mb-1'>{region.region}</div>
                                                <div className='text-sm text-white/50'>
                                                    {region.locations.join(', ')}
                                                </div>
                                            </div>
                                            <div className='flex items-center gap-2'>
                                                <div className='w-2 h-2 bg-green-500 rounded-full animate-pulse' />
                                                <span className='text-xs text-white/70'>Online</span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className='relative h-[500px] bg-[#ffffff08] border border-[#ffffff12] rounded-lg flex items-center justify-center'>
                            <span className='text-white/50'>[World Map Placeholder]</span>
                        </div>
                    </div>
                </div>
            </section>

            {/* Pricing Section */}
            <section id='pricing' className='py-20 bg-[#ffffff05]'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='text-center mb-12'>
                        <h2 className='text-4xl font-bold text-white mb-4'>Simple, transparent pricing</h2>
                        <p className='text-white/70'>
                            Choose the perfect plan for your needs. All plans include DDoS protection, automatic
                            backups, and 24/7 support.
                        </p>
                    </div>

                    {/* Hosting Type Selector */}
                    <div className='mb-8 flex justify-center'>
                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-1 inline-flex gap-1'>
                            <button
                                onClick={() => setHostingType('game-server')}
                                className={`px-6 py-2 rounded-md font-medium transition-all ${
                                    hostingType === 'game-server'
                                        ? 'bg-brand text-white'
                                        : 'text-white/70 hover:text-white'
                                }`}
                            >
                                Game Server
                            </button>
                            <button
                                onClick={() => setHostingType('vps')}
                                className={`px-6 py-2 rounded-md font-medium transition-all ${
                                    hostingType === 'vps' ? 'bg-brand text-white' : 'text-white/70 hover:text-white'
                                }`}
                            >
                                VPS
                            </button>
                        </div>
                    </div>

                    {/* Loading/Error States */}
                    {isLoading && (
                        <div className='flex items-center justify-center min-h-[400px]'>
                            <div className='text-white/70'>Loading plans...</div>
                        </div>
                    )}

                    {error && (
                        <div className='flex items-center justify-center min-h-[400px]'>
                            <div className='text-red-400'>Failed to load hosting plans. Please try again later.</div>
                        </div>
                    )}

                    {/* Plans Grid */}
                    {!isLoading && !error && (
                        <div className='space-y-8'>
                            {/* Predefined Plans */}
                            {plans && plans.length > 0 && (
                                <div className='grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4'>
                                    {plans.map((plan, index) => {
                                        const attrs = plan.attributes;
                                        const monthlyPrice = attrs.pricing.monthly;
                                        const firstMonthPrice = getFirstMonthPrice(monthlyPrice);
                                        const isMostPopular = index === 2;
                                        const vCores = getVCores(attrs.cpu);

                                        return (
                                            <div
                                                key={plan.attributes.id}
                                                className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6 hover:border-brand/50 transition-all relative flex flex-col'
                                            >
                                                {isMostPopular && (
                                                    <div className='absolute top-4 right-4'>
                                                        <span className='bg-brand text-white text-xs font-semibold px-3 py-1 rounded-full'>
                                                            Most Popular
                                                        </span>
                                                    </div>
                                                )}

                                                <div className='mb-4'>
                                                    <div className='w-12 h-12 bg-brand/20 rounded-lg flex items-center justify-center'>
                                                        <span className='text-2xl'>💻</span>
                                                    </div>
                                                </div>

                                                <h3 className='text-2xl font-bold text-white mb-4'>{attrs.name}</h3>

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
                                                        Then {formatPrice(monthlyPrice)}
                                                        /month
                                                    </div>
                                                </div>

                                                <div className='space-y-2 mb-6 flex-1'>
                                                    <div className='text-sm text-white/70'>AMD Ryzen™ 9 9950X</div>
                                                    {vCores > 0 && (
                                                        <div className='text-sm text-white/70'>
                                                            {vCores} vCores @ ~5.7 GHz
                                                        </div>
                                                    )}
                                                    {attrs.memory && (
                                                        <div className='text-sm text-white/70'>
                                                            {formatMemory(attrs.memory)} DDR5 RAM
                                                        </div>
                                                    )}
                                                    <div className='text-sm text-white/70'>Unlimited NVMe Storage</div>
                                                    <div className='text-sm text-white/70'>128 Free Backup Slots</div>
                                                    <div className='text-sm text-white/70'>12 Port Allocations</div>
                                                    <div className='text-sm text-white/70'>Free subdomain</div>
                                                    <div className='text-sm text-white/70'>
                                                        Always-On DDoS Protection
                                                    </div>
                                                </div>

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
                            )}

                            {/* Custom Plan */}
                            <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6'>
                                <div className='flex flex-col md:flex-row gap-8'>
                                    <div className='flex-1'>
                                        <div className='flex items-center gap-3 mb-6'>
                                            <div className='w-12 h-12 bg-brand/20 rounded-lg flex items-center justify-center'>
                                                <span className='text-2xl'>⚡</span>
                                            </div>
                                            <h3 className='text-2xl font-bold text-white'>Custom</h3>
                                        </div>

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

                                        <div className='space-y-2 mb-6'>
                                            <div className='text-sm text-white/70'>AMD Ryzen™ 9 9950X</div>
                                            <div className='text-sm text-white/70'>
                                                {formatMemory(customMemory)} DDR5 RAM
                                            </div>
                                            <div className='text-sm text-white/70'>128 Free Backup Slots</div>
                                            <div className='text-sm text-white/70'>Free subdomain</div>
                                            <div className='text-sm text-white/70'>8 vCores @ ~5.7 GHz</div>
                                            <div className='text-sm text-white/70'>Unlimited NVMe Storage</div>
                                            <div className='text-sm text-white/70'>12 Port Allocations</div>
                                            <div className='text-sm text-white/70'>Always-On DDoS Protection</div>
                                        </div>

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
                                                    {formatPrice(
                                                        customPlanCalculation.price,
                                                        customPlanCalculation.currency,
                                                    )}
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
                    )}
                </div>
            </section>

            {/* Coming Soon Section */}
            <section className='py-20'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='text-center mb-12'>
                        <h2 className='text-4xl font-bold text-white mb-4'>Coming Soon</h2>
                        <p className='text-white/70'>
                            We're expanding our services. Stay tuned for these exciting additions!
                        </p>
                    </div>

                    <div className='grid md:grid-cols-2 gap-6'>
                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-8 text-center relative overflow-hidden'>
                            <div className='absolute top-4 right-4'>
                                <span className='bg-brand/20 text-brand text-xs font-semibold px-3 py-1 rounded-full'>
                                    Coming Soon
                                </span>
                            </div>
                            <div className='w-16 h-16 bg-brand/20 rounded-lg flex items-center justify-center mx-auto mb-4'>
                                <span className='text-4xl'>🗄️</span>
                            </div>
                            <h3 className='text-2xl font-bold text-white mb-2'>Database Hosting</h3>
                            <p className='text-white/70'>
                                High-performance MySQL, PostgreSQL, and MongoDB hosting with automatic backups and
                                scaling.
                            </p>
                        </div>

                        <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-8 text-center relative overflow-hidden'>
                            <div className='absolute top-4 right-4'>
                                <span className='bg-brand/20 text-brand text-xs font-semibold px-3 py-1 rounded-full'>
                                    Coming Soon
                                </span>
                            </div>
                            <div className='w-16 h-16 bg-brand/20 rounded-lg flex items-center justify-center mx-auto mb-4'>
                                <span className='text-4xl'>☁️</span>
                            </div>
                            <h3 className='text-2xl font-bold text-white mb-2'>S3 Storage</h3>
                            <p className='text-white/70'>
                                Scalable object storage compatible with S3 API. Perfect for backups, assets, and media
                                files.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {/* FAQ Section */}
            <section className='py-20 bg-[#ffffff05]'>
                <div className='max-w-4xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='text-center mb-12'>
                        <h2 className='text-4xl font-bold text-white mb-4'>Frequently Asked Questions</h2>
                        <p className='text-white/70'>
                            Got questions? We've got answers. Can't find what you're looking for? Contact our support
                            team.
                        </p>
                    </div>

                    <div className='space-y-4'>
                        {[
                            {
                                q: 'How quickly can I get my server online?',
                                a: 'Most servers are deployed within 60 seconds of purchase. Once your payment is confirmed, our automated system will provision your server immediately.',
                            },
                            {
                                q: 'Can I upgrade or downgrade my plan?',
                                a: 'Yes! You can upgrade or downgrade your plan at any time. Upgrades are instant, and downgrades take effect at the start of your next billing cycle.',
                            },
                            {
                                q: 'What payment methods do you accept?',
                                a: 'We accept all major credit cards, PayPal, cryptocurrency, and various regional payment methods. All payments are processed securely.',
                            },
                            {
                                q: 'Do you offer refunds?',
                                a: "Yes, we offer a 48-hour money-back guarantee. If you're not satisfied with our service, contact support within 48 hours for a full refund.",
                            },
                            {
                                q: 'How does the DDoS protection work?',
                                a: 'All servers include enterprise-grade DDoS protection that automatically detects and mitigates attacks in real-time, keeping your server online.',
                            },
                            {
                                q: 'Can I install custom mods and plugins?',
                                a: 'Absolutely! You have full FTP and file manager access to install any mods, plugins, or custom configurations you need.',
                            },
                            {
                                q: 'What backup options are available?',
                                a: 'All plans include 128 free backup slots with automated daily backups. You can also create manual backups at any time and restore with one click.',
                            },
                            {
                                q: 'Is there a setup fee?',
                                a: 'No setup fees! The price you see is the price you pay. No hidden costs or surprise charges.',
                            },
                        ].map((faq, index) => (
                            <details
                                key={index}
                                className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg overflow-hidden group'
                            >
                                <summary className='px-6 py-4 cursor-pointer text-white font-medium hover:bg-[#ffffff08] transition-colors list-none flex items-center justify-between'>
                                    <span>{faq.q}</span>
                                    <span className='text-brand text-xl group-open:rotate-45 transition-transform'>
                                        +
                                    </span>
                                </summary>
                                <div className='px-6 pb-4 text-white/70'>{faq.a}</div>
                            </details>
                        ))}
                    </div>

                    <div className='mt-12 text-center'>
                        <p className='text-white/70 mb-4'>Still have questions?</p>
                        <ActionButton
                            variant='secondary'
                            onClick={() => window.open('https://discord.gg/oasis', '_blank')}
                        >
                            Join our Discord
                        </ActionButton>
                    </div>
                </div>
            </section>

            {/* Footer */}
            <footer className='bg-[#0a0a0a] border-t border-[#ffffff12] py-12'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8'>
                    <div className='grid md:grid-cols-4 gap-8 mb-8'>
                        <div>
                            <h3 className='text-white font-bold text-xl mb-4'>Oasis Hosting</h3>
                            <p className='text-white/70 text-sm'>
                                Premium game server and VPS hosting powered by cutting-edge AMD Ryzen processors.
                            </p>
                        </div>

                        <div>
                            <h4 className='text-white font-medium mb-4'>Products</h4>
                            <ul className='space-y-2 text-sm text-white/70'>
                                <li>
                                    <button
                                        onClick={() => setHostingType('game-server')}
                                        className='hover:text-brand transition-colors'
                                    >
                                        Game Servers
                                    </button>
                                </li>
                                <li>
                                    <button
                                        onClick={() => setHostingType('vps')}
                                        className='hover:text-brand transition-colors'
                                    >
                                        VPS Hosting
                                    </button>
                                </li>
                                <li className='text-white/50'>Database Hosting (Soon)</li>
                                <li className='text-white/50'>S3 Storage (Soon)</li>
                            </ul>
                        </div>

                        <div>
                            <h4 className='text-white font-medium mb-4'>Support</h4>
                            <ul className='space-y-2 text-sm text-white/70'>
                                <li>
                                    <a href='#' className='hover:text-brand transition-colors'>
                                        Knowledge Base
                                    </a>
                                </li>
                                <li>
                                    <a href='#' className='hover:text-brand transition-colors'>
                                        Discord
                                    </a>
                                </li>
                                <li>
                                    <a href='#' className='hover:text-brand transition-colors'>
                                        Submit Ticket
                                    </a>
                                </li>
                                <li>
                                    <a href='#' className='hover:text-brand transition-colors'>
                                        Status Page
                                    </a>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <h4 className='text-white font-medium mb-4'>Legal</h4>
                            <ul className='space-y-2 text-sm text-white/70'>
                                <li>
                                    <a href='#' className='hover:text-brand transition-colors'>
                                        Terms of Service
                                    </a>
                                </li>
                                <li>
                                    <a href='#' className='hover:text-brand transition-colors'>
                                        Privacy Policy
                                    </a>
                                </li>
                                <li>
                                    <a href='#' className='hover:text-brand transition-colors'>
                                        Refund Policy
                                    </a>
                                </li>
                                <li>
                                    <a href='#' className='hover:text-brand transition-colors'>
                                        Acceptable Use
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div className='border-t border-[#ffffff12] pt-8 flex flex-col md:flex-row items-center justify-between gap-4'>
                        <div className='text-sm text-white/50'>© 2025 Oasis Hosting. All rights reserved.</div>
                        <div className='flex items-center gap-4'>
                            <a href='#' className='text-white/70 hover:text-brand transition-colors'>
                                Twitter
                            </a>
                            <a href='#' className='text-white/70 hover:text-brand transition-colors'>
                                Discord
                            </a>
                            <a href='#' className='text-white/70 hover:text-brand transition-colors'>
                                GitHub
                            </a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    );
};

export default HostingContainer;
