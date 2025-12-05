import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import useSWR from 'swr';

import Navbar from '@/components/Navbar';
import ActionButton from '@/components/elements/ActionButton';
import Logo from '@/components/elements/PyroLogo';

import http from '@/api/http';

interface ServerCreationStatus {
    enabled: boolean;
    disabled_message: string;
    status_page_url: string;
    show_status_page_button: boolean;
    show_logo: boolean;
}

const ServerCreationDisabled = () => {
    const navigate = useNavigate();
    const { data, isLoading } = useSWR<ServerCreationStatus>(
        '/api/client/hosting/server-creation-status',
        async (url: string) => {
            const response = await http.get(url);
            return response.data.data;
        },
    );

    useEffect(() => {
        document.title = 'Server Creation Temporarily Disabled | Pyrodactyl';
    }, []);

    // If server creation is enabled, redirect back to hosting
    useEffect(() => {
        if (data && data.enabled) {
            navigate('/hosting');
        }
    }, [data, navigate]);

    if (isLoading) {
        return (
            <div className='h-full min-h-screen bg-[#0a0a0a] overflow-y-auto -mx-2 -my-2 w-[calc(100%+1rem)]'>
                <Navbar />
                <div className='flex items-center justify-center min-h-[calc(100vh-4rem)]'>
                    <div className='text-white/70'>Loading...</div>
                </div>
            </div>
        );
    }

    if (!data || data.enabled) {
        return null;
    }

    return (
        <div className='h-full min-h-screen bg-[#0a0a0a] overflow-y-auto -mx-2 -my-2 w-[calc(100%+1rem)]'>
            <Navbar />
            <div className='flex items-center justify-center min-h-[calc(100vh-4rem)] px-4'>
                <div className='max-w-2xl w-full text-center'>
                    {(data.show_logo ?? true) && (
                        <div className='mb-8 flex justify-center'>
                            <div className='w-84'>
                                <Logo className='h-auto w-full' />
                            </div>
                        </div>
                    )}
                    <div className='bg-[#ffffff08] border border-[#ffffff12] rounded-lg p-8 md:p-12'>
                        <div className='mb-6'>
                            <h1 className='text-3xl md:text-4xl font-bold text-white mb-4'>
                                Server Creation Temporarily Disabled
                            </h1>
                        </div>
                        <div className='mb-8'>
                            <p className='text-lg text-white/70 whitespace-pre-line'>
                                {data.disabled_message ||
                                    "We're currently scaling our infrastructure to provide better service. Server creation is temporarily disabled. Please check back soon!"}
                            </p>
                        </div>
                        <div className='flex items-center justify-center gap-4'>
                            {data.show_status_page_button && data.status_page_url && (
                                <ActionButton
                                    variant='secondary'
                                    size='lg'
                                    onClick={() => window.open(data.status_page_url, '_blank')}
                                >
                                    View our status page
                                </ActionButton>
                            )}
                            <ActionButton variant='secondary' size='lg' onClick={() => navigate('/hosting')}>
                                Return to Website
                            </ActionButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ServerCreationDisabled;
