import { useMemo } from 'react';

import type { Server as ServerAPI } from '@/api/server/getServer';

import { ServerContext } from '@/state/server';

/**
 * Egg-like minimal shape used by this hook.
 * Many eggs in seeds are JSON objects with fields like docker_images, variables, features.
 */
export type EggLike = {
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

export type AllocationLike = {
    id: number;
    ip?: string;
    address?: string;
    host?: string;
    port: number;
    alias?: string | null;
    notes?: string | null;
    isDefault?: boolean;
    is_default?: boolean;
    [k: string]: any;
};

export type UseServerEggFeaturesResult = {
    // Data
    features: string[];
    egg?: EggLike | undefined;
    dockerImage?: string | undefined;
    variables: Array<Record<string, any>>;
    allocations: AllocationLike[];

    // Helpers
    hasFeature: (feature: string) => boolean;
    getVariable: (envVar: string) => string | undefined;
    getPrimaryAllocation: () => AllocationLike | undefined;
    getAllocationMap: () => {
        cluster?: AllocationLike;
        driver?: AllocationLike;
        http?: AllocationLike;
        allocations: AllocationLike[];
    };

    // Misc
    dashboardType?: string | undefined;
    featureLimits: {
        databases?: number | null;
        allocations?: number | null;
        backups?: number | null;
        backupStorageMb?: number | null;
        [k: string]: any;
    };
    rawServer?: ServerAPI | undefined;
};

const DEFAULT_DB_FEATURES = ['databases', 'query-interface', 'table-browser', 'config-editing', 'backups'];

/**
 * Hook to normalize and expose server/egg features and metadata to dashboard components.
 *
 * - Normalizes feature flags (egg-level or inherited).
 * - Provides helpers to check feature presence.
 * - Exposes egg metadata (docker image, variables) in a consistent shape.
 * - Provides allocation helpers (useful for multi-port engines like RethinkDB).
 */
export default function useServerEggFeatures(): UseServerEggFeaturesResult {
    // ServerContext stores server data; use its store state selector.
    const server = ServerContext.useStoreState((s: any) => s.server.data) as ServerAPI | undefined | null;

    const normalized = useMemo(() => {
        const fallback = {
            features: DEFAULT_DB_FEATURES.slice(),
            egg: undefined as EggLike | undefined,
            dockerImage: undefined as string | undefined,
            variables: [] as Array<Record<string, any>>,
            allocations: [] as AllocationLike[],
            dashboardType: server?.dashboard_type ?? undefined,
            featureLimits: (server as any)?.featureLimits ?? (server as any)?.feature_limits ?? {},
            rawServer: server,
        };

        if (!server) return fallback;

        // Resolve egg object if present (sometimes egg is included fully, sometimes only UUID string).
        let eggObj: EggLike | undefined;
        if ((server as any).egg && typeof (server as any).egg === 'object') {
            eggObj = (server as any).egg as EggLike;
        } else {
            eggObj = undefined;
        }

        // Prefer server-provided egg features first (transformer exposes eggFeatures/egg_features),
        // then eggObj.features. If none found, fallback to defaults.
        const featuresFromServer =
            (server as any).eggFeatures ??
            (server as any).egg_features ??
            (eggObj && (eggObj.features as string[] | null)) ??
            null;

        const features =
            Array.isArray(featuresFromServer) && featuresFromServer.length > 0
                ? featuresFromServer
                : DEFAULT_DB_FEATURES.slice();

        // Docker image hint (prefer server-level dockerImage, then egg-level docker_images)
        let dockerImage: string | undefined;
        if ((server as any).dockerImage) {
            dockerImage = (server as any).dockerImage as string;
        } else if (eggObj && eggObj.docker_images) {
            const images = Object.values(eggObj.docker_images);
            dockerImage = images.length > 0 ? images[0] : undefined;
        }

        // Variables: prefer server.variables (runtime values) then egg variables
        const variables: Array<Record<string, any>> = [];
        if (Array.isArray((server as any).variables)) {
            (server as any).variables.forEach((v: any) => variables.push(v));
        } else if (eggObj && Array.isArray((eggObj as any).variables)) {
            (eggObj as any).variables.forEach((v: any) => variables.push(v));
        }

        // Allocations: server.allocations if available
        const allocations = Array.isArray((server as any).allocations)
            ? ((server as any).allocations as AllocationLike[])
            : [];

        return {
            features,
            egg: eggObj,
            dockerImage,
            variables,
            allocations,
            dashboardType: server.dashboard_type ?? (server as any).dashboardType ?? undefined,
            featureLimits: (server as any).featureLimits ?? {},
            rawServer: server,
        };
    }, [server]);

    function hasFeature(feature: string): boolean {
        return !!normalized.features && normalized.features.includes(feature);
    }

    function getVariable(envVar: string): string | undefined {
        const v = normalized.variables.find((x: any) => {
            const key = x.env_variable ?? x.envVar ?? x.name ?? null;
            return key === envVar;
        });

        if (!v) return undefined;
        return (
            (v.server_value as string) ??
            (v.value as string) ??
            (v.default_value as string) ??
            (v.defaultValue as string) ??
            (v.env_value as string) ??
            undefined
        );
    }

    function getPrimaryAllocation(): AllocationLike | undefined {
        if (!normalized.allocations || normalized.allocations.length === 0) return undefined;
        const def = normalized.allocations.find((a: any) => a.isDefault || a.is_default);
        return def ?? normalized.allocations[0];
    }

    function getAllocationMap(): {
        cluster?: AllocationLike;
        driver?: AllocationLike;
        http?: AllocationLike;
        allocations: AllocationLike[];
    } {
        const allocs = normalized.allocations ?? [];
        const result: {
            cluster?: AllocationLike;
            driver?: AllocationLike;
            http?: AllocationLike;
            allocations: AllocationLike[];
        } = { allocations: allocs };

        if (hasFeature('multi-port') && allocs.length >= 3) {
            result.cluster = allocs[0];
            result.driver = allocs[1];
            result.http = allocs[2];
        }

        return result;
    }

    return {
        features: normalized.features,
        egg: normalized.egg,
        dockerImage: normalized.dockerImage,
        variables: normalized.variables,
        allocations: normalized.allocations,

        hasFeature,
        getVariable,
        getPrimaryAllocation,
        getAllocationMap,

        dashboardType: normalized.dashboardType,
        featureLimits: normalized.featureLimits,
        rawServer: normalized.rawServer,
    };
}
