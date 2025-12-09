import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import useSWR from 'swr';

import { BillingInvoice, BillingInvoiceRow } from '@/components/dashboard/BillingInvoiceRow';
import { BillingService, BillingServiceRow } from '@/components/dashboard/BillingServiceRow';
import ActionButton from '@/components/elements/ActionButton';
import AnimatedCollapsible from '@/components/elements/AnimatedCollapsible';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import PageContentBlock from '@/components/elements/PageContentBlock';
import Spinner from '@/components/elements/Spinner';
import { Dialog } from '@/components/elements/dialog';
import { PageListContainer } from '@/components/elements/pages/PageList';

import cancelSubscription from '@/api/billing/cancelSubscription';
import getBillingPortalUrl from '@/api/billing/getBillingPortalUrl';
import getCreditTransactions, { CreditTransaction } from '@/api/billing/getCreditTransactions';
import getCreditsBalance from '@/api/billing/getCreditsBalance';
import getCreditsEnabled from '@/api/billing/getCreditsEnabled';
import getInvoices from '@/api/billing/getInvoices';
import getSubscriptions, { Subscription } from '@/api/billing/getSubscriptions';
import purchaseCredits from '@/api/billing/purchaseCredits';
import resumeSubscription from '@/api/billing/resumeSubscription';
import { httpErrorToHuman } from '@/api/http';

