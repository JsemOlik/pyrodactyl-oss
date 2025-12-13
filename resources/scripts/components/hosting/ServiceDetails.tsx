import {
    AntennaSignal,
    ArrowLeft,
    ChevronRight,
    CircleCheck,
    Cpu,
    Globe,
    LayoutHeader,
    LifeRing,
    Lock,
    Magnifier,
    Power,
    Server,
    Shield,
    Stopwatch,
    Terminal,
} from '@gravity-ui/icons';
import { AnimatePresence, motion } from 'framer-motion';
import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';

import Navbar from '@/components/Navbar';

// --- TYPES ---
interface ServiceFeature {
    title: string;
    desc: string;
    icon: React.ComponentType<{ width?: number; height?: number; className?: string }>;
}

interface GameSupport {
    name: string;
    image: string;
    tag: string;
}

interface ServiceData {
    title: string;
    subtitle: string;
    heroImage: string;
    description: string; // Long form description
    features: ServiceFeature[];
    technical: {
        cpu: string;
        ram: string;
        disk: string;
        uplink: string;
    };
    // Optional extras for specific pages
    supportedGames?: GameSupport[];
    showcaseImages?: string[]; // For the "Gamer Vibe" collage
    useCases?: { title: string; desc: string }[]; // For VPS/Enterprise
}

