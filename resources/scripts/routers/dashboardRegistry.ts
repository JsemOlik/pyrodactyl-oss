import { type LazyExoticComponent, type ReactElement, lazy } from 'react';

/**
 * Dashboard registry
 *
 * Centralized mapping of dashboard_type -> lazy router component and metadata.
 *
 * Important:
 * - Use explicit lazy imports so Vite can statically analyze chunks.
 * - Keep keys stable (these are stored on eggs/nests in the database).
 */

export type DashboardKey = 'game-server' | 'database' | 'docker' | string;

export interface DashboardRegistryEntry {
    /**
     * Human readable label for admin UIs / debugging.
     */
    label: string;

    /**
     * Lazy-loaded router component for this dashboard.
     * Should default-export a Router component that follows the existing
     * router contract (uses ServerContext, renders MainSidebar/MainWrapper, etc).
     */
    router: LazyExoticComponent<any>;

    /**
     * Optional icon element for admin lists. Kept optional so we don't force
     * a JSX dependency or specific type in this registry file.
     */
    icon?: ReactElement | null;
}

/**
 * Registry map.
 *
 * Add new dashboard types here. Don’t attempt to dynamically import by
 * runtime string: Vite needs explicit imports so chunks are generated.
 */
export const dashboardRegistry: Record<string, DashboardRegistryEntry> = {
    'game-server': {
        label: 'Game Server',
        router: lazy(() => import('@/routers/ServerRouter')),
    },

    database: {
        label: 'Database',
        router: lazy(() => import('@/routers/DatabaseRouter')),
    },

    // Placeholder for container-style dashboards (optional).
    // Keep this entry even if the router file is not implemented yet;
    // DashboardRouterFactory should guard against missing entries at runtime.
    docker: {
        label: 'Container',
        router: lazy(() => import('@/routers/DockerRouter')),
    },
};

/**
 * Helper: get registry entry for a dashboard type.
 * Returns undefined if not found.
 */
export const getDashboardEntry = (key?: string): DashboardRegistryEntry | undefined => {
    if (!key) return undefined;
    return dashboardRegistry[key];
};

/**
 * Helper: whether the registry contains a given dashboard type.
 */
export const hasDashboard = (key?: string): boolean => {
    if (!key) return false;
    return Object.prototype.hasOwnProperty.call(dashboardRegistry, key);
};

export default dashboardRegistry;
