import { Head, Link } from '@inertiajs/react';
import { createColumnHelper, flexRender } from '@tanstack/react-table';
import {
    getCoreRowModel,
    useLegacyTable,
} from '@tanstack/react-table/legacy';
import { useMemo } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    purchaseRequestStatusBadgeVariant,
    purchaseRequestStatusLabel,
} from '@/lib/purchase-request-status';
import { index, show } from '@/routes/ceo/purchase-requests';
import type { Paginated, PurchaseRequest, PurchaseRequestStatus } from '@/types';

const queueStatuses: PurchaseRequestStatus[] = [
    'ceo_approval',
    'bac_evaluation',
    'rejected',
];

const columnHelper = createColumnHelper<PurchaseRequest>();

type Props = {
    requests: Paginated<PurchaseRequest>;
    filters: { status: PurchaseRequestStatus };
    statusCounts: Partial<Record<PurchaseRequestStatus, number>>;
};

export default function CeoPurchaseRequestsIndex({
    requests,
    filters,
    statusCounts,
}: Props) {
    const columns = useMemo(
        () => [
            columnHelper.accessor('pr_number', {
                header: 'PR number',
                cell: (info) => (
                    <span className="font-medium">{info.getValue()}</span>
                ),
            }),
            columnHelper.accessor((row) => row.department?.name ?? '—', {
                id: 'department',
                header: 'Department',
            }),
            columnHelper.accessor((row) => row.requester?.name ?? '—', {
                id: 'requester',
                header: 'Requester',
            }),
            columnHelper.accessor('purpose', { header: 'Purpose' }),
            columnHelper.accessor('estimated_total', {
                header: 'Estimated',
                cell: (info) => (
                    <span className="tabular-nums">{info.getValue()}</span>
                ),
            }),
            columnHelper.accessor('status', {
                header: 'Status',
                cell: (info) => (
                    <Badge
                        variant={purchaseRequestStatusBadgeVariant(
                            info.getValue(),
                        )}
                    >
                        {purchaseRequestStatusLabel(info.getValue())}
                    </Badge>
                ),
            }),
            columnHelper.display({
                id: 'actions',
                header: '',
                cell: (info) => (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={show(info.row.original.id)}>Review</Link>
                    </Button>
                ),
            }),
        ],
        [],
    );

    const table = useLegacyTable({
        data: requests.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <Head title="CEO purchase requests" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Purchase requests"
                    description="Approve earmarked requests for Small Value Procurement."
                />

                <div className="flex flex-wrap gap-1">
                    {queueStatuses.map((status) => (
                        <Button
                            key={status}
                            variant={
                                status === filters.status ? 'default' : 'ghost'
                            }
                            size="sm"
                            asChild
                        >
                            <Link href={index({ query: { status } }).url}>
                                {purchaseRequestStatusLabel(status)}
                                <span className="text-muted-foreground ml-1.5 tabular-nums">
                                    {statusCounts[status] ?? 0}
                                </span>
                            </Link>
                        </Button>
                    ))}
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <TableHead key={header.id}>
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                      header.column.columnDef
                                                          .header,
                                                      header.getContext(),
                                                  )}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {table.getRowModel().rows.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={columns.length}
                                        className="text-muted-foreground py-10 text-center"
                                    >
                                        No{' '}
                                        {purchaseRequestStatusLabel(
                                            filters.status,
                                        ).toLowerCase()}{' '}
                                        requests.
                                    </TableCell>
                                </TableRow>
                            )}
                            {table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

CeoPurchaseRequestsIndex.layout = {
    breadcrumbs: [{ title: 'CEO requests', href: index() }],
};
