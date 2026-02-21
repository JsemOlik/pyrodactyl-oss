import useServerEggFeatures from '@/hooks/useServerEggFeatures';
import { Database, Eye, TrashBin } from '@gravity-ui/icons';
import { Form, Formik, FormikHelpers } from 'formik';
import { useEffect, useState } from 'react';
import useSWR from 'swr';
import { object, string } from 'yup';

import FlashMessageRender from '@/components/FlashMessageRender';
import ActionButton from '@/components/elements/ActionButton';
import Can from '@/components/elements/Can';
import CopyOnClick from '@/components/elements/CopyOnClick';
import Field from '@/components/elements/Field';
import Input from '@/components/elements/Input';
import { MainPageHeader } from '@/components/elements/MainPageHeader';
import Modal from '@/components/elements/Modal';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import { PageListContainer, PageListItem } from '@/components/elements/pages/PageList';

import { httpErrorToHuman } from '@/api/http';
import createDatabase from '@/api/server/database-dashboard/createDatabase';
import deleteDatabase from '@/api/server/database-dashboard/deleteDatabase';
import listDatabases, { DatabaseInfo } from '@/api/server/database-dashboard/listDatabases';

import { ServerContext } from '@/state/server';

import useFlash from '@/plugins/useFlash';

interface CreateDatabaseValues {
    name: string;
    username: string;
    password: string;
    remote: string;
    createUser: boolean;
}

const databaseSchema = object().shape({
    name: string()
        .required('A database name must be provided.')
        .min(1, 'Database name must be at least 1 character.')
        .max(64, 'Database name must not exceed 64 characters.')
        .matches(/^[a-zA-Z0-9_]+$/, 'Database name can only contain alphanumeric characters and underscores.'),
    username: string().matches(/^[a-zA-Z0-9_]+$/, 'Username can only contain alphanumeric characters and underscores.'),
    password: string(),
    remote: string().max(255, 'Remote address is too long.'),
});

