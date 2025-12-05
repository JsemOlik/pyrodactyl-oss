import { Person } from '@gravity-ui/icons';
import { useEffect, useRef, useState } from 'react';

import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import { SocketEvent } from '@/components/server/events';

import sendCommand from '@/api/server/sendCommand';

import { ServerContext } from '@/state/server';

import useWebsocketEvent from '@/plugins/useWebsocketEvent';

interface Player {
    name: string;
}

const PlayersContainer = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const connected = ServerContext.useStoreState((state) => state.socket.connected);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    const serverStatus = ServerContext.useStoreState((state) => state.server.data?.status);

    const [players, setPlayers] = useState<Player[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [isListening, setIsListening] = useState(false);
    const consoleLinesRef = useRef<string[]>([]);
    const timeoutRef = useRef<NodeJS.Timeout | null>(null);

    const parseListCommand = (lines: string[]): Player[] => {
        const players: Player[] = [];

        // Look for the line that contains player names
        // Format: "default: ookmot, tms911" or "world: player1, player2"
        // The line typically comes after "players online" message
        for (const line of lines) {
            // Remove ANSI color codes and timestamps like [22:07:44 INFO]:
            const cleanLine = line
                .replace(/\[.*?\]/g, '') // Remove timestamps
                .replace(/\u001b\[[0-9;]*m/g, '') // Remove ANSI codes
                .trim();

            // Match pattern like "default: player1, player2" or "world: player1, player2"
            // Also handle cases where it's just "player1, player2" without world prefix
            const playerLineMatch =
                cleanLine.match(/(?:default|world|.*?):\s*([a-zA-Z0-9_]+(?:\s*,\s*[a-zA-Z0-9_]+)*)/i) ||
                cleanLine.match(/^([a-zA-Z0-9_]+(?:\s*,\s*[a-zA-Z0-9_]+)*)$/);

            if (playerLineMatch) {
                const playerNamesStr = playerLineMatch[1] || playerLineMatch[0];
                const playerNames = playerNamesStr
                    .split(',')
                    .map((name) => name.trim())
                    .filter((name) => name.length > 0 && !name.toLowerCase().includes('players online'));

                playerNames.forEach((name) => {
                    if (name.length > 0) {
                        players.push({ name });
                    }
                });

                // Found players, return early
                if (players.length > 0) {
                    return players;
                }
            }
        }

        return players;
    };

    const fetchPlayers = async () => {
        if (!connected || !instance || !uuid) {
            setError('Server connection not available. Make sure the server is online and connected.');
            return;
        }

        // Check if server is actually running (status should be null when running)
        if (serverStatus !== null) {
            setError('Server must be running to check player list. Please start your server first.');
            return;
        }

        // Clear any existing timeout
        if (timeoutRef.current) {
            clearTimeout(timeoutRef.current);
        }

        setLoading(true);
        setError(null);
        setPlayers([]);
        consoleLinesRef.current = [];
        setIsListening(true);

        try {
            // Send the /list command
            await sendCommand(uuid, 'list');

            // Set a timeout to stop listening after 8 seconds
            timeoutRef.current = setTimeout(() => {
                setIsListening(false);
                const parsedPlayers = parseListCommand(consoleLinesRef.current);

                if (parsedPlayers.length > 0) {
                    setPlayers(parsedPlayers);
                    setError(null);
                } else if (consoleLinesRef.current.length > 0) {
                    // We got console output but couldn't parse players
                    setError('No players found online or unable to parse response');
                } else {
                    setError('Server did not respond. Make sure the server is online.');
                }

                setLoading(false);
            }, 8000);
        } catch (err: any) {
            setError(err.message || 'Failed to send command');
            setLoading(false);
            setIsListening(false);
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        }
    };

    // Listen for console output
    useWebsocketEvent(
        SocketEvent.CONSOLE_OUTPUT,
        (line: string) => {
            if (!isListening) return;

            // Collect console lines
            consoleLinesRef.current = [...consoleLinesRef.current, line];

            // Try to parse players from accumulated lines
            const parsedPlayers = parseListCommand(consoleLinesRef.current);
            if (parsedPlayers.length > 0) {
                setPlayers(parsedPlayers);
                setIsListening(false);
                setLoading(false);
                setError(null);
                if (timeoutRef.current) {
                    clearTimeout(timeoutRef.current);
                    timeoutRef.current = null;
                }
            }
        },
        [isListening],
    );

    // Cleanup timeout on unmount
    useEffect(() => {
        return () => {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        };
    }, []);

    useEffect(() => {
        if (connected && instance && serverStatus === null) {
            fetchPlayers();
        }
    }, [connected, instance, serverStatus]);

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
                        disabled={loading || !connected}
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
