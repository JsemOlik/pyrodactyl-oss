import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import { Dialog } from '@/components/elements/dialog';

import { VpsContext } from '@/state/vps';
import sendVpsPower from '@/api/vps/sendVpsPower';
import type { VpsPowerSignal } from '@/api/vps/sendVpsPower';

interface PowerButtonProps {
    className?: string;
}

const PowerButtons = ({ className }: PowerButtonProps) => {
    const [open, setOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const vps = VpsContext.useStoreState((state) => state.vps.data);
    const status = VpsContext.useStoreState((state) => state.status.value);
    const setVpsStatus = VpsContext.useStoreActions((actions) => actions.status.setVpsStatus);

    if (!vps) {
        return null;
    }

    const currentStatus = status || (vps.isRunning ? 'running' : vps.isStopped ? 'offline' : null);
    const killable = currentStatus === 'stopping';

    const handlePowerAction = async (action: VpsPowerSignal | 'kill-confirmed') => {
        if (isLoading) return;

        setIsLoading(true);
        try {
            const signal = action === 'kill-confirmed' ? 'kill' : action;
            await sendVpsPower(vps.uuid, signal);

            // Update status optimistically
            if (signal === 'start') {
                setVpsStatus('starting');
                toast.success('Your VPS is starting!');
            } else if (signal === 'restart') {
                setVpsStatus('starting');
                toast.success('Your VPS is restarting.');
            } else {
                setVpsStatus('stopping');
                toast.success('Your VPS is being stopped.');
            }

            setOpen(false);
        } catch (error: any) {
            toast.error(error?.message || 'Failed to execute power action');
        } finally {
            setIsLoading(false);
        }
    };

    const onButtonClick = (
        action: VpsPowerSignal | 'kill-confirmed',
        e: React.MouseEvent<HTMLButtonElement, MouseEvent>,
    ): void => {
        e.preventDefault();
        if (action === 'kill') {
            return setOpen(true);
        }
        handlePowerAction(action);
    };

    useEffect(() => {
        if (currentStatus === 'offline') {
            setOpen(false);
        }
    }, [currentStatus]);

    if (!currentStatus) {
        return null;
    }

    return (
        <div
            className={className}
            style={{
                animationTimingFunction:
                    'linear(0 0%, 0.01 0.8%, 0.04 1.6%, 0.161 3.3%, 0.816 9.4%, 1.046 11.9%, 1.189 14.4%, 1.231 15.7%, 1.254 17%, 1.259 17.8%, 1.257 18.6%, 1.236 20.45%, 1.194 22.3%, 1.057 27%, 0.999 29.4%, 0.955 32.1%, 0.942 33.5%, 0.935 34.9%, 0.933 36.65%, 0.939 38.4%, 1 47.3%, 1.011 49.95%, 1.017 52.6%, 1.016 56.4%, 1 65.2%, 0.996 70.2%, 1.001 87.2%, 1 100%)',
            }}
        >
            <Dialog.Confirm
                open={open}
                hideCloseIcon
                onClose={() => setOpen(false)}
                title={'Forcibly Stop VPS'}
                confirm={'Continue'}
                onConfirmed={onButtonClick.bind(this, 'kill-confirmed')}
            >
                Forcibly stopping a VPS can lead to data corruption.
            </Dialog.Confirm>
            <button
                style={
                    currentStatus === 'offline'
                        ? {
                              background:
                                  'radial-gradient(109.26% 109.26% at 49.83% 13.37%, var(--color-brand) 0%, var(--color-brand) 100%)',
                              opacity: 1,
                          }
                        : {
                              background:
                                  'radial-gradient(124.75% 124.75% at 50.01% -10.55%, rgb(36, 36, 36) 0%, rgb(20, 20, 20) 100%)',
                              opacity: 0.5,
                          }
                }
                className='px-8 py-3 border-[1px] border-[#ffffff12] rounded-l-full rounded-r-md text-sm font-bold shadow-md cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'
                disabled={currentStatus !== 'offline' || isLoading}
                onClick={onButtonClick.bind(this, 'start')}
            >
                Start
            </button>
            <button
                style={{
                    background:
                        'radial-gradient(124.75% 124.75% at 50.01% -10.55%, rgb(36, 36, 36) 0%, rgb(20, 20, 20) 100%)',
                }}
                className='px-8 py-3 border-[1px] border-[#ffffff12] rounded-none text-sm font-bold shadow-md cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'
                disabled={!currentStatus || isLoading}
                onClick={onButtonClick.bind(this, 'restart')}
            >
                Restart
            </button>
            <button
                style={
                    currentStatus === 'offline'
                        ? {
                              background:
                                  'radial-gradient(124.75% 124.75% at 50.01% -10.55%, rgb(36, 36, 36) 0%, rgb(20, 20, 20) 100%)',
                              opacity: 0.5,
                          }
                        : {
                              background:
                                  'radial-gradient(109.26% 109.26% at 49.83% 13.37%, var(--color-brand) 0%, var(--color-brand) 100%)',
                              opacity: 1,
                          }
                }
                className='px-8 py-3 border-[1px] border-[#ffffff12] rounded-r-full rounded-l-md text-sm font-bold shadow-md transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed'
                disabled={currentStatus === 'offline' || isLoading}
                onClick={onButtonClick.bind(this, killable ? 'kill' : 'stop')}
            >
                {killable ? 'Kill' : 'Stop'}
            </button>
        </div>
    );
};

export default PowerButtons;

