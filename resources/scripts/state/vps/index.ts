import type { Action, Computed, Thunk } from 'easy-peasy';
import { action, computed, createContextStore, thunk } from 'easy-peasy';
import isEqual from 'react-fast-compare';

import getVps from '@/api/vps/getVps';
import type { Vps } from '@/api/vps/types';

export type VpsStatus = 'offline' | 'starting' | 'stopping' | 'running' | null;

interface VpsDataStore {
    data?: Vps;
    inConflictState: Computed<VpsDataStore, boolean>;
    isInstalling: Computed<VpsDataStore, boolean>;
    isOwner: boolean;

    getVps: Thunk<VpsDataStore, string, Record<string, unknown>, VpsStore, Promise<void>>;
    setVps: Action<VpsDataStore, Vps>;
    setVpsFromState: Action<VpsDataStore, (v: Vps) => Vps>;
    setIsOwner: Action<VpsDataStore, boolean>;
}

const vps: VpsDataStore = {
    isOwner: false,

    inConflictState: computed((state) => {
        if (!state.data) {
            return false;
        }

        // VPS is in conflict state if it's creating, installing, or in error state
        return (
            state.data.status === 'creating' ||
            state.data.status === 'create_failed' ||
            state.data.status === 'starting' ||
            state.data.status === 'stopping' ||
            state.data.status === 'rebooting' ||
            state.data.status === 'error'
        );
    }),

    isInstalling: computed((state) => {
        return state.data?.status === 'creating' || state.data?.status === 'create_failed';
    }),

    getVps: thunk(async (actions, payload) => {
        const vpsData = await getVps(payload);

        actions.setVps(vpsData);
        actions.setIsOwner(true); // VPS API only returns VPSs owned by the user
    }),

    setVps: action((state, payload) => {
        if (!isEqual(payload, state.data)) {
            state.data = payload;
        }
    }),

    setVpsFromState: action((state, payload) => {
        const output = payload(state.data!);
        if (!isEqual(output, state.data)) {
            state.data = output;
        }
    }),

    setIsOwner: action((state, payload) => {
        state.isOwner = payload;
    }),
};

interface VpsStatusStore {
    value: VpsStatus;
    setVpsStatus: Action<VpsStatusStore, VpsStatus>;
}

const status: VpsStatusStore = {
    value: null,
    setVpsStatus: action((state, payload) => {
        state.value = payload;
    }),
};

export interface VpsStore {
    vps: VpsDataStore;
    status: VpsStatusStore;
    clearVpsState: Action<VpsStore>;
}

export const VpsContext = createContextStore<VpsStore>({
    vps,
    status,
    clearVpsState: action((state) => {
        state.vps.data = undefined;
        state.vps.isOwner = false;
        state.status.value = null;
    }),
});
