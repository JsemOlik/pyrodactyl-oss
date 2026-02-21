import { Suspense, lazy, useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';

import { getDashboardEntry } from '@/routers/dashboardRegistry';

import { NotFound, ServerError } from '@/components/elements/ScreenBlock';
import Spinner from '@/components/elements/Spinner';

import { httpErrorToHuman } from '@/api/http';

import { ServerContext } from '@/state/server';

const ServerRouter = lazy(() => import('@/routers/ServerRouter'));

/**
 * DashboardRouterFactory determines which dashboard router to render
 * based on the server's dashboard_type.
 *
 * This component loads the server data using ServerContext (same as ServerRouter)
 * and then routes to the appropriate dashboard based on dashboard_type.
 *
 * It's been refactored to consult the centralized dashboard registry so that
 * new dashboard types can be added by registering them in `dashboardRegistry.ts`.
 */
const DashboardRouterFactory = () => {
    const params = useParams<'id'>();
    const [error, setError] = useState('');

    const serverData = ServerContext.useStoreState((state) => state.server.data);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const clearServerState = ServerContext.useStoreActions((actions) => actions.clearServerState);

    // Load server data (same logic as ServerRouter)
    useEffect(() => {
        setError('');

        if (params.id === undefined) {
            return;
        }

        getServer(params.id).catch((error) => {
            console.error(error);
            setError(httpErrorToHuman(error));
        });

        return () => {
            clearServerState();
        };
    }, [params.id, getServer, clearServerState]);

    if (error) {
        return <ServerError title='Something went wrong' message={error} />;
    }

    if (!serverData) {
        return <Spinner />;
    }

    // dashboard_type should be available from the ServerTransformer
    // Default to 'game-server' if not set (shouldn't happen, but safe fallback)
    const dashboardType = serverData.dashboard_type || 'game-server';

    // Debug logging (remove in production)
    if (process.env.NODE_ENV === 'development') {
        console.log('DashboardRouterFactory - Server Data:', {
            uuid: serverData.uuid,
            name: serverData.name,
            dashboard_type: serverData.dashboard_type,
            nest: serverData.nest,
            egg: serverData.egg,
        });
    }

    // Look up a registry entry for this dashboard type and render it if present.
    const entry = getDashboardEntry(dashboardType);

    if (entry && entry.router) {
        // Cast to `any` to avoid JSX typing issues when rendering lazy-loaded routers
        const RouterComponent: any = entry.router;
        return (
            <Suspense fallback={<Spinner />}>
                <RouterComponent />
            </Suspense>
        );
    }

    // Fallback behavior for unknown dashboard types:
    // keep existing behavior of rendering the game-server router so legacy servers continue to work.
    // If you prefer a NotFound, change this behavior.
    return (
        <Suspense fallback={<Spinner />}>
            <ServerRouter />
        </Suspense>
    );
};

export default DashboardRouterFactory;
