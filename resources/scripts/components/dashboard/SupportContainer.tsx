import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';

import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/elements/Accordion';

import { cn } from '@/lib/utils';

import getSubscriptions from '@/api/billing/getSubscriptions';
import { Subscription } from '@/api/billing/getSubscriptions';
import getServers from '@/api/getServers';
import { Server } from '@/api/server/getServer';
import { type CreateTicketData, type Ticket, createTicket, getTickets } from '@/api/tickets';

type Faq = {
    q: string;
    a: React.ReactNode;
};

type Tutorial = {
    title: string;
    description: string;
    image: string;
};

const DiscordLogo = ({ className }: { className?: string }) => (
    <svg className={className} viewBox='0 0 71 55' fill='none' xmlns='http://www.w3.org/2000/svg'>
        <path
            d='M60.1045 4.8978C55.5792 2.8214 50.7265 1.2916 45.6527 0.41542C45.5603 0.39851 45.468 0.440769 45.4204 0.525289C44.7963 1.6353 44.105 3.0834 43.6209 4.2216C38.1637 3.4046 32.7345 3.4046 27.3892 4.2216C26.905 3.0581 26.1886 1.6353 25.5617 0.525289C25.5141 0.443589 25.4218 0.40133 25.3294 0.41542C20.2584 1.2888 15.4057 2.8186 10.8776 4.8978C10.8384 4.9147 10.8048 4.9429 10.7825 4.9795C1.57795 18.7309 -0.943561 32.1443 0.293408 45.3914C0.299005 45.4562 0.335386 45.5182 0.385761 45.5576C6.45866 50.0174 12.3413 52.7249 18.1147 54.5195C18.2071 54.5477 18.305 54.5139 18.3638 54.4378C19.7295 52.5728 20.9469 50.6063 21.9907 48.5383C22.0523 48.4172 21.9935 48.2735 21.8676 48.2256C19.9366 47.4931 18.0979 46.6 16.3292 45.5858C16.1893 45.5041 16.1781 45.304 16.3068 45.2082C16.679 44.9293 17.0513 44.6391 17.4067 44.3461C17.471 44.2926 17.5606 44.2813 17.6362 44.3151C29.2558 49.6202 41.8354 49.6202 53.3179 44.3151C53.3935 44.2785 53.4831 44.2898 53.5502 44.3433C53.9057 44.6363 54.2779 44.9293 54.6529 45.2082C54.7816 45.304 54.7732 45.5041 54.6333 45.5858C52.8646 46.6197 51.0259 47.4931 49.0921 48.2228C48.9662 48.2707 48.9102 48.4172 48.9718 48.5383C50.038 50.6034 51.2554 52.5699 52.5959 54.435C52.6519 54.5139 52.7526 54.5477 52.845 54.5195C58.6464 52.7249 64.529 50.0174 70.6019 45.5576C70.6551 45.5182 70.6887 45.459 70.6943 45.3942C72.1747 30.0791 68.2147 16.7757 60.1968 4.9823C60.1772 4.9429 60.1437 4.9147 60.1045 4.8978ZM23.7259 37.3253C20.2276 37.3253 17.3451 34.1136 17.3451 30.1693C17.3451 26.225 20.1717 23.0133 23.7259 23.0133C27.308 23.0133 30.1626 26.2532 30.1066 30.1693C30.1066 34.1136 27.28 37.3253 23.7259 37.3253ZM47.3178 37.3253C43.8196 37.3253 40.9371 34.1136 40.9371 30.1693C40.9371 26.225 43.7636 23.0133 47.3178 23.0133C50.9 23.0133 53.7545 26.2532 53.6986 30.1693C53.6986 34.1136 50.9 37.3253 47.3178 37.3253Z'
            fill='currentColor'
        />
    </svg>
);

