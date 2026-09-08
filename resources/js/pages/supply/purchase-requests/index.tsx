import { Head, Link } from '@inertiajs/react';
import { createColumnHelper, flexRender } from '@tanstack/react-table';
import {
    getCoreRowModel,
    useLegacyTable,
} from '@tanstack/react-table/legacy';
import { useMemo } from 'react';
import Heading from '@/components/heading';
import { PaginationNav } from '@/components/pagination-nav';
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
import { index, show } from '@/routes/supply/purchase-requests';
import type { Paginated, PurchaseRequest, PurchaseRequestStatus } from '@/types';

const queueStatuses: PurchaseRequestStatus[] = [
    'submitted',
    'supply_office_review',
    'budget_office_review',
    'returned_by_supply',
    'rejected',
    'cancelled',
];

const columnHelper = createColumnHelper<PurchaseRequest>();

type Props = {
    requests: Paginated<PurchaseRequest>;
    filters: { status: PurchaseRequestStatus };
    statusCounts: Partial<Record<PurchaseRequestStatus, number>>;
};

export default function SupplyPurchaseRequestsIndex({
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
            columnHelper.accessor(
                (row) => row.department?.name ?? '—',
                {
                    id: 'department',
                    header: 'Department',
                },
            ),
            columnHelper.accessor(
                (row) => row.requester?.name ?? '—',
                {
                    id: 'requester',
                    header: 'Requester',
                },
            ),
            columnHelper.accessor('purpose', {
                header: 'Purpose',
            }),
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
            <Head title="Supply purchase requests" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Purchase requests"
                    description="Review department requests, group lots, and send them to Budget Office."
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
                                        <TableHead
                                            key={header.id}
                                            className={
                                                header.id === 'estimated_total'
                                                    ? 'text-right'
                                                    : header.id === 'actions'
                                                      ? 'w-0'
                                                      : undefined
                                            }
                                        >
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
                                        <TableCell
                                            key={cell.id}
                                            className={
                                                cell.column.id ===
                                                'estimated_total'
                                                    ? 'text-right'
                                                    : undefined
                                            }
                                        >
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

                <PaginationNav
                    links={requests.links}
                    from={requests.from}
                    to={requests.to}
                    total={requests.total}
                />
            </div>
        </>
    );
}

SupplyPurchaseRequestsIndex.layout = {
    breadcrumbs: [{ title: 'Supply requests', href: index() }],
};
