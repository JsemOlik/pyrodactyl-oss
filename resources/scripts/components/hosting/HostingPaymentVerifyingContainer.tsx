import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { toast } from 'sonner';

import { MainPageHeader } from '@/components/elements/MainPageHeader';
import PageContentBlock from '@/components/elements/PageContentBlock';
import Spinner from '@/components/elements/Spinner';

import verifyPayment, { PaymentVerificationResponse } from '@/api/hosting/verifyPayment';
import { httpErrorToHuman } from '@/api/http';

const HostingPaymentVerifyingContainer = () => {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const sessionId = searchParams.get('session_id');

    const [status, setStatus] = useState<'pending' | 'processing' | 'completed' | 'error'>('pending');
    const [message, setMessage] = useState('Please wait, we are verifying your payment...');
    const [pollCount, setPollCount] = useState(0);
    const maxPolls = 60; // Poll for up to 60 times (5 minutes at 5 second intervals)

    useEffect(() => {
        document.title = 'Verifying Payment | Pyrodactyl';

        if (!sessionId) {
            toast.error('Missing session ID. Redirecting to dashboard...');
            navigate('/');
            return;
        }

        // Start polling immediately
        const pollInterval = setInterval(() => {
            verifyPayment(sessionId)
                .then((response: PaymentVerificationResponse) => {
                    setStatus(response.status);
                    setMessage(response.message);

                    if (response.status === 'completed' && response.server) {
                        clearInterval(pollInterval);
                        toast.success('Payment verified! Your server is being created...');
                        
                        // Redirect to server page after a brief delay
                        setTimeout(() => {
                            navigate(`/server/${response.server.uuid}`);
                        }, 1500);
                        return;
                    }

                    // Increment poll count
                    setPollCount((prev) => {
                        const newCount = prev + 1;
                        
                        // If we've exceeded max polls, show error
                        if (newCount >= maxPolls) {
                            clearInterval(pollInterval);
                            setStatus('error');
                            setMessage('Payment verification is taking longer than expected. Please check your dashboard or contact support.');
                            toast.error('Payment verification timeout. Please check your dashboard.');
                        }
                        
                        return newCount;
                    });
                })
                .catch((error) => {
                    console.error('Payment verification error:', error);
                    // Don't stop polling on error - might be transient
                    // Only stop if it's a clear failure
                    if (pollCount >= 5) {
                        // After 5 failed attempts, show error
                        clearInterval(pollInterval);
                        setStatus('error');
                        setMessage('Unable to verify payment status. Please check your dashboard or contact support.');
                        toast.error(httpErrorToHuman(error) || 'Unable to verify payment status.');
                    }
                });
        }, 5000); // Poll every 5 seconds

        // Cleanup on unmount
        return () => clearInterval(pollInterval);
    }, [sessionId, navigate, pollCount]);

    return (
        <PageContentBlock title='Verifying Payment'>
            <MainPageHeader title='Verifying Your Payment' />

            <div className='max-w-2xl mx-auto'>
                <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-8 text-center'>
                    {status === 'error' ? (
                        <>
                            <div className='text-red-400 text-6xl mb-4'>⚠️</div>
                            <h2 className='text-xl font-semibold text-white mb-2'>Verification Error</h2>
                            <p className='text-white/70 mb-6'>{message}</p>
                            <button
                                onClick={() => navigate('/')}
                                className='px-6 py-2 bg-[#ffffff12] hover:bg-[#ffffff20] text-white rounded-lg transition-colors'
                            >
                                Go to Dashboard
                            </button>
                        </>
                    ) : (
                        <>
                            <div className='mb-6'>
                                <Spinner size='large' />
                            </div>
                            <h2 className='text-xl font-semibold text-white mb-2'>Please Wait</h2>
                            <p className='text-white/70 mb-2'>{message}</p>
                            <p className='text-sm text-white/50'>
                                This may take a few seconds. You will be redirected automatically once your payment is
                                confirmed and your server is ready.
                            </p>
                            {pollCount > 0 && (
                                <p className='text-xs text-white/40 mt-4'>
                                    Checking... ({pollCount}/{maxPolls})
                                </p>
                            )}
                        </>
                    )}
                </div>
            </div>
        </PageContentBlock>
    );
};

export default HostingPaymentVerifyingContainer;

