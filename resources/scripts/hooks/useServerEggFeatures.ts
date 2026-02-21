/**
 * resources/scripts/hooks/useServerEggFeatures.ts
 *
 * Hook to normalize and expose server/egg features and metadata to dashboard components.
 *
 * Responsibilities:
 * - Normalize feature flags (egg-level or inherited).
 * - Provide helpers to check feature presence.
 * - Expose egg metadata (docker image, variables) in a consistent shape.
 * - Provide allocation helpers (useful for multi-port engines like RethinkDB).
 *
 * Notes:
 * - The project's ServerContext may expose egg information in multiple shapes (UUID or full egg object).
 *   This hook defensively checks for both patterns.
 * - If an egg does not declare features (null/undefined), a sensible default set is returned so
 *   the database dashboard continues to function for existing eggs.
 */

import { useMemo } from 'react';
import { ServerContext } from '@/state/server';

type EggLike = {
    id?: number | string;
    uuid?: string;
    name?: string;
    author?: string;
    description?: string;
    docker_images?: Record<string, string> | null;
    variables?: Array<Record<string, any>> | null;
    features?: string[] | null;
    [k: string]: any;
};

type ServerLike = {
    uuid?: string;
    id?: number | string;
    name?: string;
    egg?: EggLike | string | null;
    eggFeatures?: string[] | null; // from transformer: inherited features
    egg_features?: string[] | null;
    featureLimits?: {
        databases?: number | null;
        backups?: number | null;
        allocations?: number | null;
        backupStorageMb?: number | null;
    };
    allocations?: Array<{ id: number; ip: string; port: number; alias?: string | null; notes?: string | null }>;
    variables?: Array<{ env_variable?: string; server_value?: string; default_value?: string; [k: string]: any }>;
    dashboard_type?: string;
    [k: string]: any;
};

const DEFAULT_DB_FEATURES = [
    'databases',
    'query-interface',
    'table-browser',
    'config-editing',
    'backups',
];

export default function useServerEggFeatures() {
    const server = ServerContext.useStoreState((s: any) => s.server.data) as ServerLike | undefined | null;

    const normalized = useMemo(() => {
        const fallback = {
            features: DEFAULT_DB_FEATURES.slice(),
            egg: undefined as EggLike | undefined,
            dockerImage: undefined as string | undefined,
            variables: [] as Array<Record<string, any>>,
            allocations: [] as Array<{ id: number; ip: string; port: number; alias?: string | null; notes?: string | null }>,
            dashboardType: server?.dashboard_type ?? undefined,
            featureLimits: server?.featureLimits ?? (server?.feature_limits as any) ?? {},
        };

        if (!server) {
            return fallback;
        }

        // Try to resolve egg object (ServerTransformer may provide `egg` as uuid or as object include)
        let eggObj: EggLike | undefined;
        if (server.egg && typeof server.egg === 'object') {
            eggObj = server.egg as EggLike;
        } else if ((server as any).egg && typeof (server as any).egg === 'string') {
            // egg is a UUID string only; no egg attributes available
            eggObj = undefined;
        } else {
            // try other shapes
            eggObj = undefined;
        }

        // Features resolution:
        // - Prefer `server.eggFeatures` (ServerTransformer provides `egg_features` / `eggFeatures`).
        // - Then `eggObj.features`.
        // - Otherwise fallback to defaults.
        const featuresFromServer =
            (server as any).eggFeatures ??
            (server as any).egg_features ??
            (eggObj && (eggObj.features as string[] | null)) ??
            null;

        const features = Array.isArray(featuresFromServer) && featuresFromServer.length > 0 ? featuresFromServer : DEFAULT_DB_FEATURES.slice();

        // Docker image hint (prefer server-level docker image, then egg-level docker_images)
        let dockerImage: string | undefined;
        if ((server as any).dockerImage) {
            dockerImage = (server as any).dockerImage;
        } else if (eggObj && eggObj.docker_images) {
            // pick the first value
            const images = Object.values(eggObj.docker_images);
            dockerImage = images.length > 0 ? images[0] : undefined;
        }

        // Variables: combine server.variables (runtime/per-server) with egg variables if available
        const variables: Array<Record<string, any>> = [];
        if (Array.isArray(server.variables)) {
            // server.variables items typically include env_variable, server_value/default_value depending on transformer
            server.variables.forEach((v: any) => variables.push(v));
        } else if (eggObj && Array.isArray((eggObj as any).variables)) {
            (eggObj as any).variables.forEach((v: any) => variables.push(v));
        }

        // Allocations: from server.allocations (already normalized by transformer)
        const allocations = Array.isArray(server.allocations) ? server.allocations : [];

        return {
            features,
            egg: eggObj,
            dockerImage,
            variables,
            allocations,
            dashboardType: server.dashboard_type ?? server.dashboardType ?? undefined,
            featureLimits: server.featureLimits ?? {},
            rawServer: server,
        };
    }, [server]);

    /**
     * Check whether a given feature is present for the server's egg.
     * If features are not explicitly listed, defaults are used (see DEFAULT_DB_FEATURES).
     */
    function hasFeature(feature: string): boolean {
        return normalized.features.includes(feature);
    }

    /**
     * Get a variable by its env variable name.
     * Looks in server-provided variables first, then egg variables if present.
     */
    function getVariable(envVar: string): string | undefined {
        const v = normalized.variables.find((x: any) => {
            // Support multiple potential property names depending on transformer:
            // - env_variable
            // - envVar
            // - name
            const key = x.env_variable ?? x.envVar ?? x.name ?? null;
            return key === envVar;
        });

        if (!v) return undefined;

        // value may be on different properties based on transformer/API shape
        return v.server_value ?? v.value ?? v.default_value ?? v.defaultValue ?? v.env_value ?? undefined;
    }

    /**
     * Helper to obtain the "primary" allocation (first/default).
     */
    function getPrimaryAllocation(): { id: number; ip: string; port: number; alias?: string | null; notes?: string | null } | undefined {
        if (!normalized.allocations || normalized.allocations.length === 0) return undefined;
        // Try to find the default allocation (isDefault flag) else return first
        const def = normalized.allocations.find((a: any) => a.isDefault || a.is_default);
        return def ?? normalized.allocations[0];
    }

    /**
     * For multi-port DB engines (RethinkDB), attempt to map the server allocations into named ports:
     * - If there are 3 or more allocations and egg indicates multi-port support, return labels:
     *   { cluster: allocations[0], driver: allocations[1], http: allocations[2] }
     * - Otherwise return an array of allocations.
     *
     * This is a heuristic; eggs that need precise mapping should provide a `features` flag
     * (e.g. "multi-port") and/or documented variable names for each port.
     */
    function getAllocationMap(): { cluster?: any; driver?: any; http?: any; allocations: any[] } {
        const allocs = normalized.allocations || [];
        const result: any = { allocations: allocs };

        if (hasFeature('multi-port') && allocs.length >= 3) {
            result.cluster = allocs[0];
            result.driver = allocs[1];
            result.http = allocs[2];
        }

        return result;
    }

    return {
        // Data
        features: normalized.features,
        egg: normalized.egg,
        dockerImage: normalized.dockerImage,
        variables: normalized.variables,
        allocations: normalized.allocations,

        // Helpers
        hasFeature,
        getVariable,
        getPrimaryAllocation,
        getAllocationMap,

        // Misc
        dashboardType: normalized.dashboardType,
        featureLimits: normalized.featureLimits,
        rawServer: (normalized as any).rawServer,
    };
}
