import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo } from 'react';
import {
    createDataTableColumnHelper,
    DataTable,
    humanizeStatus,
    type DataTableFilterDef,
    type TableQueryFilters,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/purchase-requests';
import type { Paginated } from '@/types';

type PurchaseRequestRow = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    status: string;
    estimated_total: string;
    department?: { name: string } | null;
    requester?: { name: string } | null;
    created_at: string;
};

type Props = {
    purchaseRequests: Paginated<PurchaseRequestRow>;
    filters: TableQueryFilters;
    statusOptions: DataTableFilterDef['options'];
    canCreate: boolean;
};

const columnHelper = createDataTableColumnHelper<PurchaseRequestRow>();

export default function PurchaseRequestIndex({
    purchaseRequests,
    filters,
    statusOptions,
    canCreate,
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
            columnHelper.accessor('created_at', {
                header: 'Created',
                cell: ({ getValue }) =>
                    new Date(getValue()).toLocaleDateString(),
            }),
        ],
        [],
    );

    return (
        <>
            <Head title="Purchase requests" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Purchase requests"
                        description="Create and track department purchase requests."
                    />
                    {canCreate && (
                        <Button asChild>
                            <Link href={create.url()}>
                                <Plus className="size-4" />
                                New request
                            </Link>
                        </Button>
                    )}
                </div>

                <DataTable
                    tableId="purchase-requests"
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
                    ]}
                    emptyMessage="No purchase requests yet."
                />
            </div>
        </>
    );
}

PurchaseRequestIndex.layout = {
    breadcrumbs: [{ title: 'Purchase requests', href: index() }],
};
