import { Head, Link, router } from '@inertiajs/react';
import {
    createColumnHelper,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useMemo } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/supply/purchase-requests';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    status: string;
    estimated_total: string;
    department?: { name: string } | null;
    requester?: { name: string } | null;
};

type Props = {
    purchaseRequests: Paginated<Row>;
};

const columnHelper = createColumnHelper<Row>();

export default function SupplyPurchaseRequestIndex({ purchaseRequests }: Props) {
    const columns = useMemo(
        () => [
            columnHelper.accessor('pr_number', {
                header: 'PR number',
                cell: (info) => (
                    <Link
                        href={show.url(info.row.original.id)}
                        className="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        {info.getValue() ?? `#${info.row.original.id}`}
                    </Link>
                ),
            }),
            columnHelper.accessor('pr_title', { header: 'Title' }),
            columnHelper.accessor('status', {
                header: 'Status',
                cell: (info) => (
                    <span className="capitalize">
                        {info.getValue() === 'rejected' ? 'Deferred' : info.getValue().replaceAll('_', ' ')}
                    </span>
                ),
            }),
            columnHelper.accessor('estimated_total', {
                header: 'Total',
                cell: (info) =>
                    Number(info.getValue()).toLocaleString(undefined, { minimumFractionDigits: 2 }),
            }),
            columnHelper.accessor((row) => row.requester?.name ?? '', { id: 'requester', header: 'Requester' }),
            columnHelper.display({
                id: 'actions',
                header: 'Actions',
                cell: (info) => (
                    <Button
                        size="sm"
                        onClick={() =>
                            router.post(show.url(info.row.original.id).replace(/\/$/, '') + '/status', {
                                action: 'activate',
                            })
                        }
                    >
                        Activate
                    </Button>
                ),
            }),
        ],
        [],
    );

    const table = useReactTable({
        data: purchaseRequests.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <Head title="Supply queue" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Supply queue" description="Review and activate purchase requests." />
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            {table.getHeaderGroups().map((headerGroup) => (
                                <tr key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <th key={header.id} className="px-3 py-2 font-medium">
                                            {flexRender(header.column.columnDef.header, header.getContext())}
                                        </th>
                                    ))}
                                </tr>
                            ))}
                        </thead>
                        <tbody>
                            {table.getRowModel().rows.length === 0 && (
                                <tr>
                                    <td colSpan={columns.length} className="px-3 py-8 text-center text-muted-foreground">
                                        No requests in the Supply queue.
                                    </td>
                                </tr>
                            )}
                            {table.getRowModel().rows.map((row) => (
                                <tr key={row.id} className="border-t">
                                    {row.getVisibleCells().map((cell) => (
                                        <td key={cell.id} className="px-3 py-2">
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

SupplyPurchaseRequestIndex.layout = {
    breadcrumbs: [{ title: 'Supply queue', href: index() }],
};
