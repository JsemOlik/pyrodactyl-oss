import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import useSWR from 'swr';

import PageContentBlock from '@/components/elements/PageContentBlock';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import ActionButton from '@/components/elements/ActionButton';

import getNests from '@/api/nests/getNests';
import { httpErrorToHuman } from '@/api/http';

interface SelectedPlan {
    planId?: number;
    isCustom?: boolean;
    memory?: number;
    interval?: string;
}

const HostingConfigureContainer = () => {
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const { data: nests, error: nestsError, isLoading: nestsLoading } = useSWR('/api/client/nests', getNests);

    const [selectedPlan, setSelectedPlan] = useState<SelectedPlan | null>(null);
    const [selectedNest, setSelectedNest] = useState<number | null>(null);
    const [selectedEgg, setSelectedEgg] = useState<number | null>(null);

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

    const handleContinue = () => {
        if (!selectedNest || !selectedEgg) {
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
        params.set('nest', selectedNest.toString());
        params.set('egg', selectedEgg.toString());

        navigate(`/hosting/checkout?${params.toString()}`);
    };

    const handleBack = () => {
        navigate('/hosting');
    };

    if (nestsLoading) {
        return (
            <PageContentBlock title='Configure Server'>
                <div className='flex items-center justify-center min-h-[400px]'>
                    <div className='text-white/70'>Loading configuration options...</div>
                </div>
            </PageContentBlock>
        );
    }

    if (nestsError || !nests) {
        return (
            <PageContentBlock title='Configure Server'>
                <div className='flex items-center justify-center min-h-[400px]'>
                    <div className='text-red-400'>Failed to load configuration options. Please try again later.</div>
                </div>
            </PageContentBlock>
        );
    }

    const selectedNestData = nests.find((nest) => nest.attributes.id === selectedNest);
    const availableEggs = selectedNestData?.attributes.relationships?.eggs?.data || [];

    return (
        <PageContentBlock title='Configure Server'>
            <MainPageHeader title='Configure Your Server' />

            <div className='max-w-3xl space-y-6'>
                {/* Nest Selection */}
                <div>
                    <label className='block text-sm font-medium text-white/70 mb-3'>Select Game Type (Nest)</label>
                    <div className='grid grid-cols-1 md:grid-cols-2 gap-3'>
                        {nests.map((nest) => (
                            <button
                                key={nest.attributes.id}
                                onClick={() => {
                                    setSelectedNest(nest.attributes.id);
                                    setSelectedEgg(null); // Reset egg when nest changes
                                }}
                                className={`p-4 rounded-lg border transition-all text-left ${
                                    selectedNest === nest.attributes.id
                                        ? 'border-[#ffffff30] bg-[#ffffff10]'
                                        : 'border-[#ffffff12] bg-[#ffffff05] hover:border-[#ffffff20]'
                                }`}
                            >
                                <div className='font-semibold text-white mb-1'>{nest.attributes.name}</div>
                                {nest.attributes.description && (
                                    <div className='text-sm text-white/60'>{nest.attributes.description}</div>
                                )}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Egg Selection */}
                {selectedNest && (
                    <div>
                        <label className='block text-sm font-medium text-white/70 mb-3'>
                            Select Game (Egg)
                        </label>
                        {availableEggs.length === 0 ? (
                            <div className='text-white/50 text-sm'>No eggs available for this nest.</div>
                        ) : (
                            <div className='grid grid-cols-1 md:grid-cols-2 gap-3'>
                                {availableEggs.map((egg) => (
                                    <button
                                        key={egg.attributes.id}
                                        onClick={() => setSelectedEgg(egg.attributes.id)}
                                        className={`p-4 rounded-lg border transition-all text-left ${
                                            selectedEgg === egg.attributes.id
                                                ? 'border-[#ffffff30] bg-[#ffffff10]'
                                                : 'border-[#ffffff12] bg-[#ffffff05] hover:border-[#ffffff20]'
                                        }`}
                                    >
                                        <div className='font-semibold text-white mb-1'>{egg.attributes.name}</div>
                                        {egg.attributes.description && (
                                            <div className='text-sm text-white/60'>{egg.attributes.description}</div>
                                        )}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* Action Buttons */}
                <div className='flex gap-4 pt-4'>
                    <ActionButton variant='secondary' size='lg' onClick={handleBack}>
                        Back
                    </ActionButton>
                    <ActionButton
                        variant='primary'
                        size='lg'
                        onClick={handleContinue}
                        disabled={!selectedNest || !selectedEgg}
                        className='flex-1'
                    >
                        Continue to Billing
                    </ActionButton>
                </div>
            </div>
        </PageContentBlock>
    );
};

export default HostingConfigureContainer;

