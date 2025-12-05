import FlashMessageRender from '@/components/FlashMessageRender';
import CopyOnClick from '@/components/elements/CopyOnClick';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import VpsContentBlock from '@/components/elements/VpsContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';

import { VpsContext } from '@/state/vps';

import RenameVpsBox from './settings/RenameVpsBox';

const VpsSettingsContainer = () => {
    const vps = VpsContext.useStoreState((state) => state.vps.data);
    const limits = vps?.limits;
    const network = vps?.network;

    if (!vps) {
        return null;
    }

    return (
        <VpsContentBlock title={'Settings'}>
            <FlashMessageRender byKey={'vps:settings'} />
            <MainPageHeader direction='column' title={'Settings'}>
                <p className='text-sm text-neutral-400 leading-relaxed'>
                    Configure your VPS settings and view resource limits. Make changes to VPS name and description when
                    needed.
                </p>
            </MainPageHeader>

            <div className={`mb-6 md:mb-10`}>
                <RenameVpsBox />
            </div>

            <div className='w-full h-full flex flex-col gap-8'>
                <TitledGreyBox title={'Resource Limits'}>
                    <div className={`flex items-center justify-between text-sm`}>
                        <p>Memory</p>
                        <code className={`font-mono bg-zinc-900 rounded-sm py-1 px-2`}>
                            {limits?.memory} MB
                        </code>
                    </div>
                    <div className={`flex items-center justify-between mt-2 text-sm`}>
                        <p>Disk</p>
                        <code className={`font-mono bg-zinc-900 rounded-sm py-1 px-2`}>
                            {limits?.disk} MB
                        </code>
                    </div>
                    <div className={`flex items-center justify-between mt-2 text-sm`}>
                        <p>CPU Cores</p>
                        <code className={`font-mono bg-zinc-900 rounded-sm py-1 px-2`}>
                            {limits?.cpu_cores}
                        </code>
                    </div>
                    <div className={`flex items-center justify-between mt-2 text-sm`}>
                        <p>CPU Sockets</p>
                        <code className={`font-mono bg-zinc-900 rounded-sm py-1 px-2`}>
                            {limits?.cpu_sockets}
                        </code>
                    </div>
                </TitledGreyBox>

                <TitledGreyBox title={'Network Information'}>
                    {network?.ip_address && (
                        <div className={`flex items-center justify-between text-sm`}>
                            <p>IPv4 Address</p>
                            <CopyOnClick text={network.ip_address}>
                                <code className={`font-mono bg-zinc-900 rounded-sm py-1 px-2`}>
                                    {network.ip_address}
                                </code>
                            </CopyOnClick>
                        </div>
                    )}
                    {network?.ipv6_address && (
                        <div className={`flex items-center justify-between mt-2 text-sm`}>
                            <p>IPv6 Address</p>
                            <CopyOnClick text={network.ipv6_address}>
                                <code className={`font-mono bg-zinc-900 rounded-sm py-1 px-2`}>
                                    {network.ipv6_address}
                                </code>
                            </CopyOnClick>
                        </div>
                    )}
                    {!network?.ip_address && !network?.ipv6_address && (
                        <p className='text-sm text-zinc-400'>No IP addresses assigned yet.</p>
                    )}
                </TitledGreyBox>

                <TitledGreyBox title={'VPS Information'}>
                    <div className={`flex items-center justify-between text-sm`}>
                        <p>Distribution</p>
                        <code className={`font-mono bg-zinc-900 rounded-sm py-1 px-2`}>
                            {vps.distribution}
                        </code>
                    </div>
                    <div className={`flex items-center justify-between mt-2 text-sm`}>
                        <p>Proxmox Node</p>
                        <code className={`font-mono bg-zinc-900 rounded-sm py-1 px-2`}>
                            {vps.proxmox.node}
                        </code>
                    </div>
                    {vps.proxmox.vm_id && (
                        <div className={`flex items-center justify-between mt-2 text-sm`}>
                            <p>Proxmox VM ID</p>
                            <code className={`font-mono bg-zinc-900 rounded-sm py-1 px-2`}>
                                {vps.proxmox.vm_id}
                            </code>
                        </div>
                    )}
                </TitledGreyBox>

                <TitledGreyBox title={'Debug Information'}>
                    <CopyOnClick text={vps.uuid}>
                        <div className={`flex items-center justify-between text-sm`}>
                            <p>VPS ID</p>
                            <code className={`font-mono bg-zinc-900 rounded-sm py-1 px-2`}>{vps.uuid}</code>
                        </div>
                    </CopyOnClick>
                    <CopyOnClick text={vps.id}>
                        <div className={`flex items-center justify-between mt-2 text-sm`}>
                            <p>Short ID</p>
                            <code className={`font-mono bg-zinc-900 rounded-sm py-1 px-2`}>{vps.id}</code>
                        </div>
                    </CopyOnClick>
                </TitledGreyBox>
            </div>
        </VpsContentBlock>
    );
};

export default VpsSettingsContainer;

