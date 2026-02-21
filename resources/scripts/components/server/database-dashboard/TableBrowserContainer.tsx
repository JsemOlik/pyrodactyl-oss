import useServerEggFeatures from '@/hooks/useServerEggFeatures';
import { Database, Eye, LayoutHeaderCellsLarge, Plus, TrashBin } from '@gravity-ui/icons';
import { FieldArray, Form, Formik, Field as FormikField, FormikHelpers } from 'formik';
import { useEffect, useState } from 'react';
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
import createTable, { TableColumn } from '@/api/server/database-dashboard/createTable';
import deleteRow from '@/api/server/database-dashboard/deleteRow';
import deleteTable from '@/api/server/database-dashboard/deleteTable';
import getDatabaseConnectionInfo from '@/api/server/database-dashboard/getDatabaseConnectionInfo';
import getTableData, { TableDataResponse } from '@/api/server/database-dashboard/getTableData';
import getTableStructure, { TableStructure } from '@/api/server/database-dashboard/getTableStructure';
import insertRow from '@/api/server/database-dashboard/insertRow';
import listTables, { TableInfo } from '@/api/server/database-dashboard/listTables';
import updateRow from '@/api/server/database-dashboard/updateRow';

import { ServerContext } from '@/state/server';

import useFlash from '@/plugins/useFlash';

interface CreateTableValues {
    name: string;
    columns: TableColumn[];
    engine?: string;
    collation?: string;
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
    const [dataModalVisible, setDataModalVisible] = useState(false);
    const [selectedTable, setSelectedTable] = useState<TableInfo | null>(null);
    const [viewTable, setViewTable] = useState<TableInfo | null>(null);
    const [dataTable, setDataTable] = useState<TableInfo | null>(null);
    const [dataPage, setDataPage] = useState<number>(1);
    const [editRowModalVisible, setEditRowModalVisible] = useState(false);
    const [addRowModalVisible, setAddRowModalVisible] = useState(false);
    const [editingRow, setEditingRow] = useState<Record<string, any> | null>(null);
    const [primaryKeyColumns, setPrimaryKeyColumns] = useState<string[]>([]);

    // Get current database name from connection info
    const { data: connectionInfo } = useSWR(
        uuid ? [`/api/client/servers/${uuid}/database/connection`, uuid] : null,
        () => getDatabaseConnectionInfo(uuid!),
    );

    const { hasFeature, featureLimits } = useServerEggFeatures();

    const {
        data: tables,
        error,
        isLoading,
        mutate,
    } = useSWR<TableInfo[]>(
        uuid && connectionInfo ? [`/api/client/servers/${uuid}/database/tables`, uuid, connectionInfo.database] : null,
        () => listTables(uuid!, connectionInfo?.database),
    );

    const { data: tableStructure, isLoading: structureLoading } = useSWR<TableStructure>(
        viewTable && uuid && connectionInfo
            ? [`/api/client/servers/${uuid}/database/tables/structure`, viewTable.name, connectionInfo.database]
            : null,
        () => getTableStructure(uuid!, viewTable!.name, connectionInfo?.database),
    );

    const { data: dataTableStructure } = useSWR<TableStructure>(
        dataTable && uuid && connectionInfo
            ? [`/api/client/servers/${uuid}/database/tables/structure`, dataTable.name, connectionInfo.database]
            : null,
        () => getTableStructure(uuid!, dataTable!.name, connectionInfo?.database),
    );

    // Extract primary key columns when structure loads
    useEffect(() => {
        if (dataTableStructure) {
            const pkColumns = dataTableStructure.columns.filter((col) => col.key === 'PRI').map((col) => col.name);
            setPrimaryKeyColumns(pkColumns);
        }
    }, [dataTableStructure]);

    const {
        data: tableData,
        isLoading: dataLoading,
        mutate: mutateData,
    } = useSWR<TableDataResponse>(
        dataTable && uuid && connectionInfo
            ? [`/api/client/servers/${uuid}/database/tables/data`, dataTable.name, dataPage, connectionInfo.database]
            : null,
        () => getTableData(uuid!, dataTable!.name, dataPage, 50, connectionInfo?.database),
    );

