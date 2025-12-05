import { Person } from '@gravity-ui/icons';
import { useState } from 'react';

import ActionButton from '@/components/elements/ActionButton';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import { Dialog } from '@/components/elements/dialog';

import sendCommand from '@/api/server/sendCommand';

import { ServerContext } from '@/state/server';

import { useMinecraftPlayers } from '@/plugins/useMinecraftPlayers';

const PlayersContainer = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { playerData, loading, error, refetch } = useMinecraftPlayers();
    const [searchTerm, setSearchTerm] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [modalAction, setModalAction] = useState<'kick' | 'ban' | null>(null);
    const [modalPlayer, setModalPlayer] = useState<string | null>(null);
    const [modalReason, setModalReason] = useState('');
    const [modalLoading, setModalLoading] = useState(false);

    const players = playerData?.players || [];

    const getMinecraftHeadUrl = (username: string): string => {
        // Using mc-heads.net API
        return `https://mc-heads.net/avatar/${username}/64`;
    };

    const filteredPlayers = players.filter((player) => player.name.toLowerCase().includes(searchTerm.toLowerCase()));

    const handleKickBan = (playerName: string, action: 'kick' | 'ban') => {
        setModalPlayer(playerName);
        setModalAction(action);
        setModalReason('');
        setModalOpen(true);
    };

    const executeKickBan = async () => {
        if (!modalPlayer || !modalAction || !uuid) return;

        setModalLoading(true);
        const reason = modalReason.trim() || 'No reason provided';
        const command = `${modalAction} ${modalPlayer} ${reason}`;

        try {
            await sendCommand(uuid, command);
            setModalOpen(false);
            setModalPlayer(null);
            setModalAction(null);
            setModalReason('');
            // Refresh player list after a short delay
            setTimeout(() => {
                refetch();
            }, 1000);
        } catch (err: any) {
            setError(err.message || `Failed to ${modalAction} player`);
        } finally {
            setModalLoading(false);
        }
    };

    return (
        <ServerContentBlock title={'Players'} showFlashKey={'players'}>
            <ErrorBoundary>
                <MainPageHeader
                    title={'Players'}
                    description={'View players currently online on your Minecraft server.'}
                >
                    <button
                        onClick={refetch}
                        disabled={loading}
                        className='px-4 py-2 bg-brand text-white rounded-lg hover:bg-brand/80 disabled:opacity-50 disabled:cursor-not-allowed transition-colors'
                    >
                        {loading ? (
                            <span className='flex items-center gap-2'>
                                <Spinner size='small' />
                                Refreshing...
                            </span>
                        ) : (
                            'Refresh'
                        )}
                    </button>
                </MainPageHeader>

                <div className='mt-6'>
                    {error && (
                        <div className='mb-4 p-4 bg-red-500/10 border border-red-500/20 rounded-lg text-red-400'>
                            {error}
                        </div>
                    )}

                    {/* Search Box */}
                    {players.length > 0 && (
                        <div className='mb-4'>
                            <input
                                type='text'
                                placeholder='Search players...'
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className='w-full px-4 py-2 rounded-lg bg-[#ffffff11] border border-[#ffffff12] text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                            />
                        </div>
                    )}

                    {loading && players.length === 0 ? (
                        <div className='flex items-center justify-center py-12'>
                            <Spinner size='large' />
                        </div>
                    ) : filteredPlayers.length === 0 ? (
                        <div className='text-center py-12 text-neutral-400'>
                            <Person width={48} height={48} className='mx-auto mb-4 opacity-50' />
                            <p>{searchTerm ? 'No players found' : 'No players online'}</p>
                        </div>
                    ) : (
                        <div className='grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4'>
                            {filteredPlayers.map((player) => (
                                <div
                                    key={player.name}
                                    className='flex items-center justify-between gap-3 p-4 bg-[#ffffff09] border border-[#ffffff12] rounded-lg hover:border-[#ffffff20] transition-colors'
                                >
                                    <div className='flex items-center gap-3 flex-1 min-w-0'>
                                        <img
                                            src={getMinecraftHeadUrl(player.name)}
                                            alt={player.name}
                                            className='w-12 h-12 rounded shrink-0'
                                            onError={(e) => {
                                                // Fallback to a default avatar if image fails to load
                                                (e.target as HTMLImageElement).src =
                                                    `https://mc-heads.net/avatar/8667ba71-b85a-4004-af54-457acd50f086/64`;
                                            }}
                                        />
                                        <span className='font-medium text-white truncate'>{player.name}</span>
                                    </div>
                                    <div className='flex items-center gap-2 shrink-0'>
                                        <ActionButton
                                            variant='secondary'
                                            size='sm'
                                            onClick={() => handleKickBan(player.name, 'kick')}
                                            className='text-xs'
                                        >
                                            Kick
                                        </ActionButton>
                                        <ActionButton
                                            variant='danger'
                                            size='sm'
                                            onClick={() => handleKickBan(player.name, 'ban')}
                                            className='text-xs'
                                        >
                                            Ban
                                        </ActionButton>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Kick/Ban Modal */}
                <Dialog
                    open={modalOpen}
                    onClose={() => {
                        if (!modalLoading) {
                            setModalOpen(false);
                            setModalPlayer(null);
                            setModalAction(null);
                            setModalReason('');
                        }
                    }}
                    title={`${modalAction === 'kick' ? 'Kick' : 'Ban'} Player`}
                    description={`Enter a reason for ${modalAction === 'kick' ? 'kicking' : 'banning'} ${modalPlayer}`}
                >
                    <form
                        id='kick-ban-form'
                        onSubmit={(e) => {
                            e.preventDefault();
                            executeKickBan();
                        }}
                        className='mt-4 space-y-4'
                    >
                        <div>
                            <label htmlFor='reason' className='block text-sm font-medium mb-2'>
                                Reason <span className='text-neutral-400'>(optional)</span>
                            </label>
                            <input
                                id='reason'
                                type='text'
                                value={modalReason}
                                onChange={(e) => setModalReason(e.target.value)}
                                placeholder='Enter reason for kick/ban...'
                                className='w-full px-4 py-2 rounded-lg bg-[#ffffff11] border border-[#ffffff12] text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent'
                                disabled={modalLoading}
                                autoFocus
                            />
                        </div>
                    </form>

                    <Dialog.Footer>
                        <ActionButton
                            variant='secondary'
                            onClick={() => {
                                setModalOpen(false);
                                setModalPlayer(null);
                                setModalAction(null);
                                setModalReason('');
                            }}
                            disabled={modalLoading}
                        >
                            Cancel
                        </ActionButton>
                        <ActionButton
                            variant={modalAction === 'ban' ? 'danger' : 'primary'}
                            onClick={executeKickBan}
                            disabled={modalLoading}
                            type='submit'
                            form='kick-ban-form'
                        >
                            <div className='flex items-center gap-2'>
                                {modalLoading && <Spinner size='small' />}
                                <span>{modalAction === 'kick' ? 'Kick' : 'Ban'} Player</span>
                            </div>
                        </ActionButton>
                    </Dialog.Footer>
                </Dialog>
            </ErrorBoundary>
        </ServerContentBlock>
    );
};

export default PlayersContainer;
