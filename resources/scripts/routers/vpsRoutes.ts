import type { ComponentType } from 'react';
import { lazy } from 'react';

// VPS-specific containers will be created in Phase 8
// These imports will fail until Phase 8 components are created
// @ts-expect-error - Components will be created in Phase 8
const VpsOverviewContainer = lazy(() => import('@/components/vps/VpsOverviewContainer'));
// @ts-expect-error - Components will be created in Phase 8
const VpsMetricsContainer = lazy(() => import('@/components/vps/VpsMetricsContainer'));
// @ts-expect-error - Components will be created in Phase 8
const VpsSettingsContainer = lazy(() => import('@/components/vps/VpsSettingsContainer'));
// @ts-expect-error - Components will be created in Phase 8
const VpsActivityContainer = lazy(() => import('@/components/vps/VpsActivityContainer'));

interface RouteDefinition {
    route: string;
    path?: string;
    name: string | undefined;
    component: ComponentType;
    end?: boolean;
}

// VPS routes don't need permissions since ownership is checked at API level
type VpsRouteDefinition = RouteDefinition;

interface VpsRoutes {
    vps: VpsRouteDefinition[];
}

export default {
    vps: [
        {
            route: '',
            path: '',
            name: 'Overview',
            component: VpsOverviewContainer,
            end: true,
        },
        {
            route: 'metrics',
            path: 'metrics',
            name: 'Metrics',
            component: VpsMetricsContainer,
        },
        {
            route: 'settings',
            path: 'settings',
            name: 'Settings',
            component: VpsSettingsContainer,
        },
        {
            route: 'activity',
            path: 'activity',
            name: 'Activity',
            component: VpsActivityContainer,
        },
    ],
} as VpsRoutes;
