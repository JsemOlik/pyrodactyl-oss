export type VpsStatus =
    | 'creating'
    | 'create_failed'
    | 'running'
    | 'stopped'
    | 'starting'
    | 'stopping'
    | 'rebooting'
    | 'error'
    | 'suspended'
    | null;

export type VpsPowerState = 'offline' | 'starting' | 'running' | 'stopping';

export interface VpsLimits {
    memory: number;
    disk: number;
    cpu_cores: number;
    cpu_sockets: number;
}

export interface VpsProxmox {
    vm_id: number | null;
    node: string;
    storage: string;
}

export interface VpsNetwork {
    ip_address: string | null;
    ipv6_address: string | null;
}

export interface Vps {
    id: string;
    internalId: number | string;
    uuid: string;
    name: string;
    description: string | null;
    status: VpsStatus;
    isSuspended: boolean;
    isRunning: boolean;
    isStopped: boolean;
    isInstalled: boolean;
    limits: VpsLimits;
    proxmox: VpsProxmox;
    network: VpsNetwork;
    distribution: string;
    installedAt: Date | null;
    createdAt: Date;
    updatedAt: Date;
}

export interface VpsMetrics {
    cpu: {
        usage_percent: number;
        cores: number;
        sockets: number;
    };
    memory: {
        used_bytes: number;
        total_bytes: number;
        usage_percent: number;
    };
    disk: {
        used_bytes: number;
        total_bytes: number;
        usage_percent: number;
    };
    network: {
        rx_bytes: number;
        tx_bytes: number;
    };
    uptime: number;
    timestamp: number;
}
