import { Head, Link, router } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    createDataTableColumnHelper,
    DataTable,
    humanizeStatus,
    type DataTableFilterDef,
    type TableQueryFilters,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { index, show, status } from '@/routes/supply/purchase-requests';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    status: string;
    estimated_total: string;
    department?: { name: string } | null;
    requester?: { name: string } | null;
    created_at?: string;
};

type Props = {
    purchaseRequests: Paginated<Row>;
    filters: TableQueryFilters;
    statusOptions: DataTableFilterDef['options'];
};

const columnHelper = createDataTableColumnHelper<Row>();

export default function SupplyPurchaseRequestIndex({
    purchaseRequests,
    filters,
    statusOptions,
}: Props) {
    const columns = useMemo(
        () => [
            columnHelper.accessor('pr_number', {
                header: 'PR number',
                cell: ({ row, getValue }) => (
                    <Link
                        href={show.url(row.original.id)}
                        className="text-primary font-medium underline-offset-4 hover:underline"
                    >
                        {getValue() ?? `#${row.original.id}`}
                    </Link>
                ),
            }),
            columnHelper.accessor('pr_title', { header: 'Title' }),
            columnHelper.accessor('status', {
                header: 'Status',
                cell: ({ getValue }) => (
                    <span className="capitalize">{humanizeStatus(getValue())}</span>
                ),
            }),
            columnHelper.accessor('estimated_total', {
                header: 'Total',
                cell: ({ getValue }) =>
                    Number(getValue()).toLocaleString(undefined, {
                        minimumFractionDigits: 2,
                    }),
            }),
            columnHelper.accessor((row) => row.requester?.name ?? '', {
                id: 'requester',
                header: 'Requester',
                enableSorting: false,
            }),
        ],
        [],
    );

    return (
        <>
            <Head title="Supply queue" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Supply queue"
                    description="Review and activate purchase requests."
                />
                <DataTable
                    tableId="supply-purchase-requests"
                    visitUrl={index.url()}
                    paginated={purchaseRequests}
                    columns={columns}
                    filters={filters}
                    searchPlaceholder="PR number or title"
                    filterDefs={[
                        { id: 'status', label: 'Status', options: statusOptions },
                    ]}
                    dateColumn="created_at"
                    dateLabel="Created"
                    initialPinning={{ start: ['pr_number'] }}
                    rowActions={(row) => [
                        { label: 'View', href: show.url(row.id) },
                        {
                            label: 'Activate',
                            onClick: () =>
                                router.post(status.url(row.id), {
                                    action: 'activate',
                                }),
                        },
                    ]}
                    emptyMessage="No requests in the Supply queue."
                />
            </div>
        </>
    );
}

SupplyPurchaseRequestIndex.layout = {
    breadcrumbs: [{ title: 'Supply queue', href: index() }],
};
