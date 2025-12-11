import { Gear, Refresh } from '@gravity-ui/icons';
import { Form, Formik } from 'formik';
import { useState } from 'react';
import useSWR from 'swr';
import { object, string } from 'yup';

import FlashMessageRender from '@/components/FlashMessageRender';
import ActionButton from '@/components/elements/ActionButton';
import Field from '@/components/elements/Field';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';

import { httpErrorToHuman } from '@/api/http';
import getDatabaseConnectionInfo from '@/api/server/database-dashboard/getDatabaseConnectionInfo';
import getSettings, { DatabaseSettings } from '@/api/server/database-dashboard/getSettings';
import updateSettings from '@/api/server/database-dashboard/updateSettings';

import { ServerContext } from '@/state/server';

import useFlash from '@/plugins/useFlash';

const settingsSchema = object().shape({
    charset: string().max(50),
    collation: string().max(50),
});

const DatabaseSettingsContainer = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name);

    const { addError, addFlash, clearFlashes } = useFlash();
    const [isSaving, setIsSaving] = useState(false);

    const { data: connectionInfo } = useSWR(
        uuid ? [`/api/client/servers/${uuid}/database/connection`, uuid] : null,
        () => getDatabaseConnectionInfo(uuid!),
    );

    const {
        data: settings,
        error,
        isLoading,
        mutate,
    } = useSWR<DatabaseSettings>(
        uuid && connectionInfo ? [`/api/client/servers/${uuid}/database/settings`, connectionInfo.database] : null,
        () => getSettings(uuid!, connectionInfo?.database),
        {
            revalidateOnFocus: false,
            onError: (err) => {
                addError({ key: 'settings', message: httpErrorToHuman(err) || 'Failed to load settings' });
            },
        },
    );

    const handleSave = async (values: { charset?: string; collation?: string }) => {
        if (!uuid) {
            return;
        }

        clearFlashes('settings');
        setIsSaving(true);

        try {
            await updateSettings(uuid, {
                charset: values.charset || undefined,
                collation: values.collation || undefined,
                database: connectionInfo?.database,
            });
            addFlash({ key: 'settings', type: 'success', message: 'Settings updated successfully' });
            mutate();
        } catch (error: any) {
            addError({ key: 'settings', message: httpErrorToHuman(error) || 'Failed to update settings' });
        } finally {
            setIsSaving(false);
        }
    };

    // Common character sets and collations
    const charsets = [
        { value: 'utf8mb4', label: 'utf8mb4 (Recommended)' },
        { value: 'utf8', label: 'utf8' },
        { value: 'latin1', label: 'latin1' },
        { value: 'ascii', label: 'ascii' },
    ];

    const collations: Record<string, string[]> = {
        utf8mb4: [
            'utf8mb4_unicode_ci',
            'utf8mb4_general_ci',
            'utf8mb4_bin',
            'utf8mb4_0900_ai_ci',
            'utf8mb4_0900_as_ci',
        ],
        utf8: ['utf8_unicode_ci', 'utf8_general_ci', 'utf8_bin'],
        latin1: ['latin1_swedish_ci', 'latin1_general_ci', 'latin1_bin'],
        ascii: ['ascii_general_ci', 'ascii_bin'],
    };

    return (
        <ServerContentBlock title='Settings'>
            <FlashMessageRender byKey='settings' />
            <MainPageHeader title={serverName || 'Database'} />
            <div className='w-full h-full min-h-full flex-1 flex flex-col px-2 sm:px-0 mt-6'>
                {isLoading ? (
                    <div className='flex justify-center py-8'>
                        <Spinner />
                    </div>
                ) : error ? (
                    <div className='text-center py-8 text-red-400'>Failed to load settings</div>
                ) : settings ? (
                    <div className='grid grid-cols-1 lg:grid-cols-2 gap-6'>
                        {/* Database Settings */}
                        <div className='bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-6'>
                            <div className='flex items-center gap-3 mb-6'>
                                <Gear className='w-6 h-6 text-white/60' fill='currentColor' />
                                <h3 className='text-lg font-semibold text-white'>Database Settings</h3>
                            </div>

                            <Formik
                                initialValues={{
                                    charset: settings.database.charset || '',
                                    collation: settings.database.collation || '',
                                }}
                                validationSchema={settingsSchema}
                                onSubmit={handleSave}
                                enableReinitialize
                            >
                                {({ values, setFieldValue, isSubmitting }) => (
                                    <Form>
                                        <div className='space-y-4'>
                                            <Field
                                                type='text'
                                                name='database.name'
                                                label='Database Name'
                                                value={settings.database.name}
                                                disabled
                                                description='The name of your database'
                                            />

                                            <div>
                                                <label className='text-sm text-white/60 mb-2 block'>
                                                    Character Set
                                                </label>
                                                <select
                                                    value={values.charset}
                                                    onChange={(e) => {
                                                        setFieldValue('charset', e.target.value);
                                                        // Reset collation when charset changes
                                                        const newCollations = collations[e.target.value] || [];
                                                        if (newCollations.length > 0) {
                                                            setFieldValue('collation', newCollations[0]);
                                                        }
                                                    }}
                                                    className='w-full px-4 py-2 rounded-lg outline-hidden bg-[#ffffff17] text-sm text-white border border-[#ffffff11]'
                                                >
                                                    {charsets.map((cs) => (
                                                        <option key={cs.value} value={cs.value}>
                                                            {cs.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div>
                                                <label className='text-sm text-white/60 mb-2 block'>Collation</label>
                                                <select
                                                    value={values.collation}
                                                    onChange={(e) => setFieldValue('collation', e.target.value)}
                                                    className='w-full px-4 py-2 rounded-lg outline-hidden bg-[#ffffff17] text-sm text-white border border-[#ffffff11]'
                                                >
                                                    {(collations[values.charset] || []).map((coll) => (
                                                        <option key={coll} value={coll}>
                                                            {coll}
                                                        </option>
                                                    ))}
                                                </select>
                                            </div>

                                            <div className='flex gap-3 justify-end pt-4'>
                                                <ActionButton
                                                    variant='secondary'
                                                    onClick={() => mutate()}
                                                    disabled={isSubmitting}
                                                >
                                                    <Refresh className='w-4 h-4 mr-2' fill='currentColor' />
                                                    Refresh
                                                </ActionButton>
                                                <ActionButton variant='primary' type='submit' disabled={isSubmitting}>
                                                    {isSubmitting || isSaving ? (
                                                        <Spinner size='small' />
                                                    ) : (
                                                        'Save Changes'
                                                    )}
                                                </ActionButton>
                                            </div>
                                        </div>
                                    </Form>
                                )}
                            </Formik>
                        </div>

                        {/* Server Information */}
                        <div className='bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-6'>
                            <h3 className='text-lg font-semibold text-white mb-6'>Server Information</h3>
                            <div className='space-y-4'>
                                <div>
                                    <p className='text-xs text-white/60 uppercase tracking-wide mb-1'>
                                        Server Character Set
                                    </p>
                                    <p className='text-zinc-300 font-mono text-sm'>
                                        {settings.server.character_set_server || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <p className='text-xs text-white/60 uppercase tracking-wide mb-1'>
                                        Server Collation
                                    </p>
                                    <p className='text-zinc-300 font-mono text-sm'>
                                        {settings.server.collation_server || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <p className='text-xs text-white/60 uppercase tracking-wide mb-1'>
                                        Max Connections
                                    </p>
                                    <p className='text-zinc-300 font-mono text-sm'>
                                        {settings.server.max_connections || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <p className='text-xs text-white/60 uppercase tracking-wide mb-1'>
                                        Max Allowed Packet
                                    </p>
                                    <p className='text-zinc-300 font-mono text-sm'>
                                        {settings.server.max_allowed_packet || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <p className='text-xs text-white/60 uppercase tracking-wide mb-1'>
                                        InnoDB Buffer Pool Size
                                    </p>
                                    <p className='text-zinc-300 font-mono text-sm'>
                                        {settings.server.innodb_buffer_pool_size || 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <p className='text-xs text-white/60 uppercase tracking-wide mb-1'>Slow Query Log</p>
                                    <p className='text-zinc-300 font-mono text-sm'>
                                        {settings.server.slow_query_log === 'ON' ? 'Enabled' : 'Disabled'}
                                    </p>
                                </div>
                                <div>
                                    <p className='text-xs text-white/60 uppercase tracking-wide mb-1'>General Log</p>
                                    <p className='text-zinc-300 font-mono text-sm'>
                                        {settings.server.general_log === 'ON' ? 'Enabled' : 'Disabled'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                ) : null}
            </div>
        </ServerContentBlock>
    );
};

export default DatabaseSettingsContainer;