const DatabaseListContainer = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name);

    // Feature/egg helper to adapt the UI per-egg (e.g. disable creation when feature not present)
    const { hasFeature, featureLimits } = useServerEggFeatures();

    const { addError, clearFlashes } = useFlash();
    const [createModalVisible, setCreateModalVisible] = useState(false);
    const [deleteModalVisible, setDeleteModalVisible] = useState(false);
    const [selectedDatabase, setSelectedDatabase] = useState<DatabaseInfo | null>(null);
    const [viewModalVisible, setViewModalVisible] = useState(false);
    const [viewDatabase, setViewDatabase] = useState<DatabaseInfo | null>(null);

    const {
        data: databases,
        error,
        isLoading,
        mutate,
    } = useSWR<DatabaseInfo[]>(uuid ? [`/api/client/servers/${uuid}/database/databases`, uuid] : null, () =>
        listDatabases(uuid!),
    );

    const submitDatabase = (
        values: CreateDatabaseValues,
        { setSubmitting, resetForm }: FormikHelpers<CreateDatabaseValues>,
    ) => {
        clearFlashes('database:create');

        const createData: any = {
            name: values.name,
        };

        if (values.createUser) {
            createData.username = values.username;
            createData.password = values.password;
            createData.remote = values.remote || '%';
        }

        createDatabase(uuid!, createData)
            .then(() => {
                resetForm();
                setSubmitting(false);
                setCreateModalVisible(false);
                mutate();
            })
            .catch((error) => {
                addError({ key: 'database:create', message: httpErrorToHuman(error) });
                setSubmitting(false);
            });
    };

    const handleDelete = (database: DatabaseInfo) => {
        setSelectedDatabase(database);
        setDeleteModalVisible(true);
    };

    const confirmDelete = () => {
        if (!selectedDatabase || !uuid) {
            return;
        }

        clearFlashes('database:delete');
        deleteDatabase(uuid, selectedDatabase.name)
            .then(() => {
                setDeleteModalVisible(false);
                setSelectedDatabase(null);
                mutate();
            })
            .catch((error) => {
                addError({ key: 'database:delete', message: httpErrorToHuman(error) });
            });
    };

    const handleView = (database: DatabaseInfo) => {
        setViewDatabase(database);
        setViewModalVisible(true);
    };

    if (isLoading) {
        return (
            <ServerContentBlock title='Databases'>
                <Spinner />
            </ServerContentBlock>
        );
    }

    if (error) {
        return (
            <ServerContentBlock title='Databases'>
                <FlashMessageRender byKey='databases' />
                <div className='text-red-400'>{httpErrorToHuman(error)}</div>
            </ServerContentBlock>
        );
    }

    return (
        <ServerContentBlock title='Databases'>
            <FlashMessageRender byKey='databases' />
            <MainPageHeader
                direction='column'
                title='Databases'
                titleChildren={
                    <div className='flex items-center justify-end'>
                        <Can action={'database.create'}>
                            {hasFeature('databases') &&
                            (featureLimits?.databases === null ||
                                featureLimits?.databases === undefined ||
                                featureLimits?.databases > 0) ? (
                                <ActionButton variant='primary' onClick={() => setCreateModalVisible(true)}>
                                    New Database
                                </ActionButton>
                            ) : null}
                        </Can>
                    </div>
                }
            >
                <p className='text-sm text-neutral-400 leading-relaxed'>
                    Manage all databases on your database host. Create new databases, view details, and delete databases
                    as needed.
                </p>
            </MainPageHeader>

            {/* Create Database Modal */}
            <Formik
                onSubmit={submitDatabase}
                initialValues={{ name: '', username: '', password: '', remote: '%', createUser: false }}
                validationSchema={databaseSchema}
            >
                {({ isSubmitting, resetForm, values }) => (
                    <Modal
                        visible={createModalVisible}
                        dismissable={!isSubmitting}
                        showSpinnerOverlay={isSubmitting}
                        onDismissed={() => {
                            resetForm();
                            setCreateModalVisible(false);
                        }}
                        title='Create new database'
                    >
                        <div className='flex flex-col'>
                            <FlashMessageRender byKey={'database:create'} />
                            <Form>
                                <Field
                                    type={'string'}
                                    id={'database_name'}
                                    name={'name'}
                                    label={'Database Name'}
                                    description={
                                        'A name for your database. Only alphanumeric characters and underscores allowed.'
                                    }
                                />
                                <div className='mt-6'>
                                    <Field
                                        type={'checkbox'}
                                        id={'create_user'}
                                        name={'createUser'}
                                        label={'Create Database User'}
                                        description={'Optionally create a user with access to this database.'}
                                    />
                                </div>
                                {values.createUser && (
                                    <>
                                        <div className='mt-6'>
                                            <Field
                                                type={'string'}
                                                id={'username'}
                                                name={'username'}
                                                label={'Username'}
                                                description={'Username for database access.'}
                                            />
                                        </div>
                                        <div className='mt-6'>
                                            <Field
                                                type={'password'}
                                                id={'password'}
                                                name={'password'}
                                                label={'Password'}
                                                description={'Password for database access.'}
                                            />
                                        </div>
                                        <div className='mt-6'>
                                            <Field
                                                type={'string'}
                                                id={'remote'}
                                                name={'remote'}
                                                label={'Remote Access'}
                                                description={'Where connections are allowed from. Use % for anywhere.'}
                                            />
                                        </div>
                                    </>
                                )}
                                <div className='flex gap-3 justify-end my-6'>
                                    <ActionButton variant='primary' type={'submit'}>
                                        Create Database
                                    </ActionButton>
                                </div>
                            </Form>
                        </div>
                    </Modal>
                )}
            </Formik>

            {/* Delete Confirmation Modal */}
            <Modal
                visible={deleteModalVisible}
                dismissable={true}
                onDismissed={() => {
                    setDeleteModalVisible(false);
                    setSelectedDatabase(null);
                }}
                title='Confirm database deletion'
            >
                <FlashMessageRender byKey={'database:delete'} />
                <div className='flex flex-col'>
                    <p className='text-white/80 mb-4'>
                        Deleting a database is a permanent action, it cannot be undone. This will permanently delete the{' '}
                        <strong className='text-white'>{selectedDatabase?.name}</strong> database and remove all its
                        data.
                    </p>
                    <div className='flex gap-3 justify-end mt-6'>
                        <ActionButton variant='secondary' onClick={() => setDeleteModalVisible(false)}>
                            Cancel
                        </ActionButton>
                        <ActionButton variant='danger' onClick={confirmDelete}>
                            Delete Database
                        </ActionButton>
                    </div>
                </div>
            </Modal>

            {/* View Database Modal */}
            <Modal
                visible={viewModalVisible}
                title='Database details'
                closeButton={true}
                onDismissed={() => {
                    setViewModalVisible(false);
                    setViewDatabase(null);
                }}
            >
                {viewDatabase && (
                    <div className='flex flex-col gap-4'>
                        <div className='grid gap-4 sm:grid-cols-2'>
                            <div className='flex flex-col'>
                                <label className='text-sm text-white/60 mb-2'>Database Name</label>
                                <CopyOnClick text={viewDatabase.name}>
                                    <Input type='text' readOnly value={viewDatabase.name} />
                                </CopyOnClick>
                            </div>
                            <div className='flex flex-col'>
                                <label className='text-sm text-white/60 mb-2'>Size</label>
                                <Input type='text' readOnly value={viewDatabase.sizeFormatted} />
                            </div>
                            <div className='flex flex-col'>
                                <label className='text-sm text-white/60 mb-2'>Tables</label>
                                <Input type='text' readOnly value={viewDatabase.tableCount.toString()} />
                            </div>
                        </div>
                    </div>
                )}
            </Modal>

            {!databases || databases.length === 0 ? (
                <div className='flex flex-col items-center justify-center min-h-[60vh] py-12 px-4'>
                    <div className='text-center'>
                        <div className='w-16 h-16 mx-auto mb-4 rounded-full bg-[#ffffff11] flex items-center justify-center'>
                            <Database className='w-8 h-8 text-zinc-400' fill='currentColor' />
                        </div>
                        <h3 className='text-lg font-medium text-zinc-200 mb-2'>No databases found</h3>
                        <p className='text-sm text-zinc-400 max-w-sm'>
                            Your database host does not have any databases. Create one to get started.
                        </p>
                    </div>
                </div>
            ) : (
                <PageListContainer data-pyro-databases>
                    {databases.map((database) => (
                        <PageListItem key={database.name}>
                            <div className='flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full'>
                                <div className='flex-1 min-w-0'>
                                    <div className='flex items-center gap-3 mb-2'>
                                        <div className='flex-shrink-0 w-8 h-8 rounded-lg bg-[#ffffff11] flex items-center justify-center'>
                                            <Database fill='currentColor' className='text-zinc-400 w-4 h-4' />
                                        </div>
                                        <div className='min-w-0 flex-1'>
                                            <CopyOnClick text={database.name}>
                                                <h3 className='text-base font-medium text-zinc-100 truncate'>
                                                    {database.name}
                                                </h3>
                                            </CopyOnClick>
                                        </div>
                                    </div>

                                    <div className='grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm'>
                                        <div>
                                            <p className='text-xs text-zinc-500 uppercase tracking-wide mb-1'>Size</p>
                                            <p className='text-zinc-300'>{database.sizeFormatted}</p>
                                        </div>
                                        <div>
                                            <p className='text-xs text-zinc-500 uppercase tracking-wide mb-1'>Tables</p>
                                            <p className='text-zinc-300'>{database.tableCount}</p>
                                        </div>
                                    </div>
                                </div>

                                <div className='flex items-center gap-2 sm:flex-col sm:gap-3'>
                                    <ActionButton
                                        variant='secondary'
                                        size='sm'
                                        onClick={() => handleView(database)}
                                        className='flex items-center gap-2'
                                    >
                                        <Eye fill='currentColor' className='w-4 h-4' />
                                        <span className='hidden sm:inline'>Details</span>
                                    </ActionButton>
                                    <Can action={'database.delete'}>
                                        <ActionButton
                                            variant='danger'
                                            size='sm'
                                            onClick={() => handleDelete(database)}
                                            className='flex items-center gap-2'
                                        >
                                            <TrashBin fill='currentColor' className='w-4 h-4' />
                                            <span className='hidden sm:inline'>Delete</span>
                                        </ActionButton>
                                    </Can>
                                </div>
                            </div>
                        </PageListItem>
                    ))}
                </PageListContainer>
            )}
        </ServerContentBlock>
    );
};

export default DatabaseListContainer;
