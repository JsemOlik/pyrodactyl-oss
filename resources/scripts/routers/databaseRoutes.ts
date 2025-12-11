import type { ComponentType } from 'react';
import { lazy } from 'react';

// Lazy load database dashboard components
const DatabaseOverviewContainer = lazy(
    () => import('@/components/server/database-dashboard/DatabaseOverviewContainer'),
);
const DatabaseListContainer = lazy(
    () => import('@/components/server/database-dashboard/DatabaseListContainer'),
);
const TableBrowserContainer = lazy(
    () => import('@/components/server/database-dashboard/TableBrowserContainer'),
);
const QueryInterfaceContainer = lazy(
    () => import('@/components/server/database-dashboard/QueryInterfaceContainer'),
);
const DatabaseLogsContainer = lazy(
    () => import('@/components/server/database-dashboard/DatabaseLogsContainer'),
);
const DatabaseSettingsContainer = lazy(
    () => import('@/components/server/database-dashboard/DatabaseSettingsContainer'),
);

interface DatabaseRouteDefinition {
    route: string;
    path?: string;
    name: string | undefined;
    component: ComponentType;
    permission?: string | string[];
    end?: boolean;
}

const databaseRoutes: DatabaseRouteDefinition[] = [
    {
        route: '',
        path: '',
        permission: null,
        name: 'Overview',
        component: DatabaseOverviewContainer,
        end: true,
    },
    {
        route: 'databases/*',
        path: 'databases',
        permission: 'database.*',
        name: 'Databases',
        component: DatabaseListContainer,
    },
    {
        route: 'tables/*',
        path: 'tables',
        permission: 'database.*',
        name: 'Tables',
        component: TableBrowserContainer,
    },
    {
        route: 'query/*',
        path: 'query',
        permission: 'database.*',
        name: 'Query',
        component: QueryInterfaceContainer,
    },
    {
        route: 'logs/*',
        path: 'logs',
        permission: 'database.*',
        name: 'Logs',
        component: DatabaseLogsContainer,
    },
    {
        route: 'settings/*',
        path: 'settings',
        permission: 'database.*',
        name: 'Settings',
        component: DatabaseSettingsContainer,
    },
];

export default databaseRoutes;
