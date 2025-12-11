import { Table, Eye, TrashBin, Plus } from '@gravity-ui/icons';
import { Form, Formik, FormikHelpers, FieldArray, Field as FormikField } from 'formik';
import { useState } from 'react';
import useSWR from 'swr';
import { array, object, string } from 'yup';

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
import { ServerContext } from '@/state/server';

import useFlash from '@/plugins/useFlash';
import listTables, { TableInfo } from '@/api/server/database-dashboard/listTables';
import getTableStructure, { TableStructure } from '@/api/server/database-dashboard/getTableStructure';
import createTable, { TableColumn } from '@/api/server/database-dashboard/createTable';
import deleteTable from '@/api/server/database-dashboard/deleteTable';
import getDatabaseConnectionInfo from '@/api/server/database-dashboard/getDatabaseConnectionInfo';

interface CreateTableValues {
    name: string;
    columns: TableColumn[];
    engine: string;
    collation: string;
}

const tableSchema = object().shape({
    name: string()
        .required('A table name must be provided.')
        .min(1, 'Table name must be at least 1 character.')
        .max(64, 'Table name must not exceed 64 characters.')
        .matches(/^[a-zA-Z0-9_]+$/, 'Table name can only contain alphanumeric characters and underscores.'),
    columns: array()
        .of(
            object().shape({
                name: string().required('Column name is required.'),
                type: string().required('Column type is required.'),
            }),
        )
        .min(1, 'At least one column is required.'),
    engine: string(),
    collation: string(),
});

