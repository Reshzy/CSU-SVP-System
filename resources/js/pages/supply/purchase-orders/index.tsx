import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    createDataTableColumnHelper,
    DataTable,
    humanizeStatus,
    type DataTableFilterDef,
    type TableQueryFilters,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { index, preview, show } from '@/routes/supply/purchase-orders';
import type { Paginated } from '@/types';

type PurchaseOrderRow = {
    id: number;
    po_number: string;
    status: string;
    total_amount: string | null;
    created_at?: string;
    supplier?: { business_name: string } | null;
    purchase_request?: { pr_number: string | null } | null;
};

type ReadyPr = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    status: string;
    department?: { name: string } | null;
};

type Props = {
    purchaseOrders: Paginated<PurchaseOrderRow>;
    readyPurchaseRequests: ReadyPr[];
    filters: TableQueryFilters;
    statusOptions: DataTableFilterDef['options'];
};

const orderHelper = createDataTableColumnHelper<PurchaseOrderRow>();
const readyHelper = createDataTableColumnHelper<ReadyPr>();

export default function SupplyPurchaseOrdersIndex({
    purchaseOrders,
    readyPurchaseRequests,
    filters,
    statusOptions,
}: Props) {
    const orderColumns = useMemo(
        () => [
            orderHelper.accessor('po_number', {
                header: 'PO number',
                cell: ({ getValue }) => (
                    <span className="font-medium">{getValue()}</span>
                ),
            }),
            orderHelper.accessor((row) => row.purchase_request?.pr_number ?? '', {
                id: 'pr_number',
                header: 'PR',
                enableSorting: false,
            }),
            orderHelper.accessor((row) => row.supplier?.business_name ?? '', {
                id: 'supplier',
                header: 'Supplier',
                enableSorting: false,
            }),
            orderHelper.accessor('status', {
                header: 'Status',
                cell: ({ getValue }) => (
                    <span className="capitalize">{humanizeStatus(getValue())}</span>
                ),
            }),
            orderHelper.accessor('total_amount', {
                header: 'Amount',
                cell: ({ getValue }) =>
                    Number(getValue() ?? 0).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                    }),
            }),
        ],
        [],
    );

    const readyColumns = useMemo(
        () => [
            readyHelper.accessor('pr_number', { header: 'PR number' }),
            readyHelper.accessor('pr_title', { header: 'Title' }),
            readyHelper.accessor((row) => row.department?.name ?? '', {
                id: 'department',
                header: 'Department',
            }),
            readyHelper.accessor('status', {
                header: 'Status',
                cell: ({ getValue }) => (
                    <span className="capitalize">{humanizeStatus(getValue())}</span>
                ),
            }),
        ],
        [],
    );

    return (
        <>
            <Head title="Purchase orders" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Purchase orders"
                    description="Create POs from AOQ winners and track delivery."
                />

                {readyPurchaseRequests.length > 0 && (
                    <div className="space-y-2">
                        <h2 className="text-sm font-medium">Ready for PO</h2>
                        <DataTable
                            tableId="ready-for-po"
                            mode="client"
                            data={readyPurchaseRequests}
                            columns={readyColumns}
                            searchPlaceholder="Search ready PRs"
                            initialPinning={{ start: ['pr_number'] }}
                            rowActions={(row) => [
                                {
                                    label: 'Create PO',
                                    href: preview.url(row.id),
                                },
                            ]}
                            emptyMessage="No purchase requests are ready for a PO."
                        />
                    </div>
                )}

                <DataTable
                    tableId="supply-purchase-orders"
                    visitUrl={index.url()}
                    paginated={purchaseOrders}
                    columns={orderColumns}
                    filters={filters}
                    searchPlaceholder="PO number, PR, or supplier"
                    filterDefs={[
                        { id: 'status', label: 'Status', options: statusOptions },
                    ]}
                    dateColumn="created_at"
                    dateLabel="Created"
                    initialPinning={{ start: ['po_number'] }}
                    rowActions={(row) => [
                        { label: 'View', href: show.url(row.id) },
                    ]}
                    emptyMessage="No purchase orders yet."
                />
            </div>
        </>
    );
}

SupplyPurchaseOrdersIndex.layout = {
    breadcrumbs: [{ title: 'Purchase orders', href: index() }],
};