// --- MOCK DATA ---
const SERVICE_DATA: Record<string, ServiceData> = {
    'game-hosting': {
        title: 'Game Server Hosting',
        subtitle: 'High-tickrate, low-latency infrastructure designed for competitive play.',
        heroImage: 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&q=80&w=2070',
        description:
            "Don't let lag decide the winner. Our game hosting infrastructure is built on overclocked Ryzen 9 processors and NVMe storage to ensure your server processes every tick perfectly. Whether you are hosting a private Minecraft SMP or a ranked CS2 community with 100+ players, our custom 'Proton' panel gives you the power to manage plugins, mods, and backups with a single click.",
        technical: {
            cpu: 'Ryzen 9 7950X (5.7GHz)',
            ram: 'DDR5 ECC Memory',
            disk: 'NVMe Gen4 Arrays',
            uplink: '10Gbps Fiber',
        },
        showcaseImages: [
            'https://images.unsplash.com/photo-1607677686475-ad5406cd105c?auto=format&fit=crop&q=80&w=800', // Minecraft
            'https://images.unsplash.com/photo-1552820728-8b83bb6b773f?auto=format&fit=crop&q=80&w=800', // Rust/Survival
            'https://images.unsplash.com/photo-1605901309584-818e25960b8f?auto=format&fit=crop&q=80&w=800', // GTA/Cyberpunk vibes
            'https://images.unsplash.com/photo-1593305841991-05c29736ce37?auto=format&fit=crop&q=80&w=800', // Terraria/2D vibes
        ],
        supportedGames: [
            {
                name: 'Minecraft (Java & Bedrock)',
                tag: 'Survival',
                image: 'https://images.unsplash.com/photo-1627856014759-2bd0131245e8?auto=format&fit=crop&q=80&w=400',
            },
            {
                name: 'Rust',
                tag: 'Survival FPS',
                image: 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&q=80&w=400',
            },
            {
                name: 'Grand Theft Auto V (FiveM)',
                tag: 'Open World',
                image: 'https://images.unsplash.com/photo-1595878715977-2e8f8df18ea8?auto=format&fit=crop&q=80&w=400',
            },
            {
                name: 'Terraria',
                tag: 'Adventure',
                image: 'https://images.unsplash.com/photo-1592155931584-901ac15763e3?auto=format&fit=crop&q=80&w=400',
            },
            {
                name: 'Counter-Strike 2',
                tag: 'Competitive',
                image: 'https://images.unsplash.com/photo-1605901309584-818e25960b8f?auto=format&fit=crop&q=80&w=400',
            },
            {
                name: 'Ark: Survival Evolved',
                tag: 'Survival',
                image: 'https://images.unsplash.com/photo-1552820728-8b83bb6b773f?auto=format&fit=crop&q=80&w=400',
            },
            {
                name: 'Palworld',
                tag: 'Survival',
                image: 'https://images.unsplash.com/photo-1642425149556-b6f90e9568d2?auto=format&fit=crop&q=80&w=400',
            },
            {
                name: 'Valheim',
                tag: 'Co-op',
                image: 'https://images.unsplash.com/photo-1519074069444-1ba4fff66d16?auto=format&fit=crop&q=80&w=400',
            },
            {
                name: 'Project Zomboid',
                tag: 'Zombie Survival',
                image: 'https://images.unsplash.com/photo-1509248961158-e54f6934749c?auto=format&fit=crop&q=80&w=400',
            },
        ],
        features: [
            {
                title: 'DDoS Protection',
                desc: 'Always-on 12Tbps Path.net mitigation filters attacks instantly so your community never drops.',
                icon: Shield,
            },
            {
                title: 'Automated Backups',
                desc: 'Daily off-site backups ensure your progress is never lost, with 1-click restore capability.',
                icon: CircleCheck,
            },
            {
                title: 'Mod Support',
                desc: 'One-click install for Oxide, Spigot, PaperMC, and easy drag-and-drop FTP access.',
                icon: Cpu,
            },
            {
                title: 'Instant Setup',
                desc: 'Servers deploy automatically within 60 seconds of payment confirmation.',
                icon: Stopwatch,
            },
        ],
    },
    vps: {
        title: 'NVMe VPS',
        subtitle: 'Root access KVM slices for developers who need total control.',
        heroImage: 'https://images.unsplash.com/photo-1558494949-ef526b0042a0?auto=format&fit=crop&q=80&w=2070',
        description:
            'Break free from shared hosting limitations. Our KVM Virtual Private Servers provide you with a dedicated slice of hardware, full root access, and your choice of Operating System. Whether you are running a Discord bot, a VPN, a web server, or a private game node, our VPS solutions offer the raw performance of bare metal with the flexibility of the cloud.',
        technical: {
            cpu: 'EPYC Genoa / Ryzen 9',
            ram: 'Dedicated RAM',
            disk: 'Ceph NVMe Storage',
            uplink: '10Gbps Public',
        },
        useCases: [
            { title: 'Application Hosting', desc: 'Deploy Node.js, Python, or Go apps with Nginx/Apache.' },
            { title: 'Discord Bots', desc: 'Keep your bot online 24/7 with 99.99% uptime guarantees.' },
            { title: 'VPN Tunneling', desc: 'Secure your browsing or access restricted content with WireGuard.' },
            { title: 'Development', desc: 'Perfect isolated environments for testing CI/CD pipelines.' },
        ],
        features: [
            { title: 'Full Root Access', desc: 'SSH key management and full sudo privileges.', icon: Lock },
            { title: 'Custom ISOs', desc: 'Bring your own OS or use our pre-built Linux templates.', icon: Server },
            {
                title: 'Snapshots',
                desc: 'Take instant snapshots of your disk state before major updates.',
                icon: CircleCheck,
            },
            { title: 'VNC Access', desc: 'Out-of-band console access even if networking fails.', icon: LifeRing },
        ],
    },
    // ... (Keep other services if needed, referencing VPS structure)
};

// --- SUB-COMPONENTS ---

const ShimmerButton = ({ text, onClick }: { text: string; onClick?: () => void }) => (
    <motion.button
        whileHover={{ scale: 1.05 }}
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

const SpecCard = ({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: string;
    icon: React.ComponentType<{ width?: number; height?: number; className?: string }>;
}) => (
    <div className='bg-neutral-900/50 border border-neutral-800 p-4 rounded-lg flex items-center gap-4 hover:border-brand/50 transition-colors'>
        <div className='p-2 bg-brand/10 rounded text-brand'>
            <Icon width={20} height={20} />
        </div>
        <div>
            <div className='text-xs text-neutral-500 uppercase tracking-wider font-bold'>{label}</div>
            <div className='text-white font-bold'>{value}</div>
        </div>
    </div>
);

const FeatureCard = ({ item, index }: { item: ServiceFeature; index: number }) => {
    const Icon = item.icon;
    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.1 }}
            viewport={{ once: true }}
            className='p-6 bg-neutral-950 border border-neutral-800 hover:border-brand transition-colors group rounded-xl'
        >
            <div className='w-12 h-12 bg-neutral-900 rounded-lg flex items-center justify-center mb-4 text-white group-hover:text-brand group-hover:bg-brand/10 transition-colors'>
                <Icon width={24} height={24} />
            </div>
            <h3 className='text-lg font-bold text-white mb-2'>{item.title}</h3>
            <p className='text-neutral-400 text-sm leading-relaxed'>{item.desc}</p>
        </motion.div>
    );
};

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

