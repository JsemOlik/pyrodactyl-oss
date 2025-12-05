import { Person } from '@gravity-ui/icons';
import { useEffect, useState } from 'react';

import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';

import { ServerContext } from '@/state/server';

interface Player {
    name: string;
}

interface McSrvStatResponse {
    online: boolean;
    players?: {
        online?: number;
        max?: number;
        list?: Array<{ name?: string; uuid?: string }>;
    };
}

const PlayersContainer = () => {
    const serverData = ServerContext.useStoreState((state) => state.server.data);
    const [players, setPlayers] = useState<Player[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [hasFetched, setHasFetched] = useState(false);

    const fetchPlayers = async () => {
        if (!serverData) {
            setError('Server data not available');
            return;
        }

        // Get the default allocation (primary IP and port)
        const defaultAllocation = serverData.allocations?.find((alloc) => alloc.isDefault);
        if (!defaultAllocation) {
            setError('Server allocation not found');
            return;
        }

        setLoading(true);
        setError(null);

        try {
            // Use mcsrvstat.us API to get player list
            const response = await fetch(
                `https://api.mcsrvstat.us/3/${defaultAllocation.ip}:${defaultAllocation.port}`,
            );

            if (!response.ok) {
                throw new Error('Failed to fetch server status');
            }

            const data: McSrvStatResponse = await response.json();

            if (!data.online) {
                setError('Server is offline');
                setPlayers([]);
            } else if (data.players?.list && data.players.list.length > 0) {
                // Extract player names from the API response
                const playerNames = data.players.list
                    .map((player) => player.name)
                    .filter((name): name is string => !!name)
                    .map((name) => ({ name }));
                setPlayers(playerNames);
                setError(null);
            } else {
                setPlayers([]);
                setError(null);
            }
        } catch (err: any) {
            setError(err.message || 'Failed to fetch player list');
            setPlayers([]);
        } finally {
            setLoading(false);
        }
    };

    // Fetch players once on component mount
    useEffect(() => {
        if (!hasFetched && serverData) {
            setHasFetched(true);
            fetchPlayers();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [serverData, hasFetched]);

    const getMinecraftHeadUrl = (username: string): string => {
        // Using mc-heads.net API
        return `https://mc-heads.net/avatar/${username}/32`;
    };

    return (
        <ServerContentBlock title={'Players'} showFlashKey={'players'}>
            <ErrorBoundary>
                <MainPageHeader
                    title={'Players'}
                    description={'View players currently online on your Minecraft server.'}
                >
                    <button
                        onClick={fetchPlayers}
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

                    {loading && players.length === 0 ? (
                        <div className='flex items-center justify-center py-12'>
                            <Spinner size='large' />
                        </div>
                    ) : players.length === 0 ? (
                        <div className='text-center py-12 text-neutral-400'>
                            <Person width={48} height={48} className='mx-auto mb-4 opacity-50' />
                            <p>No players online</p>
                        </div>
                    ) : (
                        <div className='grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4'>
                            {players.map((player) => (
                                <div
                                    key={player.name}
                                    className='flex items-center gap-3 p-4 bg-[#ffffff09] border border-[#ffffff12] rounded-lg hover:border-[#ffffff20] transition-colors'
                                >
                                    <img
                                        src={getMinecraftHeadUrl(player.name)}
                                        alt={player.name}
                                        className='w-8 h-8 rounded'
                                        onError={(e) => {
                                            // Fallback to a default avatar if image fails to load
                                            (e.target as HTMLImageElement).src =
                                                `https://mc-heads.net/avatar/8667ba71-b85a-4004-af54-457acd50f086/32`;
                                        }}
                                    />
                                    <span className='font-medium text-white'>{player.name}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </ErrorBoundary>
        </ServerContentBlock>
    );
};

export default PlayersContainer;
