import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';

import { type Ticket, getTickets } from '@/api/tickets';

const TicketsContainer = () => {
    const [tickets, setTickets] = useState<Ticket[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [filters, setFilters] = useState({
        status: '',
        category: '',
    });

    useEffect(() => {
        loadTickets();
    }, [filters]);

    const loadTickets = async () => {
        setLoading(true);
        setError(null);
        try {
            const data = await getTickets({
                status: filters.status || undefined,
                category: filters.category || undefined,
            });
            setTickets(data.data);
        } catch (err: any) {
            setError(err?.response?.data?.errors?.[0]?.detail || 'Failed to load tickets');
        } finally {
            setLoading(false);
        }
    };

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

    const getCategoryBadge = (category: string) => {
        const categories: Record<string, string> = {
            billing: 'Billing',
            technical: 'Technical',
            general: 'General',
            other: 'Other',
        };
        return categories[category] || category;
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
            <div className='mb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4'>
                <h1 className='text-2xl sm:text-3xl font-extrabold leading-[98%] tracking-[-0.02em] sm:tracking-[-0.06em] break-words'>
                    My Tickets
                </h1>
                <Link
                    to='/support'
                    className='inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-semibold text-white transition-all duration-200 hover:scale-105 hover:shadow-lg active:scale-100'
                    style={{ backgroundColor: 'var(--color-brand)' }}
                >
                    <svg className='w-5 h-5' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path strokeLinecap='round' strokeLinejoin='round' strokeWidth={2} d='M12 4v16m8-8H4' />
                    </svg>
                    <span>Create New Ticket</span>
                </Link>
            </div>

            {/* Filters */}
            <div className='mb-6 rounded-xl border border-white/10 bg-gradient-to-br from-[#ffffff05] to-[#ffffff02] p-4'>
                <div className='grid grid-cols-1 sm:grid-cols-2 gap-4'>
                    <div>
                        <label className='block text-sm font-medium text-white mb-2'>Status</label>
                        <select
                            value={filters.status}
                            onChange={(e) => setFilters({ ...filters, status: e.target.value })}
                            className='w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                        >
                            <option value=''>All Statuses</option>
                            <option value='open'>Open</option>
                            <option value='in_progress'>In Progress</option>
                            <option value='resolved'>Resolved</option>
                            <option value='closed'>Closed</option>
                        </select>
                    </div>
                    <div>
                        <label className='block text-sm font-medium text-white mb-2'>Category</label>
                        <select
                            value={filters.category}
                            onChange={(e) => setFilters({ ...filters, category: e.target.value })}
                            className='w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                        >
                            <option value=''>All Categories</option>
                            <option value='billing'>Billing</option>
                            <option value='technical'>Technical</option>
                            <option value='general'>General</option>
                            <option value='other'>Other</option>
                        </select>
                    </div>
                </div>
            </div>

            {/* Tickets List */}
            {loading ? (
                <div className='text-center py-12'>
                    <div className='inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-white'></div>
                    <p className='mt-4 text-zinc-400'>Loading tickets...</p>
                </div>
            ) : error ? (
                <div className='rounded-xl border border-red-500/50 bg-red-500/20 p-6 text-red-300'>{error}</div>
            ) : tickets.length === 0 ? (
                <div className='rounded-xl border border-white/10 bg-gradient-to-br from-[#ffffff05] to-[#ffffff02] p-12 text-center'>
                    <p className='text-zinc-400 mb-4'>No tickets found.</p>
                    <Link
                        to='/support'
                        className='inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-semibold text-white transition-all duration-200 hover:scale-105'
                        style={{ backgroundColor: 'var(--color-brand)' }}
                    >
                        Create Your First Ticket
                    </Link>
                </div>
            ) : (
                <div className='space-y-4'>
                    {tickets.map((ticket) => (
                        <Link
                            key={ticket.attributes.id}
                            to={`/support/tickets/${ticket.attributes.id}`}
                            className='block rounded-xl border border-white/10 bg-gradient-to-br from-[#ffffff05] to-[#ffffff02] p-6 hover:border-white/20 transition-all duration-200'
                        >
                            <div className='flex flex-col sm:flex-row sm:items-center justify-between gap-4'>
                                <div className='flex-1'>
                                    <div className='flex items-center gap-3 mb-2'>
                                        <h3 className='text-lg font-bold text-white'>#{ticket.attributes.id}</h3>
                                        <h3 className='text-lg font-bold text-white'>{ticket.attributes.subject}</h3>
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
                                            {getCategoryBadge(ticket.attributes.category)}
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
                    ))}
                </div>
            )}
        </div>
    );
};

export default TicketsContainer;