const GameLibrary = ({ games }: { games: GameSupport[] }) => {
    const [search, setSearch] = useState('');
    const filteredGames = games.filter((g) => g.name.toLowerCase().includes(search.toLowerCase()));

    const GameCard = ({ game }: { game: GameSupport }) => (
        <div className='group relative w-[280px] shrink-0 aspect-[4/3] rounded-xl overflow-hidden cursor-pointer'>
            <img
                src={game.image}
                alt={game.name}
                className='w-full h-full object-cover transition-transform duration-500 group-hover:scale-110'
            />
            <div className='absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent opacity-80 group-hover:opacity-90 transition-opacity' />
            <div className='absolute bottom-0 left-0 p-4 w-full'>
                <span className='inline-block px-2 py-1 bg-brand/90 backdrop-blur-sm text-[10px] font-bold uppercase rounded mb-2 text-white'>
                    {game.tag}
                </span>
                <h3 className='text-white font-bold leading-tight group-hover:text-brand transition-colors'>
                    {game.name}
                </h3>
            </div>
        </div>
    );

    return (
        <section className='py-24 px-6 max-w-7xl mx-auto'>
            <div className='text-center mb-12'>
                <h2 className='text-3xl font-bold mb-4'>Supported Games</h2>
                <p className='text-neutral-400 mb-8'>
                    Instantly deploy any of these titles with our 1-Click Installer.
                </p>

                <div className='max-w-md mx-auto relative'>
                    <input
                        type='text'
                        placeholder='Search for your favorite game...'
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className='w-full bg-neutral-900 border border-neutral-700 text-white pl-12 pr-4 py-3 focus:outline-none focus:border-brand focus:ring-1 focus:ring-brand transition-all'
                        style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                    />
                    <div className='absolute left-4 top-1/2 -translate-y-1/2 text-neutral-500'>
                        <Magnifier width={18} height={18} />
                    </div>
                </div>
            </div>

            <div className='overflow-hidden min-h-[300px]'>
                <AnimatePresence mode='wait'>
                    {filteredGames.length === 0 ? (
                        <motion.div
                            key='no-results'
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: -20 }}
                            className='text-center py-10 text-neutral-500'
                        >
                            No games found matching &quot;{search}&quot;.
                            <br />
                            <span className='text-xs'>But you can likely host it via our Custom Docker container!</span>
                        </motion.div>
                    ) : filteredGames.length === 1 && filteredGames[0] ? (
                        <motion.div
                            key='single-game'
                            initial={{ opacity: 0, scale: 0.9 }}
                            animate={{ opacity: 1, scale: 1 }}
                            exit={{ opacity: 0, scale: 0.9 }}
                            className='flex justify-center'
                        >
                            <GameCard game={filteredGames[0]} />
                        </motion.div>
                    ) : (
                        <motion.div
                            key='multiple-games'
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                        >
                            <InfiniteMarquee speed={30} direction='right'>
                                {filteredGames.map((game) => (
                                    <GameCard key={game.name} game={game} />
                                ))}
                            </InfiniteMarquee>
                        </motion.div>
                    )}
                </AnimatePresence>
            </div>
        </section>
    );
};

// --- MAIN COMPONENT ---

