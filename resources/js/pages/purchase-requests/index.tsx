import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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
import { create, index, show } from '@/routes/purchase-requests';
import type { Paginated, PurchaseRequest } from '@/types';

type Props = {
    requests: Paginated<PurchaseRequest>;
    canCreate: boolean;
};

export default function PurchaseRequestsIndex({ requests, canCreate }: Props) {
    return (
        <>
            <Head title="Purchase requests" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="Purchase requests"
                        description="Requests you submitted from your department PPMP."
                    />
                    {canCreate && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                New request
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>PR number</TableHead>
                                <TableHead>Purpose</TableHead>
                                <TableHead className="text-right">
                                    Estimated
                                </TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {requests.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground py-10 text-center"
                                    >
                                        No purchase requests yet.
                                    </TableCell>
                                </TableRow>
                            )}

                            {requests.data.map((request) => (
                                <TableRow key={request.id}>
                                    <TableCell className="font-medium">
                                        {request.pr_number}
                                    </TableCell>
                                    <TableCell>{request.purpose}</TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {request.estimated_total}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={purchaseRequestStatusBadgeVariant(
                                                request.status,
                                            )}
                                        >
                                            {purchaseRequestStatusLabel(
                                                request.status,
                                            )}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={show(request.id)}>
                                                Open
                                            </Link>
                                        </Button>
                                    </TableCell>
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

PurchaseRequestsIndex.layout = {
    breadcrumbs: [{ title: 'Purchase requests', href: index() }],
};
