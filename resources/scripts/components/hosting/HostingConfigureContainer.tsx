import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import useSWR from 'swr';

import ActionButton from '@/components/elements/ActionButton';
import { MainPageHeader } from '@/components/elements/MainPageHeader';

import getVpsDistributions, { VpsDistribution } from '@/api/hosting/getVpsDistributions';
import { httpErrorToHuman } from '@/api/http';
import getNests from '@/api/nests/getNests';

interface SelectedPlan {
    planId?: number;
    isCustom?: boolean;
    memory?: number;
    interval?: string;
}

type HostingType = 'game-server' | 'vps';
type FlowStep = 'select-nest' | 'select-egg';

const HostingConfigureContainer = () => {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const hostingType = (searchParams.get('type') || 'game-server') as HostingType;

    const {
        data: nests,
        error: nestsError,
        isLoading: nestsLoading,
    } = useSWR(hostingType === 'game-server' ? '/api/client/nests' : null, getNests);
    const {
        data: distributions,
        error: distributionsError,
        isLoading: distributionsLoading,
    } = useSWR(hostingType === 'vps' ? '/api/client/hosting/vps-distributions' : null, getVpsDistributions);

    const [selectedPlan, setSelectedPlan] = useState<SelectedPlan | null>(null);
    const [selectedNest, setSelectedNest] = useState<number | null>(null);
    const [selectedEgg, setSelectedEgg] = useState<number | null>(null);
    const [selectedDistribution, setSelectedDistribution] = useState<string | null>(null);
    const [currentStep, setCurrentStep] = useState<FlowStep>('select-nest');

    useEffect(() => {
        document.title = 'Configure Server | Pyrodactyl';

        // Parse plan selection from URL params
        const planId = searchParams.get('plan');
        const isCustom = searchParams.get('custom') === 'true';
        const memory = searchParams.get('memory');
        const interval = searchParams.get('interval');

        if (planId) {
            setSelectedPlan({ planId: parseInt(planId) });
        } else if (isCustom && memory) {
            setSelectedPlan({
                isCustom: true,
                memory: parseInt(memory),
                interval: interval || 'month',
            });
        } else {
            // No plan selected, redirect back
            navigate('/hosting');
        }
    }, [searchParams, navigate]);

    // Reset step when nest is cleared
    useEffect(() => {
        if (!selectedNest && hostingType === 'game-server') {
            setCurrentStep('select-nest');
        }
    }, [selectedNest, hostingType]);

    const handleNestSelection = (nestId: number) => {
        setSelectedNest(nestId);
        setSelectedEgg(null);
        setCurrentStep('select-egg');
    };

    const handleEggSelection = (eggId: number) => {
        setSelectedEgg(eggId);
    };

    const handleContinue = () => {
        if (hostingType === 'game-server' && (!selectedNest || !selectedEgg)) {
            return;
        }
        if (hostingType === 'vps' && !selectedDistribution) {
            return;
        }

        // Navigate to billing/checkout with configuration
        const params = new URLSearchParams();
        if (selectedPlan?.planId) {
            params.set('plan', selectedPlan.planId.toString());
        } else if (selectedPlan?.isCustom) {
            params.set('custom', 'true');
            params.set('memory', selectedPlan.memory?.toString() || '');
            params.set('interval', selectedPlan.interval || 'month');
        }
        params.set('type', hostingType);

        if (hostingType === 'game-server') {
            params.set('nest', selectedNest!.toString());
            params.set('egg', selectedEgg!.toString());
        } else {
            params.set('distribution', selectedDistribution!);
        }

        navigate(`/hosting/checkout?${params.toString()}`);
    };

    const handleBack = () => {
        if (hostingType === 'game-server' && currentStep === 'select-egg') {
            setCurrentStep('select-nest');
            setSelectedEgg(null);
        } else {
            navigate('/hosting');
        }
    };

    const isLoading = hostingType === 'game-server' ? nestsLoading : distributionsLoading;
    const hasError = hostingType === 'game-server' ? nestsError || !nests : distributionsError || !distributions;

    if (isLoading) {
        return (
            <div className='h-full min-h-screen bg-[#0a0a0a] overflow-y-auto -mx-2 -my-2 w-[calc(100%+1rem)]'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8'>
                    <div className='flex items-center justify-center min-h-[400px]'>
                        <div className='text-white/70'>Loading configuration options...</div>
                    </div>
                </div>
            </div>
        );
    }

    if (hasError) {
        return (
            <div className='h-full min-h-screen bg-[#0a0a0a] overflow-y-auto -mx-2 -my-2 w-[calc(100%+1rem)]'>
                <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8'>
                    <div className='flex items-center justify-center min-h-[400px]'>
                        <div className='text-red-400'>
                            Failed to load configuration options. Please try again later.
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    const selectedNestData = nests?.find((nest) => nest.attributes.id === selectedNest);
    const availableEggs = selectedNestData?.attributes.relationships?.eggs?.data || [];

    return (
        <div className='h-full min-h-screen bg-[#0a0a0a] overflow-y-auto -mx-2 -my-2 w-[calc(100%+1rem)]'>
            <div className='max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8'>
                <MainPageHeader title={`Configure Your ${hostingType === 'vps' ? 'VPS' : 'Server'}`} />

                <div className='space-y-6'>
                    {hostingType === 'game-server' ? (
                        <>
                            {/* Nest Selection Step */}
                            {currentStep === 'select-nest' && (
                                <div className='space-y-4'>
                                    <div>
                                        <h3 className='text-lg font-semibold text-white mb-2'>Select Game Type</h3>
                                        <p className='text-sm text-white/70 mb-4'>
                                            Choose the type of game or software you want to run
                                        </p>
                                    </div>
                                    <div className='grid grid-cols-1 md:grid-cols-2 gap-3'>
                                        {nests?.map((nest) => (
                                            <button
                                                key={nest.attributes.id}
                                                onClick={() => handleNestSelection(nest.attributes.id)}
                                                className={`p-4 rounded-lg border transition-all text-left ${
                                                    selectedNest === nest.attributes.id
                                                        ? 'border-[#ffffff30] bg-[#ffffff10]'
                                                        : 'border-[#ffffff12] bg-[#ffffff05] hover:border-[#ffffff20]'
                                                }`}
                                            >
                                                <div className='font-semibold text-white mb-1'>
                                                    {nest.attributes.name}
                                                </div>
                                                {nest.attributes.description && (
                                                    <div className='text-sm text-white/60'>
                                                        {nest.attributes.description}
                                                    </div>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {/* Egg Selection Step */}
                            {currentStep === 'select-egg' && selectedNest && (
                                <div className='space-y-4'>
                                    <div>
                                        <h3 className='text-lg font-semibold text-white mb-2'>
                                            Select Game -{' '}
                                            {nests?.find((n) => n.attributes.id === selectedNest)?.attributes.name}
                                        </h3>
                                        <p className='text-sm text-white/70 mb-4'>
                                            Choose the specific software version for your server
                                        </p>
                                    </div>
                                    {availableEggs.length === 0 ? (
                                        <div className='text-white/50 text-sm'>No eggs available for this nest.</div>
                                    ) : (
                                        <div className='grid grid-cols-1 md:grid-cols-2 gap-3'>
                                            {availableEggs.map((egg) => (
                                                <button
                                                    key={egg.attributes.id}
                                                    onClick={() => handleEggSelection(egg.attributes.id)}
                                                    className={`p-4 rounded-lg border transition-all text-left ${
                                                        selectedEgg === egg.attributes.id
                                                            ? 'border-[#ffffff30] bg-[#ffffff10]'
                                                            : 'border-[#ffffff12] bg-[#ffffff05] hover:border-[#ffffff20]'
                                                    }`}
                                                >
                                                    <div className='font-semibold text-white mb-1'>
                                                        {egg.attributes.name}
                                                    </div>
                                                    {egg.attributes.description && (
                                                        <div className='text-sm text-white/60'>
                                                            {egg.attributes.description}
                                                        </div>
                                                    )}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </>
                    ) : (
                        <>
                            {/* Distribution Selection */}
                            <div>
                                <label className='block text-sm font-medium text-white/70 mb-3'>
                                    Select Distribution
                                </label>
                                {!distributions || distributions.length === 0 ? (
                                    <div className='text-white/50 text-sm'>
                                        {distributionsLoading
                                            ? 'Loading distributions...'
                                            : 'No distributions available. Please contact support.'}
                                    </div>
                                ) : (
                                    <div className='grid grid-cols-1 md:grid-cols-2 gap-3'>
                                        {distributions.map((dist) => (
                                            <button
                                                key={dist.id}
                                                onClick={() => setSelectedDistribution(dist.id)}
                                                className={`p-4 rounded-lg border transition-all text-left ${
                                                    selectedDistribution === dist.id
                                                        ? 'border-[#ffffff30] bg-[#ffffff10]'
                                                        : 'border-[#ffffff12] bg-[#ffffff05] hover:border-[#ffffff20]'
                                                }`}
                                            >
                                                <div className='font-semibold text-white mb-1'>{dist.name}</div>
                                                {dist.description && (
                                                    <div className='text-sm text-white/60'>{dist.description}</div>
                                                )}
                                                {dist.version && (
                                                    <div className='text-xs text-white/40 mt-1'>
                                                        Version: {dist.version}
                                                    </div>
                                                )}
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </>
                    )}

                    {/* Action Buttons */}
                    <div className='flex gap-4 pt-4'>
                        <ActionButton variant='secondary' size='lg' onClick={handleBack}>
                            {hostingType === 'game-server' && currentStep === 'select-egg'
                                ? 'Back to Game Types'
                                : 'Back'}
                        </ActionButton>
                        {hostingType === 'game-server' && currentStep === 'select-egg' && (
                            <ActionButton
                                variant='primary'
                                size='lg'
                                onClick={handleContinue}
                                disabled={!selectedNest || !selectedEgg}
                                className='flex-1'
                            >
                                Continue to Billing
                            </ActionButton>
                        )}
                        {hostingType === 'vps' && (
                            <ActionButton
                                variant='primary'
                                size='lg'
                                onClick={handleContinue}
                                disabled={!selectedDistribution}
                                className='flex-1'
                            >
                                Continue to Billing
                            </ActionButton>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default HostingConfigureContainer;
