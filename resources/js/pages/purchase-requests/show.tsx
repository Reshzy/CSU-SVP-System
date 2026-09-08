import { Head, Link } from '@inertiajs/react';
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
import { index } from '@/routes/purchase-requests';
import { create as createReplacement } from '@/routes/purchase-requests/replacement';
import type { PurchaseRequest } from '@/types';

type Props = {
    purchaseRequest: PurchaseRequest;
    canReplace?: boolean;
};

export default function PurchaseRequestShow({
    purchaseRequest,
    canReplace = false,
}: Props) {
    const items = purchaseRequest.items ?? [];

    return (
        <>
            <Head title={purchaseRequest.pr_number ?? 'Purchase request'} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={purchaseRequest.pr_number ?? 'Purchase request'}
                        description={`${purchaseRequest.department?.name ?? 'Department'} · Q${purchaseRequest.pr_quarter ?? '—'} · ${purchaseRequest.estimated_total}`}
                    />
                    <div className="flex items-center gap-2">
                        <Badge
                            variant={purchaseRequestStatusBadgeVariant(
                                purchaseRequest.status,
                            )}
                        >
                            {purchaseRequestStatusLabel(purchaseRequest.status)}
                        </Badge>
                        {canReplace && (
                            <Button asChild>
                                <Link
                                    href={createReplacement(
                                        purchaseRequest.id,
                                    )}
                                >
                                    Create replacement
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <Link href={index()}>All requests</Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-xl border p-4">
                        <h2 className="mb-2 text-sm font-medium">Purpose</h2>
                        <p className="text-sm">{purchaseRequest.purpose}</p>
                    </div>
                    <div className="rounded-xl border p-4">
                        <h2 className="mb-2 text-sm font-medium">
                            Justification
                        </h2>
                        <p className="text-sm">
                            {purchaseRequest.justification}
                        </p>
                    </div>
                </div>

                {purchaseRequest.return_remarks && (
                    <p className="rounded-xl border p-4 text-sm">
                        <span className="font-medium">Supply remarks: </span>
                        {purchaseRequest.return_remarks}
                    </p>
                )}

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Item</TableHead>
                                <TableHead>UOM</TableHead>
                                <TableHead className="text-right">Qty</TableHead>
                                <TableHead className="text-right">
                                    Unit cost
                                </TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {item.is_lot
                                                ? (item.lot_name ?? item.item_name)
                                                : item.item_name}
                                        </div>
                                        <div className="text-muted-foreground text-xs">
                                            {item.is_lot
                                                ? 'Lot'
                                                : item.item_code}
                                            {item.parent_lot_id
                                                ? ' · in lot'
                                                : ''}
                                        </div>
                                    </TableCell>
                                    <TableCell>{item.unit_of_measure}</TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.quantity_requested}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.estimated_unit_cost}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.estimated_total_cost}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

PurchaseRequestShow.layout = {
    breadcrumbs: [{ title: 'Purchase requests', href: index() }],
};