const faqs: Faq[] = [
    {
        q: 'How do I create a server?',
        a: 'Go to Your Servers > Create Server, choose an egg, location, and resources, then confirm.',
    },
    {
        q: "Why can't my server start?",
        a: 'Common causes: insufficient memory/disk, missing startup variables, or the node is under maintenance. Check the console and allocations first.',
    },
    {
        q: 'How do I enable 2FA?',
        a: 'Open Settings > Multi-Factor Authentication. Scan the QR code with your authenticator app and enter the code.',
    },
    {
        q: 'How does billing work?',
        a: 'Open Billing to view balance and invoices. Add funds there. Some charges may be prorated depending on your plan.',
    },
    {
        q: 'How do I upload files to my server?',
        a: 'Use the File Manager in your server dashboard. You can drag and drop files, create folders, or use the upload button.',
    },
    {
        q: 'What should I do if my server is offline?',
        a: 'Check the server console for errors, verify your startup command is correct, and ensure you have sufficient resources allocated.',
    },
];

const tutorials: Tutorial[] = [
    {
        title: 'Getting Started with Your First Server',
        description: 'Learn how to create and configure your first game server from scratch.',
        image: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800&h=400&fit=crop',
    },
    {
        title: 'Setting Up Server Backups',
        description: 'Protect your server data with automated backup configurations.',
        image: 'https://images.unsplash.com/photo-1614624532983-4ce03382d63d?w=800&h=400&fit=crop',
    },
    {
        title: 'Managing Server Resources',
        description: 'Optimize your server performance by managing resources effectively.',
        image: 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=800&h=400&fit=crop',
    },
];

