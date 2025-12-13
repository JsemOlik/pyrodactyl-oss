import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';

import { type TicketReply, createReply, deleteTicket, getTicket, resolveTicket } from '@/api/tickets';
import { Ticket } from '@/api/tickets/getTickets';

const TicketDetailContainer = () => {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const [ticket, setTicket] = useState<Ticket | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [replyMessage, setReplyMessage] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isResolving, setIsResolving] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    useEffect(() => {
        if (id) {
            loadTicket();
        }
    }, [id]);

    const loadTicket = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await getTicket(parseInt(id!));
            // Response is JSON:API format: { data: {...}, included: [...] }
            setTicket(response as any);
        } catch (err: any) {
            setError(err?.response?.data?.errors?.[0]?.detail || 'Failed to load ticket');
        } finally {
            setLoading(false);
        }
    };

    const handleReply = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!replyMessage.trim() || !id) return;

        setIsSubmitting(true);
        try {
            await createReply(parseInt(id), { message: replyMessage });
            setReplyMessage('');
            await loadTicket(); // Reload to get new reply
        } catch (err: any) {
            setError(err?.response?.data?.errors?.[0]?.detail || 'Failed to add reply');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleResolve = async () => {
        if (!id) return;

        setIsResolving(true);
        try {
            await resolveTicket(parseInt(id));
            await loadTicket();
        } catch (err: any) {
            setError(err?.response?.data?.errors?.[0]?.detail || 'Failed to resolve ticket');
        } finally {
            setIsResolving(false);
        }
    };

    const handleDelete = async () => {
        if (!id || !confirm('Are you sure you want to delete this ticket? This action cannot be undone.')) {
            return;
        }

        setIsDeleting(true);
        try {
            await deleteTicket(parseInt(id));
            navigate('/support/tickets');
        } catch (err: any) {
            setError(err?.response?.data?.errors?.[0]?.detail || 'Failed to delete ticket');
            setIsDeleting(false);
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

    if (loading) {
        return (
            <div className='m-15 text-center py-12'>
                <div className='inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-white'></div>
                <p className='mt-4 text-zinc-400'>Loading ticket...</p>
            </div>
        );
    }

    if (error && !ticket) {
        return <div className='m-15 rounded-xl border border-red-500/50 bg-red-500/20 p-6 text-red-300'>{error}</div>;
    }

    // Extract ticket data and replies from API response
    // JSON:API format: { data: { type, id, attributes, relationships }, included: [...] }
    const ticketData = ticket as any;
    const mainData = ticketData?.data || ticketData;

    if (!mainData || !mainData.attributes) {
        return (
            <div className='m-15 rounded-xl border border-white/10 bg-gradient-to-br from-[#ffffff05] to-[#ffffff02] p-12 text-center'>
                <p className='text-zinc-400'>Ticket not found.</p>
                <Link to='/support/tickets' className='text-brand hover:underline mt-4 inline-block'>
                    Back to Tickets
                </Link>
            </div>
        );
    }

    const ticketAttributes = mainData.attributes;
    const included = ticketData.included || [];

    // Get reply IDs from relationships
    const replyReferences = mainData.relationships?.replies?.data || [];
    const replyIds = replyReferences.map((ref: any) => String(ref.id));

    // Match reply IDs with included items
    let allReplies: any[] = [];
    if (replyIds.length > 0 && included.length > 0) {
        allReplies = included
            .filter((item: any) => item.type === 'ticket_reply' && replyIds.includes(String(item.id)))
            .map((item: any) => ({
                id: item.id,
                attributes: item.attributes,
                relationships: item.relationships,
            }));
    }

    // Sort replies by created_at
    allReplies.sort((a, b) => {
        const aDate = new Date(a.attributes?.created_at || a.created_at || 0);
        const bDate = new Date(b.attributes?.created_at || b.created_at || 0);
        return aDate.getTime() - bDate.getTime();
    });

    return (
        <div
            className='m-15 transform-gpu skeleton-anim-2'
            style={{
                animationDelay: '50ms',
                animationTimingFunction:
                    'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
            }}
        >
            <div className='mb-6 flex items-center gap-4'>
                <Link to='/support/tickets' className='text-zinc-400 hover:text-white transition-colors'>
                    <svg className='w-6 h-6' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path strokeLinecap='round' strokeLinejoin='round' strokeWidth={2} d='M15 19l-7-7 7-7' />
                    </svg>
                </Link>
                <h1 className='text-2xl sm:text-3xl font-extrabold leading-[98%] tracking-[-0.02em] sm:tracking-[-0.06em] break-words'>
                    Ticket #{ticketAttributes.id}
                </h1>
            </div>

            {error && (
                <div className='mb-6 rounded-xl border border-red-500/50 bg-red-500/20 p-4 text-red-300'>{error}</div>
            )}

            {/* Ticket Header */}
            <div className='mb-6 rounded-xl border border-white/10 bg-gradient-to-br from-[#ffffff05] to-[#ffffff02] p-6'>
                <div className='flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4'>
                    <h2 className='text-xl font-bold text-white'>{ticketAttributes.subject}</h2>
                    <div className='flex flex-wrap items-center gap-2'>
                        <span
                            className={`px-3 py-1 rounded text-sm font-medium border ${getStatusBadge(
                                ticketAttributes.status,
                            )}`}
                        >
                            {ticketAttributes.status.replace('_', ' ').toUpperCase()}
                        </span>
                        <span
                            className={`px-3 py-1 rounded text-sm font-medium border ${getPriorityBadge(
                                ticketAttributes.priority,
                            )}`}
                        >
                            {ticketAttributes.priority.toUpperCase()}
                        </span>
                    </div>
                </div>
                <div className='mb-4'>
                    <p className='text-zinc-300 whitespace-pre-wrap'>{ticketAttributes.description}</p>
                </div>
                <div className='flex flex-wrap items-center gap-4 text-sm text-zinc-400'>
                    <span>Category: {ticketAttributes.category}</span>
                    <span>Created: {new Date(ticketAttributes.created_at).toLocaleString()}</span>
                    {ticketAttributes.resolved_at && (
                        <span>Resolved: {new Date(ticketAttributes.resolved_at).toLocaleString()}</span>
                    )}
                </div>
            </div>

            {/* Replies */}
            <div className='mb-6 rounded-xl border border-white/10 bg-gradient-to-br from-[#ffffff05] to-[#ffffff02] p-6'>
                <h3 className='text-lg font-bold text-white mb-4'>Replies</h3>
                {allReplies.length === 0 ? (
                    <p className='text-zinc-400'>No replies yet.</p>
                ) : (
                    <div className='space-y-4'>
                        {allReplies.map((reply: any, index: number) => {
                            const replyData = reply.attributes || reply;

                            // Extract user data from included array
                            let userData: any = {};

                            if (reply.relationships?.user?.data) {
                                const userId = String(reply.relationships.user.data.id);
                                const userFromIncluded = included.find(
                                    (item: any) => item.type === 'user' && String(item.id) === userId,
                                );
                                userData = userFromIncluded?.attributes || {};
                            } else if (replyData.user?.attributes) {
                                userData = replyData.user.attributes;
                            } else if (replyData.user) {
                                // User might be directly in attributes
                                const userId = String(replyData.user);
                                const userFromIncluded = included.find(
                                    (item: any) => item.type === 'user' && String(item.id) === userId,
                                );
                                userData = userFromIncluded?.attributes || {};
                            }

                            return (
                                <div
                                    key={reply.id || replyData.id || index}
                                    className='border border-white/10 rounded-lg p-4 bg-white/5'
                                >
                                    <div className='flex items-center justify-between mb-2'>
                                        <span className='text-white font-medium'>
                                            {userData.username || userData.email || 'User'}
                                        </span>
                                        <span className='text-sm text-zinc-400'>
                                            {new Date(replyData.created_at).toLocaleString()}
                                        </span>
                                    </div>
                                    <p className='text-zinc-300 whitespace-pre-wrap'>{replyData.message}</p>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Reply Form */}
            {ticketAttributes.status !== 'resolved' && ticketAttributes.status !== 'closed' && (
                <div className='mb-6 rounded-xl border border-white/10 bg-gradient-to-br from-[#ffffff05] to-[#ffffff02] p-6'>
                    <h3 className='text-lg font-bold text-white mb-4'>Add Reply</h3>
                    <form onSubmit={handleReply}>
                        <textarea
                            value={replyMessage}
                            onChange={(e) => setReplyMessage(e.target.value)}
                            rows={5}
                            className='w-full px-4 py-2 rounded-lg bg-white/5 border border-white/10 text-white placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent resize-none mb-4'
                            placeholder='Type your reply here...'
                            required
                        />
                        <button
                            type='submit'
                            disabled={isSubmitting || !replyMessage.trim()}
                            className='inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-semibold text-white transition-all duration-200 hover:scale-105 hover:shadow-lg active:scale-100 disabled:opacity-50 disabled:cursor-not-allowed'
                            style={{ backgroundColor: 'var(--color-brand)' }}
                        >
                            {isSubmitting ? 'Sending...' : 'Send Reply'}
                        </button>
                    </form>
                </div>
            )}

            {/* Actions */}
            <div className='flex flex-wrap gap-4'>
                {!ticketAttributes.resolved_at && (
                    <button
                        onClick={handleResolve}
                        disabled={isResolving}
                        className='inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-semibold text-white bg-green-500/20 border border-green-500/50 hover:bg-green-500/30 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed'
                    >
                        {isResolving ? 'Resolving...' : 'Mark as Resolved'}
                    </button>
                )}
                <button
                    onClick={handleDelete}
                    disabled={isDeleting}
                    className='inline-flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-semibold text-white bg-red-500/20 border border-red-500/50 hover:bg-red-500/30 transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed'
                >
                    {isDeleting ? 'Deleting...' : 'Delete Ticket'}
                </button>
            </div>
        </div>
    );
};

export default TicketDetailContainer;
