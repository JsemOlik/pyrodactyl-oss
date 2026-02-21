import { lazy } from 'react';

/**
 * Dashboard registry
 *
 * Centralized mapping of dashboard_type -> lazy router component and metadata.
 *
 * Important:
 * - Use explicit lazy imports so Vite can statically analyze chunks.
 * - Keep keys stable (these are stored on eggs/nests in the database).
 */

/**
 * Lightweight registry entry shape used at runtime.
 *
 * Use permissive types here so the registry remains flexible across different
 * dashboards and build setups. More specific typing can be applied by callers.
 */
export interface DashboardRegistryEntry {
    /** Human readable label for admin UIs / debugging. */
    label?: string;

    /** Lazy-loaded router component (renderable). May be undefined for placeholders. */
    router?: any;

    /** Optional icon/metadata; kept permissive. */
    icon?: any | null;
}

/**
 * Registry map.
 *
 * Add new dashboard types here. Don’t attempt to dynamically import by
 * runtime string: Vite needs explicit imports so chunks are generated.
 */
export const dashboardRegistry: Record<string, any> = {
    game-server: {
        label: 'Game Server',
        router: lazy(() => import('@/routers/ServerRouter')),
    },

    database: {
        label: 'Database',
        router: lazy(() => import('@/routers/DatabaseRouter')),
    },

    // Placeholder for container-style dashboards (optional). This entry is intentionally permissive.
    docker: {
        label: 'Container',
        // Router may not exist yet in some environments; keep as a lazy reference.
        router: lazy(() => import('@/routers/DockerRouter')),
    },
};

/**
 * Helper: get registry entry for a dashboard type.
 * Returns undefined if not found.
 */
export const getDashboardEntry = (key?: string): any => {
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