const SupportContainer = () => {
    const [isCreatingTicket, setIsCreatingTicket] = useState(false);
    const [showTicketForm, setShowTicketForm] = useState(false);
    const [servers, setServers] = useState<Server[]>([]);
    const [subscriptions, setSubscriptions] = useState<Subscription[]>([]);
    const [tickets, setTickets] = useState<Ticket[]>([]);
    const [ticketsLoading, setTicketsLoading] = useState(true);
    const [formData, setFormData] = useState<CreateTicketData>({
        subject: '',
        description: '',
        category: 'general',
        priority: 'medium',
        server_id: null,
        subscription_id: null,
    });
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});
    const [submitError, setSubmitError] = useState<string | null>(null);
    const [submitSuccess, setSubmitSuccess] = useState(false);

    useEffect(() => {
        // Fetch servers and subscriptions for the form
        Promise.all([getServers({}), getSubscriptions()])
            .then(([serversData, subscriptionsData]) => {
                setServers(serversData.items);
                setSubscriptions(subscriptionsData);
            })
            .catch((error) => {
                console.error('Failed to load servers/subscriptions:', error);
            });

        // Load tickets
        loadTickets();
    }, []);

    const loadTickets = async () => {
        setTicketsLoading(true);
        try {
            const data = await getTickets({ per_page: 10 });
            setTickets(data.data);
        } catch (error) {
            console.error('Failed to load tickets:', error);
        } finally {
            setTicketsLoading(false);
        }
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setFormErrors({});
        setSubmitError(null);
        setSubmitSuccess(false);

        // Basic validation
        if (!formData.subject.trim()) {
            setFormErrors({ subject: 'Subject is required' });
            return;
        }
        if (!formData.description.trim()) {
            setFormErrors({ description: 'Description is required' });
            return;
        }

        setIsCreatingTicket(true);

        try {
            await createTicket({
                ...formData,
                server_id: formData.server_id || undefined,
                subscription_id: formData.subscription_id || undefined,
            });

            setSubmitSuccess(true);
            setFormData({
                subject: '',
                description: '',
                category: 'general',
                priority: 'medium',
                server_id: null,
                subscription_id: null,
            });
            setShowTicketForm(false);

            // Reload tickets to show the new one
            await loadTickets();

            // Reset success message after 3 seconds
            setTimeout(() => setSubmitSuccess(false), 3000);
        } catch (error: any) {
            const errorMessage =
                error?.response?.data?.errors?.[0]?.detail || 'Failed to create ticket. Please try again.';
            setSubmitError(errorMessage);
        } finally {
            setIsCreatingTicket(false);
        }
    };

    return (
        <div
            className='m-15 transform-gpu skeleton-anim-2'
            style={{
                animationDelay: '50ms',
                animationTimingFunction:
                    'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
            }}
        >
            {/* Create Ticket Section */}
            <div
                className='mb-12 transform-gpu skeleton-anim-2'
                style={{
                    animationDelay: '25ms',
                    animationTimingFunction:
                        'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                }}
            >
                <div className='rounded-xl border border-white/10 bg-gradient-to-br from-[#ffffff05] to-[#ffffff02] p-6 sm:p-8'>
                    <div className='flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6 mb-6'>
                        <div className='flex-1'>
                            <h2 className='text-xl sm:text-2xl font-bold text-white mb-2'>Create Support Ticket</h2>
                            <p className='text-sm sm:text-base text-zinc-300 leading-relaxed'>
                                Need help? Create a support ticket and our team will assist you. You can link your
                                ticket to a specific server or subscription.
                            </p>
                        </div>
                        {!showTicketForm && (
                            <button
                                onClick={() => setShowTicketForm(true)}
                                className='inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-semibold text-white transition-all duration-200 hover:scale-105 hover:shadow-lg active:scale-100'
                                style={{ backgroundColor: 'var(--color-brand)' }}
                            >
                                <svg className='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                    <path
                                        strokeLinecap='round'
                                        strokeLinejoin='round'
                                        strokeWidth={2}
                                        d='M12 4v16m8-8H4'
                                    />
                                </svg>
                                <span>Create Ticket</span>
                            </button>
                        )}
                    </div>

                    {showTicketForm && (
                        <form onSubmit={handleSubmit} className='space-y-4'>
                            {submitSuccess && (
                                <div className='rounded-lg bg-green-500/20 border border-green-500/50 p-4 text-green-300 text-sm'>
                                    Ticket created successfully! Our team will respond soon.
                                </div>
                            )}

                            {submitError && (
                                <div className='rounded-lg bg-red-500/20 border border-red-500/50 p-4 text-red-300 text-sm'>
                                    {submitError}
                                </div>
                            )}

                            <div>
                                <label htmlFor='subject' className='block text-sm font-medium text-white mb-2'>
                                    Subject <span className='text-red-400'>*</span>
                                </label>
                                <input
                                    type='text'
                                    id='subject'
                                    value={formData.subject}
                                    onChange={(e) => setFormData({ ...formData, subject: e.target.value })}
                                    className='w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                                    placeholder='Brief description of your issue'
                                    required
                                />
                                {formErrors.subject && (
                                    <p className='mt-1 text-sm text-red-400'>{formErrors.subject}</p>
                                )}
                            </div>

                            <div>
                                <label htmlFor='description' className='block text-sm font-medium text-white mb-2'>
                                    Description <span className='text-red-400'>*</span>
                                </label>
                                <textarea
                                    id='description'
                                    value={formData.description}
                                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                    rows={5}
                                    className='w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent resize-none'
                                    placeholder='Please provide detailed information about your issue...'
                                    required
                                />
                                {formErrors.description && (
                                    <p className='mt-1 text-sm text-red-400'>{formErrors.description}</p>
                                )}
                            </div>

                            <div className='grid grid-cols-1 sm:grid-cols-2 gap-4'>
                                <div>
                                    <label htmlFor='category' className='block text-sm font-medium text-white mb-2'>
                                        Category <span className='text-red-400'>*</span>
                                    </label>
                                    <select
                                        id='category'
                                        value={formData.category}
                                        onChange={(e) => setFormData({ ...formData, category: e.target.value as any })}
                                        className='w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                                        required
                                    >
                                        <option value='billing'>Billing</option>
                                        <option value='technical'>Technical</option>
                                        <option value='general'>General</option>
                                        <option value='other'>Other</option>
                                    </select>
                                </div>

                                <div>
                                    <label htmlFor='priority' className='block text-sm font-medium text-white mb-2'>
                                        Priority
                                    </label>
                                    <select
                                        id='priority'
                                        value={formData.priority}
                                        onChange={(e) => setFormData({ ...formData, priority: e.target.value as any })}
                                        className='w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                                    >
                                        <option value='low'>Low</option>
                                        <option value='medium'>Medium</option>
                                        <option value='high'>High</option>
                                        <option value='urgent'>Urgent</option>
                                    </select>
                                </div>
                            </div>

                            <div className='grid grid-cols-1 sm:grid-cols-2 gap-4'>
                                <div>
                                    <label htmlFor='server_id' className='block text-sm font-medium text-white mb-2'>
                                        Related Server (Optional)
                                    </label>
                                    <select
                                        id='server_id'
                                        value={formData.server_id || ''}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                server_id: e.target.value ? parseInt(e.target.value) : null,
                                            })
                                        }
                                        className='w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                                    >
                                        <option value=''>None</option>
                                        {servers.map((server) => (
                                            <option key={server.uuid} value={server.internalId}>
                                                {server.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label
                                        htmlFor='subscription_id'
                                        className='block text-sm font-medium text-white mb-2'
                                    >
                                        Related Subscription (Optional)
                                    </label>
                                    <select
                                        id='subscription_id'
                                        value={formData.subscription_id || ''}
                                        onChange={(e) =>
                                            setFormData({
                                                ...formData,
                                                subscription_id: e.target.value ? parseInt(e.target.value) : null,
                                            })
                                        }
                                        className='w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                                    >
                                        <option value=''>None</option>
                                        {subscriptions.map((sub) => (
                                            <option key={sub.attributes.id} value={sub.attributes.id}>
                                                {sub.attributes.plan_name} - {sub.attributes.server_name || 'No Server'}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className='flex gap-4'>
                                <button
                                    type='submit'
                                    disabled={isCreatingTicket}
                                    className='inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-semibold text-white transition-all duration-200 hover:scale-105 hover:shadow-lg active:scale-100 disabled:opacity-50 disabled:cursor-not-allowed'
                                    style={{ backgroundColor: 'var(--color-brand)' }}
                                >
                                    {isCreatingTicket ? 'Creating...' : 'Create Ticket'}
                                </button>
                                <button
                                    type='button'
                                    onClick={() => {
                                        setShowTicketForm(false);
                                        setFormErrors({});
                                        setSubmitError(null);
                                        setSubmitSuccess(false);
                                    }}
                                    className='inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-semibold text-zinc-300 bg-white/5 border border-white/10 hover:bg-white/10 transition-all duration-200'
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            </div>

            {/* My Tickets Section */}
            <div
                className='mb-12 transform-gpu skeleton-anim-2'
                style={{
                    animationDelay: '75ms',
                    animationTimingFunction:
                        'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                }}
            >
                <h2 className='text-2xl sm:text-3xl font-extrabold leading-[98%] tracking-[-0.02em] sm:tracking-[-0.06em] break-words mb-6'>
                    My Tickets
                </h2>
                {ticketsLoading ? (
                    <div className='text-center py-12'>
                        <div className='inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-white'></div>
                        <p className='mt-4 text-zinc-400'>Loading tickets...</p>
                    </div>
                ) : tickets.length === 0 ? (
                    <div className='rounded-xl border border-white/10 bg-gradient-to-br from-[#ffffff05] to-[#ffffff02] p-12 text-center'>
                        <p className='text-zinc-400'>You haven't created any tickets yet.</p>
                    </div>
                ) : (
                    <div className='space-y-4'>
                        {tickets.map((ticket) => {
                            const getStatusBadge = (status: string) => {
                                const styles = {
                                    open: 'bg-green-500/20 text-green-300 border-green-500/50',
                                    in_progress: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/50',
                                    resolved: 'bg-blue-500/20 text-blue-300 border-blue-500/50',
                                    closed: 'bg-zinc-500/20 text-zinc-300 border-zinc-500/50',
                                };
                                return styles[status as keyof typeof styles] || styles.closed;
                            };

                            const getPriorityBadge = (priority: string) => {
                                const styles = {
                                    urgent: 'bg-red-500/20 text-red-300 border-red-500/50',
                                    high: 'bg-orange-500/20 text-orange-300 border-orange-500/50',
                                    medium: 'bg-blue-500/20 text-blue-300 border-blue-500/50',
                                    low: 'bg-zinc-500/20 text-zinc-300 border-zinc-500/50',
                                };
                                return styles[priority as keyof typeof styles] || styles.medium;
                            };

                            return (
                                <Link
                                    key={ticket.attributes.id}
                                    to={`/support/tickets/${ticket.attributes.id}`}
                                    className='block rounded-xl border border-white/10 bg-gradient-to-br from-[#ffffff05] to-[#ffffff02] p-6 hover:border-white/20 transition-all duration-200'
                                >
                                    <div className='flex flex-col sm:flex-row sm:items-center justify-between gap-4'>
                                        <div className='flex-1'>
                                            <div className='flex items-center gap-3 mb-2'>
                                                <h3 className='text-lg font-bold text-white'>
                                                    #{ticket.attributes.id}
                                                </h3>
                                                <h3 className='text-lg font-bold text-white'>
                                                    {ticket.attributes.subject}
                                                </h3>
                                            </div>
                                            <p className='text-sm text-zinc-400 mb-3 line-clamp-2'>
                                                {ticket.attributes.description}
                                            </p>
                                            <div className='flex flex-wrap items-center gap-2'>
                                                <span
                                                    className={`px-2 py-1 rounded text-xs font-medium border ${getStatusBadge(
                                                        ticket.attributes.status,
                                                    )}`}
                                                >
                                                    {ticket.attributes.status.replace('_', ' ').toUpperCase()}
                                                </span>
                                                <span
                                                    className={`px-2 py-1 rounded text-xs font-medium border ${getPriorityBadge(
                                                        ticket.attributes.priority,
                                                    )}`}
                                                >
                                                    {ticket.attributes.priority.toUpperCase()}
                                                </span>
                                                <span className='px-2 py-1 rounded text-xs font-medium bg-blue-500/20 text-blue-300 border border-blue-500/50'>
                                                    {ticket.attributes.category.charAt(0).toUpperCase() +
                                                        ticket.attributes.category.slice(1)}
                                                </span>
                                            </div>
                                        </div>
                                        <div className='text-right'>
                                            <p className='text-sm text-zinc-400'>
                                                {new Date(ticket.attributes.created_at).toLocaleDateString()}
                                            </p>
                                            <svg
                                                className='w-5 h-5 text-zinc-400 mt-2 ml-auto'
                                                fill='none'
                                                stroke='currentColor'
                                                viewBox='0 0 24 24'
                                            >
                                                <path
                                                    strokeLinecap='round'
                                                    strokeLinejoin='round'
                                                    strokeWidth={2}
                                                    d='M9 5l7 7-7 7'
                                                />
                                            </svg>
                                        </div>
                                    </div>
                                </Link>
                            );
                        })}
                        {tickets.length >= 10 && (
                            <div className='text-center mt-6'>
                                <Link
                                    to='/support/tickets'
                                    className='inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-semibold text-white transition-all duration-200 hover:scale-105'
                                    style={{ backgroundColor: 'var(--color-brand)' }}
                                >
                                    View All Tickets
                                </Link>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Discord Support Section */}
            <div
                className='mb-12 transform-gpu skeleton-anim-2'
                style={{
                    animationDelay: '75ms',
                    animationTimingFunction:
                        'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                }}
            >
                <div className='rounded-xl border border-white/10 bg-gradient-to-br from-[#ffffff05] to-[#ffffff02] p-6 sm:p-8'>
                    <div className='flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6'>
                        <div className='flex-1'>
                            <h2 className='text-xl sm:text-2xl font-bold text-white mb-2'>Need Help from Our Team?</h2>
                            <p className='text-sm sm:text-base text-zinc-300 leading-relaxed'>
                                If you need support from one of our technicians or sales representatives, join our
                                Discord server. Our team is ready to assist you with any questions or issues you may
                                have.
                            </p>
                        </div>
                        <a
                            href='https://discord.gg/UhuYKKK2uM'
                            target='_blank'
                            rel='noopener noreferrer'
                            className='inline-flex items-center justify-center gap-2.5 px-6 py-3 rounded-lg font-semibold text-white transition-all duration-200 hover:scale-105 hover:shadow-lg hover:shadow-[#5865F2]/20 active:scale-100'
                            style={{ backgroundColor: '#5865F2' }}
                        >
                            <DiscordLogo className='w-5 h-5 flex-shrink-0' />
                            <span>Join Discord</span>
                        </a>
                    </div>
                </div>
            </div>

            {/* Tutorials Section */}
            <div
                className='mb-12 transform-gpu skeleton-anim-2'
                style={{
                    animationDelay: '100ms',
                    animationTimingFunction:
                        'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                }}
            >
                <h2 className='text-2xl sm:text-3xl font-extrabold leading-[98%] tracking-[-0.02em] sm:tracking-[-0.06em] break-words mb-6'>
                    Tutorials
                </h2>
                <div className='grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4'>
                    {tutorials.map((tutorial, index) => (
                        <div
                            key={index}
                            className='h-[500px] rounded-xl border border-white/10 bg-gradient-to-br from-[#ffffff05] to-[#ffffff02] overflow-hidden hover:border-white/20 transition-all duration-200 cursor-pointer group flex flex-col'
                        >
                            {/* Banner Image */}
                            <div className='relative w-full h-48 bg-gradient-to-br from-[#5865F2]/20 to-[#5865F2]/5 overflow-hidden flex-shrink-0'>
                                <img
                                    src={tutorial.image}
                                    alt={tutorial.title}
                                    className='w-full h-full object-cover opacity-60 group-hover:opacity-80 transition-opacity duration-200'
                                />
                                <div className='absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent' />
                            </div>

                            {/* Content */}
                            <div className='p-6 flex flex-col flex-1 relative'>
                                <h3 className='text-xl sm:text-2xl font-bold text-white mb-2'>{tutorial.title}</h3>
                                <p className='text-sm text-zinc-400 mb-auto'>{tutorial.description}</p>

                                {/* Right Arrow in Bottom Right */}
                                <div className='mt-auto flex justify-end'>
                                    <svg
                                        className='w-6 h-6 text-white/60 group-hover:text-white group-hover:translate-x-1 transition-all duration-200'
                                        fill='none'
                                        stroke='currentColor'
                                        viewBox='0 0 24 24'
                                    >
                                        <path
                                            strokeLinecap='round'
                                            strokeLinejoin='round'
                                            strokeWidth={2}
                                            d='M9 5l7 7-7 7'
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* FAQ Section */}
            <div
                className='transform-gpu skeleton-anim-2'
                style={{
                    animationDelay: '125ms',
                    animationTimingFunction:
                        'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                }}
            >
                <h2 className='text-2xl sm:text-3xl font-extrabold leading-[98%] tracking-[-0.02em] sm:tracking-[-0.06em] break-words mb-6'>
                    Frequently Asked Questions
                </h2>
                <div className='rounded-xl border border-white/10 bg-gradient-to-br from-[#ffffff05] to-[#ffffff02] overflow-hidden'>
                    <Accordion type='single' collapsible className='flex flex-col'>
                        {faqs.map((item, i) => (
                            <AccordionItem
                                key={i}
                                value={`item-${i}`}
                                className={cn('border-white/10 last:border-b-0', i === 0 && 'border-t-0')}
                            >
                                <AccordionTrigger className='px-6 py-4 hover:no-underline hover:bg-white/5 transition-colors'>
                                    <span className='text-white text-base font-medium text-left'>{item.q}</span>
                                </AccordionTrigger>
                                <AccordionContent className='px-6 pb-4 text-zinc-300 text-sm leading-relaxed'>
                                    {item.a}
                                </AccordionContent>
                            </AccordionItem>
                        ))}
                    </Accordion>
                </div>
                <div className='mt-6 text-center'>
                    <p className='text-sm text-zinc-400'>
                        For more information, visit our{' '}
                        <a href='/docs' className='text-[#5865F2] hover:text-[#5865F2]/80 underline transition-colors'>
                            documentation
                        </a>
                        .
                    </p>
                </div>
            </div>
        </div>
    );
};

export default SupportContainer;
