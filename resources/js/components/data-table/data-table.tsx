import {
    createColumnHelper,
    useTable,
    type RowData,
    type SortingState,
    type Updater,
} from '@tanstack/react-table';
import { useEffect, useMemo, useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';
import { DataTableBulkBar } from './bulk-bar';
import { DataTableColumnHeader } from './column-header';
import { exportTableCsv } from './export-csv';
import { dataTableFeatures, type DataTableFeatures } from './features';
import { DataTablePagination } from './pagination';
import { DataTableRowActions } from './row-actions';
import { DataTableToolbar } from './toolbar';
import type {
    DataTableBulkAction,
    DataTableColumn,
    DataTableFilterDef,
    DataTableMode,
    DataTableRowAction,
    PageSize,
    TableQueryFilters,
} from './types';
import { PAGE_SIZES } from './types';
import { useInertiaTable } from './use-inertia-table';
import { useTablePreferences } from './use-table-preferences';
import { asFilterString, pinnedStyle } from './utils';

type DataTableProps<TData extends RowData> = {
    tableId: string;
    columns: DataTableColumn<TData, any>[];
    mode?: DataTableMode;
    visitUrl?: string;
    paginated?: Paginated<TData>;
    data?: TData[];
    filters?: TableQueryFilters;
    searchPlaceholder?: string;
    filterDefs?: DataTableFilterDef[];
    dateColumn?: string;
    dateLabel?: string;
    rowActions?: (row: TData) => DataTableRowAction<TData>[];
    bulkActions?: DataTableBulkAction<TData>[];
    emptyMessage?: string;
    noResultsMessage?: string;
    getRowId?: (row: TData) => string;
    initialPinning?: { start?: string[]; end?: string[] };
};

const columnHelper = createColumnHelper<DataTableFeatures, RowData>();

export function DataTable<TData extends RowData>({
    tableId,
    columns,
    mode = 'server',
    visitUrl,
    paginated,
    data = [],
    filters = {},
    searchPlaceholder = 'Search',
    filterDefs = [],
    dateColumn,
    dateLabel,
    rowActions,
    bulkActions,
    emptyMessage = 'No records yet.',
    noResultsMessage = 'No results match the current filters.',
    getRowId = (row) => String((row as { id: string | number }).id),
    initialPinning,
}: DataTableProps<TData>) {
    const isServer = mode === 'server';
    const defaultPageSize = resolvePageSize(
        filters.per_page ?? paginated?.per_page,
        15,
    );
    const defaultPinning = {
        start: ['select', ...(initialPinning?.start ?? [])],
        end: [...(initialPinning?.end ?? []), ...(rowActions ? ['actions'] : [])],
    };

    const { preferences, setPreferences, resetPreferences } = useTablePreferences(
        tableId,
        {
            columnVisibility: {},
            columnSizing: {},
            columnPinning: defaultPinning,
            pageSize: defaultPageSize,
        },
    );

    const { isLoading, visit, debounceMs } = useInertiaTable(visitUrl);
    const [searchInput, setSearchInput] = useState(asFilterString(filters.search));
    const [clientQuery, setClientQuery] = useState<TableQueryFilters>({
        search: '',
        page: 1,
        per_page: preferences.pageSize,
    });

    useEffect(() => {
        setSearchInput(asFilterString(filters.search));
    }, [filters.search]);

    const query = isServer ? filters : clientQuery;

    const sourceRows = useMemo(() => {
        const rows = isServer ? (paginated?.data ?? []) : data;

        if (isServer) {
            return rows;
        }

        return applyClientFilters(rows, query, filterDefs, dateColumn);
    }, [data, dateColumn, filterDefs, isServer, paginated?.data, query]);

    const selectColumn = useMemo(
        () =>
            columnHelper.display({
                id: 'select',
                header: ({ table }) => (
                    <Checkbox
                        checked={
                            table.getIsAllPageRowsSelected()
                                ? true
                                : table.getIsSomePageRowsSelected()
                                  ? 'indeterminate'
                                  : false
                        }
                        onCheckedChange={(value) =>
                            table.toggleAllPageRowsSelected(!!value)
                        }
                        aria-label="Select all rows on this page"
                    />
                ),
                cell: ({ row }) => (
                    <Checkbox
                        checked={row.getIsSelected()}
                        onCheckedChange={(value) => row.toggleSelected(!!value)}
                        aria-label="Select row"
                    />
                ),
                enableSorting: false,
                enableHiding: false,
                enableResizing: false,
                enablePinning: true,
                size: 40,
                minSize: 40,
                maxSize: 40,
            }) as DataTableColumn<TData>,
        [],
    );

    const actionsColumn = useMemo(() => {
        if (!rowActions) {
            return null;
        }

        return columnHelper.display({
            id: 'actions',
            header: 'Actions',
            cell: ({ row }) => (
                <DataTableRowActions
                    row={row.original as TData}
                    actions={rowActions(row.original as TData)}
                />
            ),
            enableSorting: false,
            enableHiding: false,
            enableResizing: false,
            size: 64,
            minSize: 64,
        }) as DataTableColumn<TData>;
    }, [rowActions]);

    const tableColumns = useMemo(() => {
        const next = [selectColumn, ...columns];

        if (actionsColumn) {
            next.push(actionsColumn);
        }

        return next;
    }, [actionsColumn, columns, selectColumn]);

    const sorting: SortingState =
        query.sort && typeof query.sort === 'string'
            ? [{ id: query.sort, desc: query.direction !== 'asc' }]
            : [];

    const pageSize = resolvePageSize(
        query.per_page ?? preferences.pageSize,
        preferences.pageSize,
    );
    const pageIndex = Math.max(0, (Number(query.page) || paginated?.current_page || 1) - 1);

    const table = useTable({
        features: dataTableFeatures,
        data: sourceRows,
        columns: tableColumns,
        getRowId,
        enableMultiSort: false,
        enableSortingRemoval: true,
        columnResizeMode: 'onChange',
        manualPagination: isServer,
        manualSorting: isServer,
        manualFiltering: true,
        rowCount: isServer ? (paginated?.total ?? 0) : sourceRows.length,
        state: {
            sorting,
            pagination: { pageIndex, pageSize },
            columnVisibility: preferences.columnVisibility,
            columnSizing: preferences.columnSizing,
            columnPinning: preferences.columnPinning,
        },
        onColumnVisibilityChange: (updater) =>
            setPreferences((current) => ({
                ...current,
                columnVisibility: valueFromUpdater(updater, current.columnVisibility),
            })),
        onColumnSizingChange: (updater) =>
            setPreferences((current) => ({
                ...current,
                columnSizing: valueFromUpdater(updater, current.columnSizing),
            })),
        onColumnPinningChange: (updater) =>
            setPreferences((current) => ({
                ...current,
                columnPinning: valueFromUpdater(updater, current.columnPinning),
            })),
        onSortingChange: (updater) => {
            const next = valueFromUpdater(updater, sorting);
            const first = next[0];
            updateQuery({
                sort: first?.id ?? null,
                direction: first ? (first.desc ? 'desc' : 'asc') : null,
                page: 1,
            });
        },
        onPaginationChange: (updater) => {
            const next = valueFromUpdater(updater, { pageIndex, pageSize });
            updateQuery({
                page: next.pageIndex + 1,
                per_page: next.pageSize,
            });
        },
    });

    function updateQuery(patch: TableQueryFilters, options?: { debounceMs?: number }) {
        const next = {
            ...query,
            ...patch,
        };

        if (isServer) {
            visit(next, { debounceMs: options?.debounceMs ?? 0 });
            return;
        }

        setClientQuery(next);
    }

    const handleSearchChange = (value: string) => {
        setSearchInput(value);

        if (isServer) {
            visit({ ...query, search: value || null, page: 1 }, { debounceMs });
            return;
        }

        setClientQuery((current) => ({ ...current, search: value || null, page: 1 }));
    };

    const selectedRows = table
        .getSelectedRowModel()
        .rows.map((row) => row.original as TData);

    const defaultBulkActions: DataTableBulkAction<TData>[] = [
        {
            label: 'Export CSV',
            onAction: () => exportTableCsv(table, tableId),
        },
        ...(bulkActions ?? []),
    ];

    const from = isServer
        ? (paginated?.from ?? null)
        : sourceRows.length === 0
          ? null
          : pageIndex * pageSize + 1;
    const to = isServer
        ? (paginated?.to ?? null)
        : Math.min(sourceRows.length, (pageIndex + 1) * pageSize);
    const total = isServer ? (paginated?.total ?? 0) : sourceRows.length;
    const hasActiveQuery = Boolean(
        query.search ||
            query.date_from ||
            query.date_to ||
            filterDefs.some((filter) => asFilterString(query[filter.id])),
    );
    const rows = table.getRowModel().rows;

    return (
        <div className="flex flex-col gap-3">
            <DataTableToolbar
                table={table}
                search={searchInput}
                searchPlaceholder={searchPlaceholder}
                onSearchChange={handleSearchChange}
                filters={query}
                filterDefs={filterDefs}
                onFilterChange={(id, value) =>
                    updateQuery({ [id]: value || null, page: 1 })
                }
                dateColumn={dateColumn}
                dateLabel={dateLabel}
                onDateChange={(key, value) =>
                    updateQuery({ [key]: value || null, page: 1 })
                }
                onClearFilters={() => {
                    const cleared: TableQueryFilters = { ...query, search: null, date_from: null, date_to: null, page: 1 };

                    for (const filter of filterDefs) {
                        cleared[filter.id] = null;
                    }

                    setSearchInput('');
                    updateQuery(cleared);
                }}
                onResetLayout={() => {
                    resetPreferences();

                    if (isServer) {
                        visit({ ...query, per_page: defaultPageSize, page: 1 });
                    } else {
                        setClientQuery((current) => ({
                            ...current,
                            per_page: defaultPageSize,
                            page: 1,
                        }));
                    }
                }}
            />

            <DataTableBulkBar
                selected={selectedRows}
                actions={defaultBulkActions}
                onClear={() => table.resetRowSelection()}
            />

            <div className="relative overflow-hidden rounded-xl border">
                {isLoading && (
                    <div className="bg-background/60 absolute inset-0 z-10 flex items-start justify-center pt-16">
                        <div className="w-full space-y-2 px-4">
                            {Array.from({ length: 5 }).map((_, index) => (
                                <Skeleton key={index} className="h-8 w-full" />
                            ))}
                        </div>
                    </div>
                )}

                <div className="max-h-[min(70vh,40rem)] overflow-auto">
                    <Table
                        className="table-fixed"
                        containerClassName="overflow-visible"
                        style={{ width: table.getTotalSize() }}
                    >
                        <TableHeader className="bg-background sticky top-0 z-20">
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <TableHead
                                            key={header.id}
                                            className="relative overflow-hidden"
                                            style={pinnedStyle(header.column)}
                                        >
                                            {header.isPlaceholder
                                                ? null
                                                : typeof header.column.columnDef.header ===
                                                    'string'
                                                  ? (
                                                        <DataTableColumnHeader
                                                            header={header}
                                                            title={header.column.columnDef.header}
                                                        />
                                                    )
                                                  : (
                                                        <table.FlexRender header={header} />
                                                    )}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {rows.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={tableColumns.length}
                                        className="text-muted-foreground py-10 text-center"
                                    >
                                        {hasActiveQuery ? noResultsMessage : emptyMessage}
                                    </TableCell>
                                </TableRow>
                            )}
                            {rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    data-state={row.getIsSelected() ? 'selected' : undefined}
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell
                                            key={cell.id}
                                            style={pinnedStyle(cell.column)}
                                            className={cn(
                                                'overflow-hidden',
                                                cell.column.id === 'actions' && 'text-right',
                                            )}
                                        >
                                            <div className="min-w-0 max-w-full overflow-hidden text-ellipsis">
                                                <table.FlexRender cell={cell} />
                                            </div>
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <DataTablePagination
                table={table}
                from={from}
                to={to}
                total={total}
                onPageChange={(nextIndex) => updateQuery({ page: nextIndex + 1 })}
                onPageSizeChange={(nextSize) => {
                    setPreferences((current) => ({
                        ...current,
                        pageSize: resolvePageSize(nextSize, current.pageSize),
                    }));
                    updateQuery({ per_page: nextSize, page: 1 });
                }}
            />
        </div>
    );
}

function valueFromUpdater<T>(updater: Updater<T>, current: T): T {
    return typeof updater === 'function'
        ? (updater as (value: T) => T)(current)
        : updater;
}

function resolvePageSize(value: unknown, fallback: PageSize): PageSize {
    const numeric = Number(value);

    return (PAGE_SIZES as readonly number[]).includes(numeric)
        ? (numeric as PageSize)
        : fallback;
}

function applyClientFilters<TData>(
    rows: TData[],
    query: TableQueryFilters,
    filterDefs: DataTableFilterDef[],
    dateColumn?: string,
): TData[] {
    const search = asFilterString(query.search).trim().toLowerCase();

    return rows.filter((row) => {
        const record = row as Record<string, unknown>;

        if (search) {
            const haystack = JSON.stringify(row).toLowerCase();

            if (!haystack.includes(search)) {
                return false;
            }
        }

        for (const filter of filterDefs) {
            const value = asFilterString(query[filter.id]);

            if (value && String(record[filter.id] ?? '') !== value) {
                return false;
            }
        }

        if (dateColumn && (query.date_from || query.date_to)) {
            const raw = record[dateColumn];
            const stamp = raw ? new Date(String(raw)).getTime() : Number.NaN;

            if (Number.isNaN(stamp)) {
                return false;
            }

            if (query.date_from && stamp < new Date(String(query.date_from)).getTime()) {
                return false;
            }

            if (query.date_to && stamp > new Date(`${query.date_to}T23:59:59`).getTime()) {
                return false;
            }
        }

        return true;
    });
}