const TableBrowserContainer = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const serverName = ServerContext.useStoreState((state) => state.server.data?.name);

    const { addError, clearFlashes } = useFlash();
    const [createModalVisible, setCreateModalVisible] = useState(false);
    const [deleteModalVisible, setDeleteModalVisible] = useState(false);
    const [viewModalVisible, setViewModalVisible] = useState(false);
    const [selectedTable, setSelectedTable] = useState<TableInfo | null>(null);
    const [viewTable, setViewTable] = useState<TableInfo | null>(null);

    // Get current database name from connection info
    const { data: connectionInfo } = useSWR(
        uuid ? [`/api/client/servers/${uuid}/database/connection`, uuid] : null,
        () => getDatabaseConnectionInfo(uuid!),
    );

    const {
        data: tables,
        error,
        isLoading,
        mutate,
    } = useSWR<TableInfo[]>(
        uuid && connectionInfo
            ? [`/api/client/servers/${uuid}/database/tables`, uuid, connectionInfo.database]
            : null,
        () => listTables(uuid!, connectionInfo?.database),
    );

    const {
        data: tableStructure,
        isLoading: structureLoading,
    } = useSWR<TableStructure>(
        viewTable && uuid && connectionInfo
            ? [`/api/client/servers/${uuid}/database/tables/structure`, viewTable.name, connectionInfo.database]
            : null,
        () => getTableStructure(uuid!, viewTable!.name, connectionInfo?.database),
    );

    const submitTable = (
        values: CreateTableValues,
        { setSubmitting, resetForm }: FormikHelpers<CreateTableValues>,
    ) => {
        clearFlashes('table:create');

        createTable(uuid!, {
            name: values.name,
            columns: values.columns,
            database: connectionInfo?.database,
            engine: values.engine || 'InnoDB',
            collation: values.collation || 'utf8mb4_unicode_ci',
        })
            .then(() => {
                resetForm();
                setSubmitting(false);
                setCreateModalVisible(false);
                mutate();
            })
            .catch((error) => {
                addError({ key: 'table:create', message: httpErrorToHuman(error) });
                setSubmitting(false);
            });
    };

    const handleDelete = (table: TableInfo) => {
        setSelectedTable(table);
        setDeleteModalVisible(true);
    };

    const confirmDelete = () => {
        if (!selectedTable || !uuid) {
            return;
        }

        clearFlashes('table:delete');
        deleteTable(uuid, selectedTable.name, connectionInfo?.database)
            .then(() => {
                setDeleteModalVisible(false);
                setSelectedTable(null);
                mutate();
            })
            .catch((error) => {
                addError({ key: 'table:delete', message: httpErrorToHuman(error) });
            });
    };

    const handleView = (table: TableInfo) => {
        setViewTable(table);
        setViewModalVisible(true);
    };

    if (isLoading || !connectionInfo) {
        return (
            <ServerContentBlock title='Tables'>
                <Spinner />
            </ServerContentBlock>
        );
    }

    if (error) {
        return (
            <ServerContentBlock title='Tables'>
                <FlashMessageRender byKey='tables' />
                <div className='text-red-400'>{httpErrorToHuman(error)}</div>
            </ServerContentBlock>
        );
    }

    return (
        <ServerContentBlock title='Tables'>
            <FlashMessageRender byKey='tables' />
            <MainPageHeader
                direction='column'
                title='Tables'
                titleChildren={
                    <Can action={'database.*'} matchAny>
                        <ActionButton variant='primary' onClick={() => setCreateModalVisible(true)}>
                            <Plus className='w-4 h-4 mr-2' fill='currentColor' />
                            New Table
                        </ActionButton>
                    </Can>
                }
            >
                <p className='text-sm text-neutral-400 leading-relaxed'>
                    Manage tables in your database. View table structures, create new tables, and delete tables as
                    needed.
                </p>
            </MainPageHeader>

            {/* Create Table Modal */}
            <Formik
                onSubmit={submitTable}
                initialValues={{
                    name: '',
                    columns: [
                        {
                            name: 'id',
                            type: 'INT',
                            length: 11,
                            nullable: false,
                            autoIncrement: true,
                            primaryKey: true,
                        },
                    ],
                    engine: 'InnoDB',
                    collation: 'utf8mb4_unicode_ci',
                }}
                validationSchema={tableSchema}
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
                        title='Create new table'
                    >
                        <div className='flex flex-col max-h-[80vh] overflow-y-auto'>
                            <FlashMessageRender byKey={'table:create'} />
                            <Form>
                                <Field
                                    type={'string'}
                                    id={'table_name'}
                                    name={'name'}
                                    label={'Table Name'}
                                    description={'A name for your table. Only alphanumeric characters and underscores allowed.'}
                                />
                                <div className='mt-6'>
                                    <label className='text-sm font-medium text-white mb-2 block'>Columns</label>
                                    <FieldArray name='columns'>
                                        {({ push, remove, form }) => (
                                            <div className='space-y-4'>
                                                {values.columns.map((column, index) => (
                                                    <div
                                                        key={index}
                                                        className='bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-4'
                                                    >
                                                        <div className='grid grid-cols-2 gap-4 mb-4'>
                                                            <Field
                                                                type={'string'}
                                                                name={`columns.${index}.name`}
                                                                label={'Column Name'}
                                                            />
                                                            <FormikField name={`columns.${index}.type`}>
                                                                {({ field }: any) => (
                                                                    <div className='flex flex-col gap-2'>
                                                                        <label className='text-sm text-[#ffffff77]'>Type</label>
                                                                        <select
                                                                            {...field}
                                                                            className='px-4 py-2 rounded-lg outline-hidden bg-[#ffffff17] text-sm text-white'
                                                                        >
                                                                            <option value='INT'>INT</option>
                                                                            <option value='BIGINT'>BIGINT</option>
                                                                            <option value='VARCHAR'>VARCHAR</option>
                                                                            <option value='TEXT'>TEXT</option>
                                                                            <option value='DATETIME'>DATETIME</option>
                                                                            <option value='TIMESTAMP'>TIMESTAMP</option>
                                                                            <option value='DECIMAL'>DECIMAL</option>
                                                                            <option value='BOOLEAN'>BOOLEAN</option>
                                                                        </select>
                                                                    </div>
                                                                )}
                                                            </FormikField>
                                                        </div>
                                                        {(values.columns[index].type === 'VARCHAR' ||
                                                            values.columns[index].type === 'CHAR') && (
                                                            <Field
                                                                type={'number'}
                                                                name={`columns.${index}.length`}
                                                                label={'Length'}
                                                            />
                                                        )}
                                                        {values.columns[index].type === 'DECIMAL' && (
                                                            <div className='grid grid-cols-2 gap-4'>
                                                                <Field
                                                                    type={'number'}
                                                                    name={`columns.${index}.precision`}
                                                                    label={'Precision'}
                                                                />
                                                                <Field
                                                                    type={'number'}
                                                                    name={`columns.${index}.scale`}
                                                                    label={'Scale'}
                                                                />
                                                            </div>
                                                        )}
                                                        <div className='grid grid-cols-2 gap-4 mt-4'>
                                                            <Field
                                                                type={'checkbox'}
                                                                name={`columns.${index}.nullable`}
                                                                label={'Nullable'}
                                                            />
                                                            <Field
                                                                type={'checkbox'}
                                                                name={`columns.${index}.primaryKey`}
                                                                label={'Primary Key'}
                                                            />
                                                        </div>
                                                        <div className='grid grid-cols-2 gap-4 mt-4'>
                                                            <Field
                                                                type={'checkbox'}
                                                                name={`columns.${index}.autoIncrement`}
                                                                label={'Auto Increment'}
                                                            />
                                                            <Field
                                                                type={'string'}
                                                                name={`columns.${index}.defaultValue`}
                                                                label={'Default Value'}
                                                            />
                                                        </div>
                                                        {index > 0 && (
                                                            <ActionButton
                                                                variant='danger'
                                                                size='sm'
                                                                onClick={() => remove(index)}
                                                                className='mt-4'
                                                            >
                                                                Remove Column
                                                            </ActionButton>
                                                        )}
                                                    </div>
                                                ))}
                                                <ActionButton
                                                    variant='secondary'
                                                    onClick={() =>
                                                        push({
                                                            name: '',
                                                            type: 'VARCHAR',
                                                            length: 255,
                                                            nullable: true,
                                                        })
                                                    }
                                                >
                                                    Add Column
                                                </ActionButton>
                                            </div>
                                        )}
                                    </FieldArray>
                                </div>
                                <div className='grid grid-cols-2 gap-4 mt-6'>
                                    <FormikField name='engine'>
                                        {({ field }: any) => (
                                            <div className='flex flex-col gap-2'>
                                                <label className='text-sm text-[#ffffff77]'>Engine</label>
                                                <select
                                                    {...field}
                                                    className='px-4 py-2 rounded-lg outline-hidden bg-[#ffffff17] text-sm text-white'
                                                >
                                                    <option value='InnoDB'>InnoDB</option>
                                                    <option value='MyISAM'>MyISAM</option>
                                                    <option value='MEMORY'>MEMORY</option>
                                                </select>
                                            </div>
                                        )}
                                    </FormikField>
                                    <FormikField name='collation'>
                                        {({ field }: any) => (
                                            <div className='flex flex-col gap-2'>
                                                <label className='text-sm text-[#ffffff77]'>Collation</label>
                                                <select
                                                    {...field}
                                                    className='px-4 py-2 rounded-lg outline-hidden bg-[#ffffff17] text-sm text-white'
                                                >
                                                    <option value='utf8mb4_unicode_ci'>utf8mb4_unicode_ci</option>
                                                    <option value='utf8mb4_general_ci'>utf8mb4_general_ci</option>
                                                    <option value='utf8_unicode_ci'>utf8_unicode_ci</option>
                                                </select>
                                            </div>
                                        )}
                                    </FormikField>
                                </div>
                                <div className='flex gap-3 justify-end my-6'>
                                    <ActionButton variant='primary' type={'submit'}>
                                        Create Table
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
                    setSelectedTable(null);
                }}
                title='Confirm table deletion'
            >
                <FlashMessageRender byKey={'table:delete'} />
                <div className='flex flex-col'>
                    <p className='text-white/80 mb-4'>
                        Deleting a table is a permanent action, it cannot be undone. This will permanently delete the{' '}
                        <strong className='text-white'>{selectedTable?.name}</strong> table and remove all its data.
                    </p>
                    <div className='flex gap-3 justify-end mt-6'>
                        <ActionButton variant='secondary' onClick={() => setDeleteModalVisible(false)}>
                            Cancel
                        </ActionButton>
                        <ActionButton variant='danger' onClick={confirmDelete}>
                            Delete Table
                        </ActionButton>
                    </div>
                </div>
            </Modal>

            {/* View Table Structure Modal */}
            <Modal
                visible={viewModalVisible}
                title={`Table: ${viewTable?.name}`}
                closeButton={true}
                onDismissed={() => {
                    setViewModalVisible(false);
                    setViewTable(null);
                }}
            >
                {structureLoading ? (
                    <Spinner />
                ) : tableStructure ? (
                    <div className='flex flex-col gap-6 max-h-[80vh] overflow-y-auto'>
                        {/* Table Info */}
                        <div className='grid grid-cols-2 gap-4'>
                            <div>
                                <label className='text-sm text-white/60 mb-1 block'>Engine</label>
                                <p className='text-white'>{tableStructure.engine}</p>
                            </div>
                            <div>
                                <label className='text-sm text-white/60 mb-1 block'>Collation</label>
                                <p className='text-white'>{tableStructure.collation}</p>
                            </div>
                            <div>
                                <label className='text-sm text-white/60 mb-1 block'>Size</label>
                                <p className='text-white'>{tableStructure.sizeFormatted}</p>
                            </div>
                            <div>
                                <label className='text-sm text-white/60 mb-1 block'>Rows</label>
                                <p className='text-white'>{tableStructure.rowCount.toLocaleString()}</p>
                            </div>
                        </div>

                        {/* Columns */}
                        <div>
                            <h3 className='text-lg font-semibold text-white mb-4'>Columns</h3>
                            <div className='overflow-x-auto'>
                                <table className='w-full border-collapse'>
                                    <thead>
                                        <tr className='border-b border-white/10'>
                                            <th className='text-left p-2 text-sm text-white/60'>Name</th>
                                            <th className='text-left p-2 text-sm text-white/60'>Type</th>
                                            <th className='text-left p-2 text-sm text-white/60'>Nullable</th>
                                            <th className='text-left p-2 text-sm text-white/60'>Default</th>
                                            <th className='text-left p-2 text-sm text-white/60'>Key</th>
                                            <th className='text-left p-2 text-sm text-white/60'>Extra</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tableStructure.columns.map((column, idx) => (
                                            <tr key={idx} className='border-b border-white/5'>
                                                <td className='p-2 text-white font-mono text-sm'>{column.name}</td>
                                                <td className='p-2 text-white/80 font-mono text-sm'>{column.fullType}</td>
                                                <td className='p-2 text-white/80 text-sm'>
                                                    {column.nullable ? 'YES' : 'NO'}
                                                </td>
                                                <td className='p-2 text-white/80 font-mono text-sm'>
                                                    {column.defaultValue ?? 'NULL'}
                                                </td>
                                                <td className='p-2 text-white/80 text-sm'>{column.key || '-'}</td>
                                                <td className='p-2 text-white/80 text-sm'>{column.extra || '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* Indexes */}
                        {tableStructure.indexes.length > 0 && (
                            <div>
                                <h3 className='text-lg font-semibold text-white mb-4'>Indexes</h3>
                                <div className='space-y-2'>
                                    {tableStructure.indexes.map((index, idx) => (
                                        <div key={idx} className='bg-[#ffffff09] border border-[#ffffff11] rounded p-3'>
                                            <div className='flex items-center gap-2 mb-1'>
                                                <span className='font-semibold text-white'>{index.name}</span>
                                                {index.unique && (
                                                    <span className='text-xs bg-blue-500/20 text-blue-400 px-2 py-1 rounded'>
                                                        UNIQUE
                                                    </span>
                                                )}
                                                <span className='text-xs text-white/60'>{index.type}</span>
                                            </div>
                                            <p className='text-sm text-white/80'>
                                                Columns: {index.columns.join(', ')}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                ) : null}
            </Modal>

            {!tables || tables.length === 0 ? (
                <div className='flex flex-col items-center justify-center min-h-[60vh] py-12 px-4'>
                    <div className='text-center'>
                        <div className='w-16 h-16 mx-auto mb-4 rounded-full bg-[#ffffff11] flex items-center justify-center'>
                            <Table className='w-8 h-8 text-zinc-400' fill='currentColor' />
                        </div>
                        <h3 className='text-lg font-medium text-zinc-200 mb-2'>No tables found</h3>
                        <p className='text-sm text-zinc-400 max-w-sm'>
                            Your database does not have any tables. Create one to get started.
                        </p>
                    </div>
                </div>
            ) : (
                <PageListContainer data-pyro-tables>
                    {tables.map((table) => (
                        <PageListItem key={table.name}>
                            <div className='flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 w-full'>
                                <div className='flex-1 min-w-0'>
                                    <div className='flex items-center gap-3 mb-2'>
                                        <div className='flex-shrink-0 w-8 h-8 rounded-lg bg-[#ffffff11] flex items-center justify-center'>
                                            <Table fill='currentColor' className='text-zinc-400 w-4 h-4' />
                                        </div>
                                        <div className='min-w-0 flex-1'>
                                            <CopyOnClick text={table.name}>
                                                <h3 className='text-base font-medium text-zinc-100 truncate'>
                                                    {table.name}
                                                </h3>
                                            </CopyOnClick>
                                        </div>
                                    </div>

                                    <div className='grid grid-cols-1 sm:grid-cols-4 gap-3 text-sm'>
                                        <div>
                                            <p className='text-xs text-zinc-500 uppercase tracking-wide mb-1'>Size</p>
                                            <p className='text-zinc-300'>{table.sizeFormatted}</p>
                                        </div>
                                        <div>
                                            <p className='text-xs text-zinc-500 uppercase tracking-wide mb-1'>Rows</p>
                                            <p className='text-zinc-300'>{table.rowCount.toLocaleString()}</p>
                                        </div>
                                        <div>
                                            <p className='text-xs text-zinc-500 uppercase tracking-wide mb-1'>Engine</p>
                                            <p className='text-zinc-300'>{table.engine}</p>
                                        </div>
                                        <div>
                                            <p className='text-xs text-zinc-500 uppercase tracking-wide mb-1'>Collation</p>
                                            <p className='text-zinc-300 font-mono text-xs'>{table.collation}</p>
                                        </div>
                                    </div>
                                </div>

                                <div className='flex items-center gap-2 sm:flex-col sm:gap-3'>
                                    <ActionButton
                                        variant='secondary'
                                        size='sm'
                                        onClick={() => handleView(table)}
                                        className='flex items-center gap-2'
                                    >
                                        <Eye fill='currentColor' className='w-4 h-4' />
                                        <span className='hidden sm:inline'>Structure</span>
                                    </ActionButton>
                                    <Can action={'database.delete'} matchAny>
                                        <ActionButton
                                            variant='danger'
                                            size='sm'
                                            onClick={() => handleDelete(table)}
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

export default TableBrowserContainer;
