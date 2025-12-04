import { useMemo, useState, useCallback } from 'react';
import useSWR from 'swr';
import { toast } from 'sonner';

import { BillingService, BillingServiceRow } from '@/components/dashboard/BillingServiceRow';
import AnimatedCollapsible from '@/components/elements/AnimatedCollapsible';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { PageListContainer } from '@/components/elements/pages/PageList';
import { Dialog } from '@/components/elements/dialog';
import ActionButton from '@/components/elements/ActionButton';
import Spinner from '@/components/elements/Spinner';

import getSubscriptions, { Subscription } from '@/api/billing/getSubscriptions';
import cancelSubscription from '@/api/billing/cancelSubscription';
import resumeSubscription from '@/api/billing/resumeSubscription';
import getBillingPortalUrl from '@/api/billing/getBillingPortalUrl';
import { httpErrorToHuman } from '@/api/http';

const BillingContainer = () => {
    // Load subscriptions
    const { data: subscriptions, error, mutate } = useSWR<Subscription[]>(
        '/api/client/billing/subscriptions',
        getSubscriptions,
        {
            revalidateOnFocus: false,
        }
    );

    // State for managing dialogs
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [resumeDialogOpen, setResumeDialogOpen] = useState(false);
    const [selectedSubscription, setSelectedSubscription] = useState<Subscription | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [cancelImmediate, setCancelImmediate] = useState<boolean | null>(null);
    const [billingPortalLoading, setBillingPortalLoading] = useState<number | null>(null);

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
    const services: (BillingService & { subscriptionId: number })[] = useMemo(() => {
        if (!subscriptions) return [];
        
        return subscriptions.map((sub) => {
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
            setSelectedSubscription(null);
            setCancelImmediate(null);
        } catch (error: any) {
            toast.error(httpErrorToHuman(error) || 'Failed to cancel subscription.');
        } finally {
            setIsLoading(false);
        }
    };

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

    return (
        <PageContentBlock title={'Billing'} showFlashKey={'billing'}>
            <div className='transform-gpu skeleton-anim-2 mb-3 sm:mb-4'>
                <MainPageHeader title='Active Services' />
                <PageListContainer className='p-4 flex flex-col gap-3'>
                    {!subscriptions && !error ? (
                        <div className='p-4 text-sm text-white/70'>Loading services…</div>
                    ) : error ? (
                        <div className='p-4 text-sm text-red-400'>
                            Failed to load subscriptions: {httpErrorToHuman(error)}
                        </div>
                    ) : services.length === 0 ? (
                        <div className='p-4 text-sm text-white/70'>No active services yet.</div>
                    ) : (
                        <>
                            {visible.map((service, index) => (
                                <div
                                    key={service.id}
                                    className='transform-gpu skeleton-anim-2'
                                    style={{
                                        animationDelay: `${index * 50 + 50}ms`,
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
                                                            animationDelay: `${index * 50 + 50}ms`,
                                                            animationTimingFunction:
                                                                'linear(0,0.01,0.04 1.6%,0.161 3.3%,0.816 9.4%,1.046,1.189 14.4%,1.231,1.254 17%,1.259,1.257 18.6%,1.236,1.194 22.3%,1.057 27%,0.999 29.4%,0.955 32.1%,0.942,0.935 34.9%,0.933,0.939 38.4%,1 47.3%,1.011,1.017 52.6%,1.016 56.4%,1 65.2%,0.996 70.2%,1.001 87.2%,1)',
                                                        }}
                                                    >
                                                        <div
                                                            className='transition-all duration-300'
                                                            style={{
                                                                opacity: expanded ? 1 : 0,
                                                                transform: expanded ? 'translateY(0px)' : 'translateY(-4px)',
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

            <div aria-hidden className='mt-16 mb-16 bg-[#ffffff33] min-h-[1px] w-full'></div>

            <div className='transform-gpu skeleton-anim-2 mb-3 sm:mb-4'>
                <MainPageHeader title='Billing & Invoices' />
                <PageListContainer className='p-4'>
                    <div className='p-4 text-sm text-white/70'>No invoices yet.</div>
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
                    <ActionButton variant='secondary' onClick={() => {
                        setCancelDialogOpen(false);
                        setSelectedSubscription(null);
                        setCancelImmediate(null);
                    }}>
                        Cancel
                    </ActionButton>
                    <ActionButton
                        variant='danger'
                        onClick={() => {
                            if (cancelImmediate !== null) {
                                confirmCancel(cancelImmediate);
                            }
                        }}
                        disabled={isLoading || cancelImmediate === null}
                    >
                        <div className='flex items-center gap-2'>
                            {isLoading && <Spinner size='small' />}
                            <span>
                                {cancelImmediate === true
                                    ? 'Cancel Immediately'
                                    : cancelImmediate === false
                                      ? 'Cancel at Billing Date'
                                      : 'Confirm Cancellation'}
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
        </PageContentBlock>
    );
};

export default BillingContainer;
