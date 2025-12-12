import { Suspense, lazy, useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';

import { NotFound, ServerError } from '@/components/elements/ScreenBlock';
import Spinner from '@/components/elements/Spinner';

import { httpErrorToHuman } from '@/api/http';

import { ServerContext } from '@/state/server';

const ServerRouter = lazy(() => import('@/routers/ServerRouter'));
const DatabaseRouter = lazy(() => import('@/routers/DatabaseRouter'));
// const WebsiteRouter = lazy(() => import('@/routers/WebsiteRouter'));
// const S3StorageRouter = lazy(() => import('@/routers/S3StorageRouter'));
// const VpsRouter = lazy(() => import('@/routers/VpsRouter'));

/**
 * DashboardRouterFactory determines which dashboard router to render
 * based on the server's dashboard_type.
 *
 * This component loads the server data using ServerContext (same as ServerRouter)
 * and then routes to the appropriate dashboard based on dashboard_type.
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

    // Map dashboard types to their respective router components
    const renderRouter = () => {
        switch (dashboardType) {
            case 'game-server':
                return (
                    <Suspense fallback={<Spinner />}>
                        <ServerRouter />
                    </Suspense>
                );

            case 'database':
                return (
                    <Suspense fallback={<Spinner />}>
                        <DatabaseRouter />
                    </Suspense>
                );

            case 'website':
                // TODO: Implement WebsiteRouter in future phase
                return <NotFound />;

            case 's3-storage':
                // TODO: Implement S3StorageRouter in future phase
                return <NotFound />;

            case 'vps':
                // VpsRouter already exists, but uses different route structure
                // For now, return NotFound - VPS servers use /vps-server/:id/* route
                return <NotFound />;

            default:
                // Default to game-server dashboard for unknown types
                return (
                    <Suspense fallback={<Spinner />}>
                        <ServerRouter />
                    </Suspense>
                );
        }
    };

    return <>{renderRouter()}</>;
};

export default DashboardRouterFactory;
