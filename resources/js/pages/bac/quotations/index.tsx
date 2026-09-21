import { Head, Link } from '@inertiajs/react';
import {
    createColumnHelper,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useMemo } from 'react';
import Heading from '@/components/heading';
import { index, manage } from '@/routes/bac/quotations';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    status: string;
};

type Props = { purchaseRequests: Paginated<Row> };

const columnHelper = createColumnHelper<Row>();

export default function BacQuotationsIndex({ purchaseRequests }: Props) {
    const columns = useMemo(
        () => [
            columnHelper.accessor('pr_number', {
                header: 'PR',
                cell: (info) => (
                    <Link
                        href={manage.url(info.row.original.id)}
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
                        {info.getValue() === 'rejected'
                            ? 'Deferred'
                            : info.getValue().replaceAll('_', ' ')}
                    </span>
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
            <Head title="BAC quotations" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="BAC quotations"
                    description="Canvass RFQ, quotations, and AOQ."
                />
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            {table.getHeaderGroups().map((headerGroup) => (
                                <tr key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <th
                                            key={header.id}
                                            className="px-3 py-2 font-medium"
                                        >
                                            {flexRender(
                                                header.column.columnDef.header,
                                                header.getContext(),
                                            )}
                                        </th>
                                    ))}
                                </tr>
                            ))}
                        </thead>
                        <tbody>
                            {table.getRowModel().rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={columns.length}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No purchase requests in BAC evaluation.
                                    </td>
                                </tr>
                            )}
                            {table.getRowModel().rows.map((row) => (
                                <tr key={row.id} className="border-t">
                                    {row.getVisibleCells().map((cell) => (
                                        <td key={cell.id} className="px-3 py-2">
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
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

BacQuotationsIndex.layout = {
    breadcrumbs: [{ title: 'BAC quotations', href: index() }],
};