const BillingContainer = () => {
    // Load subscriptions
    const {
        data: subscriptions,
        error,
        mutate,
    } = useSWR<Subscription[]>('/api/client/billing/subscriptions', getSubscriptions, {
        revalidateOnFocus: false,
    });

    // Load invoices
    const { data: invoices, error: invoicesError } = useSWR<BillingInvoice[]>(
        '/api/client/billing/invoices',
        getInvoices,
        {
            revalidateOnFocus: false,
        },
    );

    // Check if credits are enabled
    const { data: creditsEnabledData, error: creditsEnabledError } = useSWR(
        '/api/client/billing/credits/enabled',
        getCreditsEnabled,
        {
            revalidateOnFocus: false,
        },
    );

    const creditsEnabled = creditsEnabledData?.data?.enabled ?? false;

    // Load credits balance (only if enabled)
    const {
        data: creditsBalance,
        error: creditsError,
        mutate: mutateCredits,
    } = useSWR(creditsEnabled ? '/api/client/billing/credits/balance' : null, getCreditsBalance, {
        revalidateOnFocus: false,
    });

    // Load credit transactions (only if enabled)
    const { data: creditTransactionsData, error: transactionsError } = useSWR(
        creditsEnabled ? ['/api/client/billing/credits/transactions', { limit: 50 }] : null,
        ([url, params]) => getCreditTransactions(params),
        {
            revalidateOnFocus: false,
        },
    );

    const creditTransactions = creditTransactionsData?.data;

    // State for managing dialogs
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [cancelConfirmDialogOpen, setCancelConfirmDialogOpen] = useState(false);
    const [resumeDialogOpen, setResumeDialogOpen] = useState(false);
    const [selectedSubscription, setSelectedSubscription] = useState<Subscription | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [cancelImmediate, setCancelImmediate] = useState<boolean | null>(null);
    const [billingPortalLoading, setBillingPortalLoading] = useState<number | null>(null);
    const [countdown, setCountdown] = useState<number>(0);
    const [buyCreditsDialogOpen, setBuyCreditsDialogOpen] = useState(false);
    const [creditsAmount, setCreditsAmount] = useState<string>('10');
    const [isPurchasingCredits, setIsPurchasingCredits] = useState(false);

    // Handler functions
    const handleBillingPortal = useCallback(async (subscriptionId: number) => {
        setBillingPortalLoading(subscriptionId);
        try {
            const response = await getBillingPortalUrl(subscriptionId);
            window.location.href = response.url;
        } catch (error: any) {
            toast.error(httpErrorToHuman(error) || 'Failed to open billing portal.');
            setBillingPortalLoading(null);
        }
    }, []);

    const handleCancel = (service: BillingService & { subscriptionId: number }) => {
        const subscription = subscriptions?.find((s) => s.attributes.id === service.subscriptionId);
        if (!subscription) return;

        setSelectedSubscription(subscription);
        setCancelDialogOpen(true);
    };

    const handleResume = (service: BillingService & { subscriptionId: number }) => {
        const subscription = subscriptions?.find((s) => s.attributes.id === service.subscriptionId);
        if (!subscription) return;

        setSelectedSubscription(subscription);
        setResumeDialogOpen(true);
    };

    // Map subscriptions to BillingService format
    // Filter out subscriptions without server_uuid (server was deleted)
    const services: (BillingService & { subscriptionId: number })[] = useMemo(() => {
        if (!subscriptions) return [];

        return subscriptions
            .filter((sub) => sub.attributes.server_uuid) // Only include subscriptions with a server
            .map((sub) => {
                const attrs = sub.attributes;
                const priceFormatted = new Intl.NumberFormat(undefined, {
                    style: 'currency',
                    currency: attrs.currency,
                }).format(attrs.price_amount);

                // Map interval to supported format
                const intervalMap: Record<string, 'month' | 'year'> = {
                    month: 'month',
                    quarter: 'month',
                    'half-year': 'month',
                    year: 'year',
                };

                const subscriptionId = attrs.id;

                return {
                    id: String(attrs.id),
                    externalId: attrs.stripe_id,
                    name: attrs.server_name || 'Unnamed Server',
                    planName: attrs.plan_name,
                    priceAmount: attrs.price_amount,
                    priceFormatted: priceFormatted,
                    currency: attrs.currency,
                    interval: intervalMap[attrs.interval] || 'month',
                    status: attrs.status,
                    nextRenewalAt: attrs.next_renewal_at || undefined,
                    manageUrl: attrs.server_uuid ? `/server/${attrs.server_uuid}` : undefined,
                    canCancel: attrs.can_cancel,
                    canResume: attrs.can_resume,
                    subscriptionId: subscriptionId,
                    onManage: attrs.server_uuid ? () => handleBillingPortal(subscriptionId) : undefined,
                };
            });
    }, [subscriptions, handleBillingPortal]);

    const confirmCancel = async (immediate: boolean) => {
        if (!selectedSubscription) return;

        setIsLoading(true);
        try {
            await cancelSubscription(selectedSubscription.attributes.id, immediate);
            if (immediate) {
                toast.success('Subscription has been canceled immediately and the server has been deleted.');
            } else {
                toast.success('Subscription will be canceled at the end of the billing period.');
            }
            await mutate();
            setCancelDialogOpen(false);
            setCancelConfirmDialogOpen(false);
            setSelectedSubscription(null);
            setCancelImmediate(null);
            setCountdown(0);
        } catch (error: any) {
            toast.error(httpErrorToHuman(error) || 'Failed to cancel subscription.');
        } finally {
            setIsLoading(false);
        }
    };

    // Countdown timer effect for cancel confirmation dialog
    useEffect(() => {
        if (cancelConfirmDialogOpen) {
            // Reset countdown to 5 when dialog opens
            setCountdown(5);

            // Start countdown timer
            const timer = setInterval(() => {
                setCountdown((prev) => {
                    if (prev <= 1) {
                        clearInterval(timer);
                        return 0;
                    }
                    return prev - 1;
                });
            }, 1000);

            // Cleanup on unmount or dialog close
            return () => clearInterval(timer);
        } else {
            setCountdown(0);
        }
    }, [cancelConfirmDialogOpen]);

    const confirmResume = async () => {
        if (!selectedSubscription) return;

        setIsLoading(true);
        try {
            await resumeSubscription(selectedSubscription.attributes.id);
            toast.success('Subscription has been resumed.');
            await mutate();
            setResumeDialogOpen(false);
            setSelectedSubscription(null);
        } catch (error: any) {
            toast.error(httpErrorToHuman(error) || 'Failed to resume subscription.');
        } finally {
            setIsLoading(false);
        }
    };

    const [expanded, setExpanded] = useState(false);
    const visibleCount = 4;
    const visible = services.slice(0, visibleCount);
    const hidden = services.slice(visibleCount);
    const hasHidden = hidden.length > 0;

    const handleBuyCredits = async () => {
        const amount = parseFloat(creditsAmount);
        if (!amount || amount < 1) {
            toast.error('Please enter a valid amount (minimum $1).');
            return;
        }

        setIsPurchasingCredits(true);
        try {
            const response = await purchaseCredits({ amount });
            window.location.href = response.data.checkout_url;
        } catch (error: any) {
            toast.error(httpErrorToHuman(error) || 'Failed to create credits purchase session.');
            setIsPurchasingCredits(false);
        }
    };

    return (
        <PageContentBlock title={'Billing'} showFlashKey={'billing'}>
            {creditsEnabled && (
                <div
                    className='transform-gpu skeleton-anim-2 mb-6'
                    style={{
                        animationDelay: '0ms',
                        animationTimingFunction:
                            'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                    }}
                >
                    <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-6'>
                        <div className='flex items-center justify-between flex-wrap gap-4'>
                            <div>
                                <h3 className='text-lg font-semibold text-white mb-1'>Account Credits</h3>
                                <div className='text-2xl font-bold text-brand'>
                                    {creditsBalance?.data ? (
                                        <>
                                            {new Intl.NumberFormat('en-US', {
                                                style: 'currency',
                                                currency: creditsBalance.data.currency.toUpperCase(),
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2,
                                            }).format(creditsBalance.data.balance)}
                                        </>
                                    ) : (
                                        'Loading...'
                                    )}
                                </div>
                            </div>
                            <ActionButton variant='primary' onClick={() => setBuyCreditsDialogOpen(true)}>
                                Buy Credits
                            </ActionButton>
                        </div>
                    </div>
                </div>
            )}
            <div
                className='transform-gpu skeleton-anim-2 mb-3 sm:mb-4'
                style={{
                    animationDelay: '0ms',
                    animationTimingFunction:
                        'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                }}
            >
                <MainPageHeader title='Active Services' />
                <PageListContainer className='p-4 flex flex-col gap-3'>
                    {!subscriptions && !error ? (
                        <div className='p-2 text-sm text-white/70'>Loading services…</div>
                    ) : error ? (
                        <div className='p-2 text-sm text-red-400'>
                            Failed to load subscriptions: {httpErrorToHuman(error)}
                        </div>
                    ) : services.length === 0 ? (
                        <div className='p-2 text-sm text-white/70'>No active services yet.</div>
                    ) : (
                        <>
                            {visible.map((service, index) => (
                                <div
                                    key={service.id}
                                    className='transform-gpu skeleton-anim-2'
                                    style={{
                                        animationDelay: '0ms',
                                        animationTimingFunction:
                                            'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                                    }}
                                >
                                    <BillingServiceRow
                                        service={service}
                                        onCancel={() => handleCancel(service)}
                                        onResume={() => handleResume(service)}
                                    />
                                </div>
                            ))}

                            {hasHidden && (
                                <div className='flex flex-col gap-3'>
                                    {!expanded ? (
                                        <button
                                            onClick={() => setExpanded(true)}
                                            className='mt-1 inline-flex items-center justify-center gap-2 rounded-md bg-[#ffffff11] hover:bg-[#ffffff23] px-3 py-2 text-sm font-medium text-[#ffffffcc] transition-colors'
                                            aria-expanded={expanded}
                                        >
                                            Show {hidden.length} more
                                            <svg
                                                xmlns='http://www.w3.org/2000/svg'
                                                width='14'
                                                height='14'
                                                viewBox='0 0 24 24'
                                                fill='none'
                                                stroke='currentColor'
                                                strokeWidth='2'
                                                strokeLinecap='round'
                                                strokeLinejoin='round'
                                            >
                                                <polyline points='6 9 12 15 18 9' />
                                            </svg>
                                        </button>
                                    ) : null}

                                    <AnimatedCollapsible open={expanded} durationMs={260}>
                                        <div className='flex flex-col gap-3'>
                                            {hidden.map((service, i) => {
                                                const index = visible.length + i;
                                                return (
                                                    <div
                                                        key={service.id}
                                                        className='transform-gpu skeleton-anim-2'
                                                        style={{
                                                            animationDelay: '0ms',
                                                            animationTimingFunction:
                                                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                                                        }}
                                                    >
                                                        <div
                                                            className='transition-all duration-300'
                                                            style={{
                                                                opacity: expanded ? 1 : 0,
                                                                transform: expanded
                                                                    ? 'translateY(0px)'
                                                                    : 'translateY(-4px)',
                                                            }}
                                                        >
                                                            <BillingServiceRow
                                                                service={service}
                                                                onCancel={() => handleCancel(service)}
                                                                onResume={() => handleResume(service)}
                                                            />
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </AnimatedCollapsible>

                                    {expanded && (
                                        <button
                                            onClick={() => setExpanded(false)}
                                            className='inline-flex items-center justify-center gap-2 rounded-md bg-[#ffffff11] hover:bg-[#ffffff23] px-3 py-2 text-sm font-medium text-[#ffffffcc] transition-colors'
                                            aria-expanded={expanded}
                                        >
                                            Show less
                                            <svg
                                                xmlns='http://www.w3.org/2000/svg'
                                                width='14'
                                                height='14'
                                                viewBox='0 0 24 24'
                                                fill='none'
                                                stroke='currentColor'
                                                strokeWidth='2'
                                                strokeLinecap='round'
                                                strokeLinejoin='round'
                                            >
                                                <polyline points='18 15 12 9 6 15' />
                                            </svg>
                                        </button>
                                    )}
                                </div>
                            )}
                        </>
                    )}
                </PageListContainer>
            </div>

            {creditsEnabled && (
                <>
                    <div aria-hidden className='mt-16 mb-16 bg-[#ffffff33] min-h-[1px] w-full'></div>

                    <div
                        className='transform-gpu skeleton-anim-2 mb-3 sm:mb-4'
                        style={{
                            animationDelay: '0ms',
                            animationTimingFunction:
                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                        }}
                    >
                        <MainPageHeader title='Credit Transaction History' />
                        <PageListContainer className='p-4 flex flex-col gap-3'>
                            {!creditTransactions && !transactionsError ? (
                                <div className='p-2 text-sm text-white/70'>Loading transactions…</div>
                            ) : transactionsError ? (
                                <div className='p-2 text-sm text-red-400'>
                                    Failed to load transactions: {httpErrorToHuman(transactionsError)}
                                </div>
                            ) : !creditTransactions || creditTransactions.length === 0 ? (
                                <div className='p-2 text-sm text-white/70'>No transactions yet.</div>
                            ) : (
                                <div className='overflow-x-auto'>
                                    <table className='w-full'>
                                        <thead>
                                            <tr className='border-b border-[#ffffff12]'>
                                                <th className='text-left py-3 px-4 text-sm font-semibold text-white/70'>
                                                    Date
                                                </th>
                                                <th className='text-left py-3 px-4 text-sm font-semibold text-white/70'>
                                                    Type
                                                </th>
                                                <th className='text-left py-3 px-4 text-sm font-semibold text-white/70'>
                                                    Description
                                                </th>
                                                <th className='text-right py-3 px-4 text-sm font-semibold text-white/70'>
                                                    Amount
                                                </th>
                                                <th className='text-right py-3 px-4 text-sm font-semibold text-white/70'>
                                                    Balance
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {creditTransactions.map((transaction) => {
                                                const date = new Date(transaction.created_at).toLocaleDateString(
                                                    undefined,
                                                    {
                                                        year: 'numeric',
                                                        month: 'short',
                                                        day: 'numeric',
                                                        hour: '2-digit',
                                                        minute: '2-digit',
                                                    },
                                                );

                                                const typeColors: Record<string, string> = {
                                                    purchase: 'text-green-400',
                                                    deduction: 'text-red-400',
                                                    refund: 'text-blue-400',
                                                    renewal: 'text-yellow-400',
                                                    adjustment: 'text-purple-400',
                                                };

                                                const typeLabels: Record<string, string> = {
                                                    purchase: 'Purchase',
                                                    deduction: 'Deduction',
                                                    refund: 'Refund',
                                                    renewal: 'Renewal',
                                                    adjustment: 'Adjustment',
                                                };

                                                const isPositive =
                                                    transaction.type === 'purchase' || transaction.type === 'refund';
                                                const amountSign = isPositive ? '+' : '-';
                                                const amountColor = isPositive ? 'text-green-400' : 'text-red-400';

                                                return (
                                                    <tr
                                                        key={transaction.id}
                                                        className='border-b border-[#ffffff08] hover:bg-[#ffffff05] transition-colors'
                                                    >
                                                        <td className='py-3 px-4 text-sm text-white/70'>{date}</td>
                                                        <td className='py-3 px-4'>
                                                            <span
                                                                className={`text-sm font-medium ${typeColors[transaction.type] || 'text-white/70'}`}
                                                            >
                                                                {typeLabels[transaction.type] || transaction.type}
                                                            </span>
                                                        </td>
                                                        <td className='py-3 px-4 text-sm text-white/70'>
                                                            {transaction.description || '—'}
                                                        </td>
                                                        <td
                                                            className={`py-3 px-4 text-sm font-semibold text-right ${amountColor}`}
                                                        >
                                                            {amountSign}
                                                            {new Intl.NumberFormat('en-US', {
                                                                style: 'currency',
                                                                currency:
                                                                    creditsBalance?.data?.currency?.toUpperCase() ||
                                                                    'USD',
                                                                minimumFractionDigits: 2,
                                                                maximumFractionDigits: 2,
                                                            }).format(transaction.amount)}
                                                        </td>
                                                        <td className='py-3 px-4 text-sm text-white/70 text-right'>
                                                            {new Intl.NumberFormat('en-US', {
                                                                style: 'currency',
                                                                currency:
                                                                    creditsBalance?.data?.currency?.toUpperCase() ||
                                                                    'USD',
                                                                minimumFractionDigits: 2,
                                                                maximumFractionDigits: 2,
                                                            }).format(transaction.balance_after)}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </PageListContainer>
                    </div>
                </>
            )}

            <div aria-hidden className='mt-16 mb-16 bg-[#ffffff33] min-h-[1px] w-full'></div>

            <div
                className='transform-gpu skeleton-anim-2 mb-3 sm:mb-4'
                style={{
                    animationDelay: '0ms',
                    animationTimingFunction:
                        'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                }}
            >
                <MainPageHeader title='Billing & Invoices' />
                <PageListContainer className='p-4 flex flex-col gap-3'>
                    {!invoices && !invoicesError ? (
                        <div className='p-2 text-sm text-white/70'>Loading invoices…</div>
                    ) : invoicesError ? (
                        <div className='p-2 text-sm text-red-400'>
                            Failed to load invoices: {httpErrorToHuman(invoicesError)}
                        </div>
                    ) : !invoices || invoices.length === 0 ? (
                        <div className='p-2 text-sm text-white/70'>No invoices yet.</div>
                    ) : (
                        invoices.map((invoice) => <BillingInvoiceRow key={invoice.id} invoice={invoice} />)
                    )}
                </PageListContainer>
            </div>

            {/* Cancel Subscription Dialog */}
            <Dialog
                open={cancelDialogOpen}
                onClose={() => {
                    setCancelDialogOpen(false);
                    setSelectedSubscription(null);
                    setCancelImmediate(null);
                }}
                title='Cancel Subscription'
            >
                <div className='space-y-4'>
                    <p className='text-zinc-300'>
                        How would you like to cancel your subscription for{' '}
                        <span className='font-semibold text-zinc-50'>
                            {selectedSubscription?.attributes.server_name || 'this server'}
                        </span>
                        ?
                    </p>

                    <div className='space-y-3'>
                        <button
                            onClick={() => setCancelImmediate(false)}
                            disabled={isLoading}
                            className={`w-full text-left p-4 rounded-lg border-2 transition-colors ${
                                cancelImmediate === false
                                    ? 'border-yellow-500 bg-yellow-500/10'
                                    : 'border-[#ffffff12] hover:border-[#ffffff20]'
                            }`}
                        >
                            <div className='font-semibold text-zinc-50 mb-1'>Cancel at Billing Date</div>
                            <div className='text-sm text-zinc-400'>
                                Your subscription will remain active until the end of the current billing period. Your
                                server will stay accessible until then.
                            </div>
                        </button>

                        <button
                            onClick={() => setCancelImmediate(true)}
                            disabled={isLoading}
                            className={`w-full text-left p-4 rounded-lg border-2 transition-colors ${
                                cancelImmediate === true
                                    ? 'border-red-500 bg-red-500/10'
                                    : 'border-[#ffffff12] hover:border-[#ffffff20]'
                            }`}
                        >
                            <div className='font-semibold text-zinc-50 mb-1'>Cancel Immediately</div>
                            <div className='text-sm text-zinc-400'>
                                Your subscription will be canceled immediately and your server will be deleted. This
                                action cannot be undone.
                            </div>
                        </button>
                    </div>
                </div>

                <Dialog.Footer>
                    <ActionButton
                        variant='secondary'
                        onClick={() => {
                            setCancelDialogOpen(false);
                            setSelectedSubscription(null);
                            setCancelImmediate(null);
                        }}
                    >
                        Cancel
                    </ActionButton>
                    <ActionButton
                        variant='primary'
                        onClick={() => {
                            if (cancelImmediate !== null) {
                                setCancelDialogOpen(false);
                                setCancelConfirmDialogOpen(true);
                            }
                        }}
                        disabled={isLoading || cancelImmediate === null}
                    >
                        Continue
                    </ActionButton>
                </Dialog.Footer>
            </Dialog>

            {/* Cancel Confirmation Dialog */}
            <Dialog
                open={cancelConfirmDialogOpen}
                onClose={() => {
                    setCancelConfirmDialogOpen(false);
                    setCancelDialogOpen(true);
                    setCountdown(0);
                }}
                title={cancelImmediate ? 'Confirm Immediate Cancellation' : 'Confirm Cancellation at Billing Date'}
            >
                {cancelImmediate ? (
                    <>
                        <p className='mb-3 font-semibold text-red-400'>
                            You are about to immediately cancel your subscription and permanently delete your server.
                        </p>
                        <div className='space-y-2 text-sm text-zinc-300'>
                            <p>When you cancel immediately, you will:</p>
                            <ul className='list-disc list-inside space-y-1 ml-2'>
                                <li>Immediately lose all access to your server</li>
                                <li>Have your server permanently deleted</li>
                                <li>Lose all server data, files, backups, and configurations</li>
                                <li>Not receive any refund for the remaining billing period</li>
                                <li>Not be able to recover any data after this action</li>
                            </ul>
                        </div>
                        <p className='mt-4 text-sm text-red-400 font-semibold'>
                            This action cannot be undone. Are you absolutely sure?
                        </p>
                    </>
                ) : (
                    <>
                        <p className='mb-3 font-semibold text-yellow-400'>
                            You are about to cancel your subscription at the end of the billing period.
                        </p>
                        <div className='space-y-2 text-sm text-zinc-300'>
                            <p>When you cancel at the billing date, you will:</p>
                            <ul className='list-disc list-inside space-y-1 ml-2'>
                                <li>
                                    Keep access to your server until{' '}
                                    {selectedSubscription?.attributes.next_renewal_at ? (
                                        <span className='font-semibold'>
                                            {new Date(
                                                selectedSubscription.attributes.next_renewal_at,
                                            ).toLocaleDateString(undefined, {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            })}
                                        </span>
                                    ) : (
                                        'the end of your billing period'
                                    )}
                                </li>
                                <li>Have until the billing date to retrieve any data and/or backups</li>
                                <li>Your server will be permanently deleted after the cancellation date</li>
                                <li>You can resume the subscription at any time before the cancellation date</li>
                            </ul>
                        </div>
                        <p className='mt-4 text-sm text-zinc-400'>
                            Are you sure you want to proceed with canceling at the billing date?
                        </p>
                    </>
                )}

                <Dialog.Footer>
                    <ActionButton
                        variant='secondary'
                        onClick={() => {
                            setCancelConfirmDialogOpen(false);
                            setCancelDialogOpen(true);
                            setCountdown(0);
                        }}
                    >
                        Back
                    </ActionButton>
                    <ActionButton
                        variant='danger'
                        onClick={() => {
                            if (cancelImmediate !== null && countdown === 0) {
                                confirmCancel(cancelImmediate);
                                setCancelConfirmDialogOpen(false);
                            }
                        }}
                        disabled={isLoading || countdown > 0}
                    >
                        <div className='flex items-center gap-2'>
                            {isLoading && <Spinner size='small' />}
                            <span>
                                {countdown > 0
                                    ? `(${countdown}) ${cancelImmediate ? 'Cancel Immediately' : 'Cancel at Billing Date'}`
                                    : cancelImmediate
                                      ? 'Cancel Immediately'
                                      : 'Cancel at Billing Date'}
                            </span>
                        </div>
                    </ActionButton>
                </Dialog.Footer>
            </Dialog>

            {/* Resume Subscription Dialog */}
            <Dialog.Confirm
                open={resumeDialogOpen}
                onClose={() => {
                    setResumeDialogOpen(false);
                    setSelectedSubscription(null);
                }}
                title='Resume Subscription'
                confirm='Resume Subscription'
                onConfirmed={confirmResume}
                loading={isLoading}
            >
                <p className='mb-2'>
                    Are you sure you want to resume your subscription for{' '}
                    <span className='font-semibold text-zinc-50'>
                        {selectedSubscription?.attributes.server_name || 'this server'}
                    </span>
                    ?
                </p>
                <p className='text-sm text-zinc-400'>
                    Your subscription will continue from the end of the current billing period.
                </p>
            </Dialog.Confirm>

            {/* Buy Credits Dialog */}
            <Dialog
                open={buyCreditsDialogOpen}
                onClose={() => {
                    setBuyCreditsDialogOpen(false);
                    setCreditsAmount('10');
                }}
                title='Purchase Credits'
            >
                <div className='space-y-4'>
                    <p className='text-zinc-300'>
                        Enter the amount of credits you want to purchase. Credits will be added to your account after
                        payment is processed.
                    </p>

                    <div>
                        <label htmlFor='credits-amount' className='block text-sm font-medium text-zinc-300 mb-2'>
                            Amount (USD)
                        </label>
                        <input
                            id='credits-amount'
                            type='number'
                            min='1'
                            step='0.01'
                            value={creditsAmount}
                            onChange={(e) => setCreditsAmount(e.target.value)}
                            className='w-full px-4 py-2 bg-[#ffffff08] border border-[#ffffff12] rounded-lg text-white placeholder-white/30 focus:outline-none focus:border-brand transition-colors'
                            placeholder='10.00'
                        />
                        <p className='mt-1 text-xs text-zinc-400'>Minimum purchase amount is $1.00</p>
                    </div>
                </div>

                <Dialog.Footer>
                    <ActionButton
                        variant='secondary'
                        onClick={() => {
                            setBuyCreditsDialogOpen(false);
                            setCreditsAmount('10');
                        }}
                    >
                        Cancel
                    </ActionButton>
                    <ActionButton
                        variant='primary'
                        onClick={handleBuyCredits}
                        disabled={isPurchasingCredits || !creditsAmount || parseFloat(creditsAmount) < 1}
                    >
                        {isPurchasingCredits ? (
                            <>
                                <Spinner size='small' className='mr-2' />
                                Processing...
                            </>
                        ) : (
                            'Continue to Payment'
                        )}
                    </ActionButton>
                </Dialog.Footer>
            </Dialog>
        </PageContentBlock>
    );
};

export default BillingContainer;
