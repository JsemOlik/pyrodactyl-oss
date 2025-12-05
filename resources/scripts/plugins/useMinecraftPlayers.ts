import { useEffect, useState } from 'react';

import { ServerContext } from '@/state/server';

interface McSrvStatResponse {
    online: boolean;
    players?: {
        online?: number;
        max?: number;
        list?: Array<{ name?: string; uuid?: string }>;
    };
}

interface PlayerData {
    online: number;
    max: number;
    players: Array<{ name: string }>;
}

const FETCH_INTERVAL = 2 * 60 * 1000; // 2 minutes in milliseconds

export const useMinecraftPlayers = () => {
    const serverData = ServerContext.useStoreState((state) => state.server.data);
    const [playerData, setPlayerData] = useState<PlayerData | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

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

        // Use alias if available, otherwise fall back to IP
        const serverAddress = defaultAllocation.alias || defaultAllocation.ip;

        setLoading(true);
        setError(null);

        try {
            // Use mcsrvstat.us API to get player list
            const response = await fetch(`https://api.mcsrvstat.us/3/${serverAddress}:${defaultAllocation.port}`);

            if (!response.ok) {
                throw new Error('Failed to fetch server status');
            }

            const data: McSrvStatResponse = await response.json();

            if (!data.online) {
                setPlayerData(null);
                setError('Server is offline');
            } else {
                const players = (data.players?.list || [])
                    .map((player) => ({ name: player.name || '' }))
                    .filter((player) => player.name.length > 0);

                setPlayerData({
                    online: data.players?.online || 0,
                    max: data.players?.max || 0,
                    players,
                });
                setError(null);
            }
        } catch (err: any) {
            setError(err.message || 'Failed to fetch player list');
            setPlayerData(null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!serverData) return;

        // Fetch immediately
        fetchPlayers();

        // Set up interval to fetch every 2 minutes
        const interval = setInterval(() => {
            fetchPlayers();
        }, FETCH_INTERVAL);

        return () => clearInterval(interval);
    }, [serverData]);

    return { playerData, loading, error, refetch: fetchPlayers };
};
