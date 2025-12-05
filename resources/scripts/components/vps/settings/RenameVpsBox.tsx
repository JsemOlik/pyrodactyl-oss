import { useState } from 'react';
import { toast } from 'sonner';

import ActionButton from '@/components/elements/ActionButton';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { Input } from '@/components/elements/inputs';

import { VpsContext } from '@/state/vps';
import http from '@/api/http';
import { httpErrorToHuman } from '@/api/http';

const RenameVpsBox = () => {
    const vps = VpsContext.useStoreState((state) => state.vps.data);
    const setVps = VpsContext.useStoreActions((actions) => actions.vps.setVps);
    const setVpsFromState = VpsContext.useStoreActions((actions) => actions.vps.setVpsFromState);

    const [name, setName] = useState(vps?.name || '');
    const [description, setDescription] = useState(vps?.description || '');
    const [isSubmitting, setIsSubmitting] = useState(false);

    if (!vps) {
        return null;
    }

    const onSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        try {
            // TODO: Create API endpoint for updating VPS name/description
            // For now, we'll just update the local state
            // await http.put(`/api/client/vps-servers/${vps.uuid}`, { name, description });

            setVpsFromState((current) => ({
                ...current,
                name,
                description: description || null,
            }));

            toast.success('VPS settings updated successfully');
        } catch (error: any) {
            toast.error(httpErrorToHuman(error) || 'Failed to update VPS settings');
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <TitledGreyBox title={'VPS Details'}>
            <form onSubmit={onSubmit}>
                <div className='mb-4'>
                    <label htmlFor='vps-name' className='block text-sm font-medium mb-2'>
                        VPS Name
                    </label>
                    <Input
                        id='vps-name'
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder='My VPS Server'
                        maxLength={191}
                        required
                    />
                </div>
                <div className='mb-4'>
                    <label htmlFor='vps-description' className='block text-sm font-medium mb-2'>
                        Description (Optional)
                    </label>
                    <Input
                        id='vps-description'
                        value={description}
                        onChange={(e) => setDescription(e.target.value)}
                        placeholder='A brief description of this VPS'
                        maxLength={500}
                    />
                </div>
                <ActionButton type='submit' loading={isSubmitting}>
                    Save Changes
                </ActionButton>
            </form>
        </TitledGreyBox>
    );
};

export default RenameVpsBox;