export default function ServiceDetails() {
    const { slug } = useParams<{ slug: string }>();
    const data = (slug && SERVICE_DATA[slug] ? SERVICE_DATA[slug] : SERVICE_DATA['game-hosting']) as ServiceData;
    const isGameHosting = slug === 'game-hosting' || !slug;

    useEffect(() => {
        document.title = `${data.title} - Oasis Cloud`;
        window.scrollTo(0, 0);

        const hostingButtonRadius = document.documentElement.getAttribute('data-hosting-button-radius') || '0.5rem';
        const root = document.documentElement;
        root.style.setProperty('--button-border-radius', hostingButtonRadius);

        return () => {
            root.style.removeProperty('--button-border-radius');
        };
    }, [data.title]);

    return (
        <div className='h-full min-h-screen bg-black text-white font-sans selection:bg-brand selection:text-white overflow-y-auto overflow-x-hidden -mx-2 -my-2 w-[calc(100%+1rem)]'>
            <Navbar />

            {/* HERO SECTION */}
            <section className='relative pt-32 pb-24 px-6 border-b border-neutral-900 overflow-hidden'>
                {/* Background Image - Brightened per request (opacity-40) */}
                <div className='absolute inset-0 z-0'>
                    <img
                        src={data.heroImage}
                        alt='Background'
                        className='w-full h-full object-cover opacity-40 blur-[2px]'
                    />
                    {/* Gradient overlay to ensure text readability */}
                    <div className='absolute inset-0 bg-gradient-to-t from-black via-black/70 to-black/30' />
                </div>

                <div className='relative z-10 max-w-7xl mx-auto'>
                    <Link
                        to='/'
                        className='inline-flex items-center gap-2 text-neutral-400 hover:text-white mb-8 text-sm font-bold uppercase tracking-wider transition-colors bg-black/50 backdrop-blur-md px-4 py-2 border border-white/10'
                        style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                    >
                        <ArrowLeft width={16} height={16} /> Back to Services
                    </Link>

                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        className='grid lg:grid-cols-2 gap-12'
                    >
                        <div>
                            <h1 className='text-5xl md:text-7xl font-black uppercase mb-6 tracking-tight'>
                                {data.title}
                            </h1>
                            <p className='text-xl font-medium text-white mb-6 border-l-4 border-brand pl-6'>
                                {data.subtitle}
                            </p>
                            <p className='text-neutral-400 leading-relaxed mb-8 text-lg'>{data.description}</p>

                            <div className='flex flex-wrap gap-4'>
                                <Link
                                    to='/'
                                    className='bg-brand text-white px-8 py-4 font-bold hover:bg-white hover:text-black transition-all shadow-[0_0_30px_rgba(var(--color-brand-rgb),0.4)] flex items-center gap-2'
                                    style={{ borderRadius: 'var(--button-border-radius, 0.5rem)' }}
                                >
                                    Deploy Now <ChevronRight width={16} height={16} />
                                </Link>
                                <ShimmerButton text='Docs & API' />
                            </div>
                        </div>
                    </motion.div>
                </div>
            </section>

            {/* TECHNICAL SPECS BAR */}
            <section className='bg-neutral-950 border-b border-neutral-900 py-8 relative z-20 shadow-xl'>
                <div className='max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-4'>
                    <SpecCard label='Processor' value={data.technical.cpu} icon={Cpu} />
                    <SpecCard label='Memory' value={data.technical.ram} icon={Server} />
                    <SpecCard label='Storage' value={data.technical.disk} icon={CircleCheck} />
                    <SpecCard label='Network' value={data.technical.uplink} icon={AntennaSignal} />
                </div>
            </section>

            {/* VISUAL SHOWCASE (GAMER VIBE) - Only for Game Hosting */}
            {isGameHosting && data.showcaseImages && (
                <section className='py-20 bg-neutral-900/20 overflow-hidden'>
                    <div className='max-w-7xl mx-auto px-6 mb-10 text-center'>
                        <h2 className='text-2xl font-bold uppercase tracking-widest mb-2'>Immersive Worlds</h2>
                        <p className='text-neutral-400'>Powered by Oasis Cloud infrastructure.</p>
                    </div>
                    {/* Masonry / Grid Collage */}
                    <div className='max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-4 h-[400px] md:h-[500px]'>
                        <div className='col-span-2 row-span-2 relative rounded-2xl overflow-hidden group'>
                            <img
                                src={data.showcaseImages[0]}
                                alt='Minecraft'
                                className='w-full h-full object-cover transition-transform duration-700 group-hover:scale-105'
                            />
                            <div className='absolute inset-0 bg-gradient-to-t from-black/80 to-transparent flex items-end p-6'>
                                <span className='text-white font-bold text-xl'>Infinite Creations</span>
                            </div>
                        </div>
                        <div className='relative rounded-2xl overflow-hidden group'>
                            <img
                                src={data.showcaseImages[1]}
                                alt='Rust'
                                className='w-full h-full object-cover transition-transform duration-700 group-hover:scale-105'
                            />
                            <div className='absolute inset-0 bg-gradient-to-t from-black/80 to-transparent flex items-end p-6'>
                                <span className='text-white font-bold'>Survival</span>
                            </div>
                        </div>
                        <div className='relative rounded-2xl overflow-hidden group'>
                            <img
                                src={data.showcaseImages[2]}
                                alt='GTA'
                                className='w-full h-full object-cover transition-transform duration-700 group-hover:scale-105'
                            />
                            <div className='absolute inset-0 bg-gradient-to-t from-black/80 to-transparent flex items-end p-6'>
                                <span className='text-white font-bold'>Roleplay</span>
                            </div>
                        </div>
                        <div className='col-span-2 relative rounded-2xl overflow-hidden group'>
                            <img
                                src={data.showcaseImages[3]}
                                alt='Terraria'
                                className='w-full h-full object-cover transition-transform duration-700 group-hover:scale-105'
                            />
                            <div className='absolute inset-0 bg-gradient-to-t from-black/80 to-transparent flex items-end p-6'>
                                <span className='text-white font-bold'>Adventure</span>
                            </div>
                        </div>
                    </div>
                </section>
            )}

            {/* GAME LIBRARY (SEARCHABLE) */}
            {data.supportedGames && <GameLibrary games={data.supportedGames} />}

            {/* USE CASES (For VPS/Non-Game) */}
            {data.useCases && (
                <section className='py-24 px-6 max-w-7xl mx-auto bg-neutral-900/10'>
                    <h2 className='text-3xl font-bold mb-12 text-center'>Perfect For Your Project</h2>
                    <div className='grid grid-cols-1 md:grid-cols-2 gap-8'>
                        {data.useCases.map((uc, i) => (
                            <div key={i} className='flex gap-6 p-6 border border-neutral-800 rounded-xl bg-neutral-950'>
                                <div className='text-brand shrink-0'>
                                    <Terminal width={32} height={32} />
                                </div>
                                <div>
                                    <h3 className='text-xl font-bold mb-2'>{uc.title}</h3>
                                    <p className='text-neutral-400'>{uc.desc}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {/* FEATURES GRID */}
            <section className='py-24 px-6 max-w-7xl mx-auto border-t border-neutral-900'>
                <div className='flex flex-col md:flex-row justify-between items-end mb-16'>
                    <div>
                        <h2 className='text-3xl font-bold mb-4'>The Oasis Standard</h2>
                        <p className='text-neutral-400 max-w-lg'>
                            We don&apos;t charge extra for the essentials. Every deployment includes our enterprise
                            standard suite.
                        </p>
                    </div>
                </div>

                <div className='grid grid-cols-1 md:grid-cols-2 gap-6'>
                    {data.features.map((feature, i) => (
                        <FeatureCard key={i} item={feature} index={i} />
                    ))}
                </div>
            </section>

            {/* SLA & INFRASTRUCTURE */}
            <section className='py-24 bg-neutral-900/30 border-y border-neutral-900 relative overflow-hidden'>
                <div className='absolute top-0 right-0 w-[500px] h-[500px] bg-brand/5 blur-[120px] rounded-full pointer-events-none' />

                <div className='max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-2 gap-16 items-center'>
                    <div>
                        <div className='inline-flex items-center gap-2 px-3 py-1 rounded-full bg-green-500/10 border border-green-500/20 text-green-500 text-xs font-bold uppercase tracking-wider mb-6'>
                            <div className='w-2 h-2 rounded-full bg-green-500 animate-pulse' />
                            Systems Operational
                        </div>
                        <h2 className='text-4xl font-bold mb-6'>Enterprise Reliability</h2>
                        <div className='space-y-8'>
                            <div>
                                <h3 className='text-xl font-bold text-white mb-2 flex items-center gap-2'>
                                    <Shield className='text-brand' /> Anti-DDoS Mitigation
                                </h3>
                                <p className='text-neutral-400'>
                                    Our network is scrubbed by Path.net with a 12Tbps capacity. Attacks are detected and
                                    mitigated in under 1 second, ensuring your players never disconnect.
                                </p>
                            </div>
                            <div>
                                <h3 className='text-xl font-bold text-white mb-2 flex items-center gap-2'>
                                    <Power className='text-brand' /> Power Redundancy
                                </h3>
                                <p className='text-neutral-400'>
                                    N+1 redundant UPS battery backups and onsite diesel generators ensure your server
                                    stays online even during a total datacenter power grid failure.
                                </p>
                            </div>
                            <div>
                                <h3 className='text-xl font-bold text-white mb-2 flex items-center gap-2'>
                                    <LifeRing className='text-brand' /> 24/7 Expert Support
                                </h3>
                                <p className='text-neutral-400'>
                                    Stuck? Our team of engineers is available 24/7 via Discord and Ticket. We don&apos;t
                                    use bots; you get real humans who know code.
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* SLA STATS */}
                    <div className='grid grid-cols-1 gap-6'>
                        <div className='bg-black border border-neutral-800 p-8 rounded-2xl relative overflow-hidden group hover:border-brand/30 transition-colors'>
                            <div className='absolute top-0 right-0 p-32 bg-brand/10 blur-[80px] rounded-full group-hover:bg-brand/20 transition-colors' />
                            <div className='relative z-10'>
                                <div className='text-6xl font-black text-white mb-2'>99.9%</div>
                                <div className='text-xl font-bold text-neutral-500 uppercase tracking-widest mb-4'>
                                    Uptime SLA
                                </div>
                                <p className='text-neutral-400 text-sm'>
                                    We financially guarantee our network availability. If we dip below 99.9%, you get
                                    credited automatically.
                                </p>
                            </div>
                        </div>

                        <div className='bg-neutral-900 border border-neutral-800 p-8 rounded-2xl flex items-center justify-between'>
                            <div>
                                <div className='text-3xl font-bold text-white flex items-center gap-2'>
                                    <Globe width={24} /> Prague, CZ
                                </div>
                                <div className='text-neutral-500 text-sm'>Primary Datacenter</div>
                            </div>
                            <div className='h-12 w-[1px] bg-neutral-800' />
                            <div>
                                <div className='text-3xl font-bold text-white flex items-center gap-2'>
                                    <LayoutHeader width={24} /> &lt; 15ms
                                </div>
                                <div className='text-neutral-500 text-sm'>Avg. EU Latency</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {/* CTA */}
            <section className='py-24 px-6 text-center'>
                <div className='max-w-3xl mx-auto'>
                    <h2 className='text-4xl font-bold mb-6'>Ready to play?</h2>
                    <p className='text-neutral-400 mb-10 text-lg'>
                        Start your journey with Oasis Cloud today. Your server is just 60 seconds away.
                    </p>
                    <Link to='/' className='inline-block'>
                        <motion.button
                            whileHover={{ scale: 1.05 }}
                            whileTap={{ scale: 0.95 }}
                            className='relative overflow-hidden bg-transparent hover:border-brand hover:border-[1.5px] px-12 py-5 font-bold text-lg text-white group'
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
                                Create Server Now <ChevronRight width={16} height={16} />
                            </span>
                            <div className='absolute inset-0 -translate-x-full group-hover:animate-[shimmer_1.5s_infinite] bg-gradient-to-r from-transparent via-white/20 to-transparent z-0' />
                        </motion.button>
                    </Link>
                </div>
            </section>

            {/* Footer */}
            <footer className='py-10 text-center text-neutral-600 text-sm border-t border-neutral-900 bg-neutral-950'>
                &copy; 2025 Oasis Cloud. Built for Gamers, by Gamers.
            </footer>
        </div>
    );
}