    const submitTable = (values: CreateTableValues, { setSubmitting, resetForm }: FormikHelpers<CreateTableValues>) => {
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

    const handleViewData = (table: TableInfo) => {
        setDataTable(table);
        setDataPage(1);
        setDataModalVisible(true);
    };

    const handleEditRow = (row: Record<string, any>) => {
        setEditingRow(row);
        setEditRowModalVisible(true);
    };

    const handleAddRow = () => {
        setEditingRow({});
        setAddRowModalVisible(true);
    };

    const handleDeleteRow = async (row: Record<string, any>) => {
        if (!dataTable || !uuid || !dataTableStructure) {
            return;
        }

        // Build where clause from primary keys
        const where: Record<string, any> = {};
        primaryKeyColumns.forEach((col) => {
            if (row[col] !== undefined && row[col] !== null) {
                where[col] = row[col];
            }
        });

        if (Object.keys(where).length === 0) {
            addError({ key: 'row:delete', message: 'Cannot delete row: no primary key found' });
            return;
        }

        clearFlashes('row:delete');
        try {
            await deleteRow(uuid, {
                table: dataTable.name,
                where,
                database: connectionInfo?.database,
            });
            mutateData();
        } catch (error: any) {
            addError({ key: 'row:delete', message: httpErrorToHuman(error) });
        }
    };

    const handleSaveRow = async (rowData: Record<string, any>, isNew: boolean) => {
        if (!dataTable || !uuid) {
            return;
        }

        clearFlashes('row:save');

        try {
            if (isNew) {
                await insertRow(uuid, {
                    table: dataTable.name,
                    data: rowData,
                    database: connectionInfo?.database,
                });
            } else {
                // Build where clause from primary keys
                const where: Record<string, any> = {};
                primaryKeyColumns.forEach((col) => {
                    if (editingRow && editingRow[col] !== undefined && editingRow[col] !== null) {
                        where[col] = editingRow[col];
                    }
                });

                if (Object.keys(where).length === 0) {
                    addError({ key: 'row:save', message: 'Cannot update row: no primary key found' });
                    return;
                }

                await updateRow(uuid, {
                    table: dataTable.name,
                    data: rowData,
                    where,
                    database: connectionInfo?.database,
                });
            }
            setEditRowModalVisible(false);
            setAddRowModalVisible(false);
            setEditingRow(null);
            mutateData();
        } catch (error: any) {
            addError({ key: 'row:save', message: httpErrorToHuman(error) });
        }
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
                    <div className='flex items-center justify-end'>
                        <Can action={'database.*'} matchAny>
                            {hasFeature('table-browser') &&
                            (featureLimits?.databases === null ||
                                featureLimits?.databases === undefined ||
                                featureLimits?.databases > 0) ? (
                                <ActionButton variant='primary' onClick={() => setCreateModalVisible(true)}>
                                    <Plus className='w-4 h-4 mr-2' fill='currentColor' />
                                    New Table
                                </ActionButton>
                            ) : null}
                        </Can>
                    </div>
                }
            >
                <p className='text-sm text-neutral-400 leading-relaxed'>
                    Manage tables in your database. View table structures, create new tables, and delete tables as
                    needed.
                </p>
            </MainPageHeader>

            <Modal
                visible={createModalVisible}
                dismissable={!false}
                showSpinnerOverlay={false}
                onDismissed={() => {
                    setCreateModalVisible(false);
                }}
                title='Create new table'
            >
                <FlashMessageRender byKey={'table:create'} />
                <Formik
                    onSubmit={submitTable}
                    validationSchema={tableSchema}
                    initialValues={{
                        name: '',
                        columns: [{ name: 'id', type: 'INT', autoIncrement: true }],
                        engine: 'InnoDB',
                        collation: 'utf8mb4_unicode_ci',
                    }}
                >
                    {({ isSubmitting, resetForm }) => (
                        <div className='flex flex-col'>
                            <Form>
                                <div className='grid gap-4 sm:grid-cols-2'>
                                    <Field
                                        type={'string'}
                                        id={'table_name'}
                                        name={'name'}
                                        label={'Table Name'}
                                        description={'A name for the table.'}
                                    />
                                    <div className='flex flex-col'>
                                        <label className='text-sm text-white/60 mb-2'>Columns</label>
                                        <FieldArray name='columns'>
                                            {({ remove, push, form }) => (
                                                <div className='space-y-2'>
                                                    {form.values.columns &&
                                                        form.values.columns.map((_: any, index: number) => (
                                                            <div key={index} className='flex gap-2'>
                                                                <FormikField name={`columns.${index}.name`}>
                                                                    {({ field }: any) => (
                                                                        <Input {...field} placeholder='column_name' />
                                                                    )}
                                                                </FormikField>
                                                                <FormikField name={`columns.${index}.type`}>
                                                                    {({ field }: any) => (
                                                                        <Input
                                                                            {...field}
                                                                            placeholder='type (VARCHAR(255))'
                                                                        />
                                                                    )}
                                                                </FormikField>
                                                                <button
                                                                    type='button'
                                                                    onClick={() => remove(index)}
                                                                    className='bg-red-600 px-2 rounded'
                                                                >
                                                                    Remove
                                                                </button>
                                                            </div>
                                                        ))}
                                                    <ActionButton
                                                        variant='secondary'
                                                        onClick={() => push({ name: '', type: 'VARCHAR(255)' })}
                                                    >
                                                        Add Column
                                                    </ActionButton>
                                                </div>
                                            )}
                                        </FieldArray>
                                    </div>
                                </div>
                                <div className='flex gap-3 justify-end my-6'>
                                    <ActionButton variant='primary' type={'submit'}>
                                        Create Table
                                    </ActionButton>
                                </div>
                            </Form>
                        </div>
                    )}
                </Formik>
            </Modal>

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

            {/* View Table Modal */}
            <Modal
                visible={viewModalVisible}
                title='Table structure'
                closeButton={true}
                onDismissed={() => {
                    setViewModalVisible(false);
                    setViewTable(null);
                }}
            >
                {structureLoading ? (
                    <Spinner />
                ) : (
                    tableStructure && (
                        <div className='space-y-4'>
                            <h3 className='text-lg font-semibold text-white'>{tableStructure.name}</h3>
                            <div className='bg-[#ffffff09] border border-[#ffffff11] rounded-lg p-4'>
                                <h4 className='text-sm text-white/60 mb-2'>Columns</h4>
                                <div className='space-y-2'>
                                    {tableStructure.columns.map((col) => (
                                        <div key={col.name} className='flex justify-between'>
                                            <div>
                                                <div className='font-mono text-white/80'>{col.name}</div>
                                                <div className='text-xs text-white/60'>{col.fullType}</div>
                                            </div>
                                            <div className='text-sm text-white/60'>
                                                {col.nullable ? 'NULL' : 'NOT NULL'}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    )
                )}
            </Modal>

            {/* View Table Data Modal */}
            <Modal
                visible={dataModalVisible}
                title={dataTable ? `Data — ${dataTable.name}` : 'Table Data'}
                closeButton={true}
                onDismissed={() => {
                    setDataModalVisible(false);
                    setDataTable(null);
                    setDataPage(1);
                }}
            >
                {dataLoading || !dataTable ? (
                    <Spinner />
                ) : dataTable && tableData ? (
                    <div>
                        <div className='mb-4 flex justify-between'>
                            <div>
                                <strong className='text-white'>{dataTable.name}</strong>
                                <div className='text-sm text-white/60'>Rows: {tableData.pagination.total}</div>
                            </div>
                            <div className='flex gap-2'>
                                <ActionButton variant='secondary' onClick={() => mutateData()}>
                                    Refresh
                                </ActionButton>
                            </div>
                        </div>
                        <div className='overflow-x-auto'>
                            <table className='w-full border-collapse'>
                                <thead>
                                    <tr className='border-b border-white/10'>
                                        {tableData.columns.map((col) => (
                                            <th key={col} className='text-left p-2 text-sm text-white/60 font-semibold'>
                                                {col}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {tableData.data.map((row, idx) => (
                                        <tr key={idx} className='border-b border-white/5 hover:bg-white/5'>
                                            {tableData.columns.map((col) => (
                                                <td key={col} className='p-2 text-white/80 font-mono text-sm'>
                                                    {row[col] !== null && row[col] !== undefined
                                                        ? String(row[col])
                                                        : 'NULL'}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        <div className='flex items-center justify-between mt-4'>
                            <div className='text-sm text-white/60'>
                                Page {tableData.pagination.currentPage} of {tableData.pagination.lastPage}
                            </div>
                            <div className='flex gap-2'>
                                <ActionButton
                                    variant='secondary'
                                    onClick={() => setDataPage(Math.max(1, dataPage - 1))}
                                    disabled={dataPage <= 1}
                                >
                                    Prev
                                </ActionButton>
                                <ActionButton
                                    variant='secondary'
                                    onClick={() => setDataPage(Math.min(tableData.pagination.lastPage, dataPage + 1))}
                                    disabled={dataPage >= tableData.pagination.lastPage}
                                >
                                    Next
                                </ActionButton>
                            </div>
                        </div>
                    </div>
                ) : (
                    <div className='text-center py-8'>No data available</div>
                )}
            </Modal>

            {!tables || tables.length === 0 ? (
                <div className='flex flex-col items-center justify-center min-h-[60vh] py-12 px-4'>
                    <div className='text-center'>
                        <div className='w-16 h-16 mx-auto mb-4 rounded-full bg-[#ffffff11] flex items-center justify-center'>
                            <LayoutHeaderCellsLarge className='w-8 h-8 text-zinc-400' fill='currentColor' />
                        </div>
                        <h3 className='text-lg font-medium text-zinc-200 mb-2'>No tables found</h3>
                        <p className='text-sm text-zinc-400 max-w-sm'>
                            There are no tables available for this database. Create a new table to get started.
                        </p>
                    </div>
                </div>
            ) : (
                <PageListContainer>
                    {tables.map((table) => (
                        <PageListItem
                            key={table.name}
                            title={table.name}
                            subtitle={`${table.rowCount} rows • ${table.sizeFormatted}`}
                        >
                            <div className='flex items-center gap-2'>
                                <ActionButton variant='secondary' onClick={() => handleView(table)}>
                                    <Eye className='w-4 h-4 mr-2' fill='currentColor' />
                                    View
                                </ActionButton>
                                <ActionButton variant='secondary' onClick={() => handleViewData(table)}>
                                    <Database className='w-4 h-4 mr-2' fill='currentColor' />
                                    Data
                                </ActionButton>
                                <Can action={'database.*'} matchAny>
                                    <ActionButton variant='danger' onClick={() => handleDelete(table)}>
                                        <TrashBin className='w-4 h-4 mr-2' fill='currentColor' />
                                        Delete
                                    </ActionButton>
                                </Can>
                            </div>
                        </PageListItem>
                    ))}
                </PageListContainer>
            )}
        </ServerContentBlock>
    );
};

export default TableBrowserContainer;
