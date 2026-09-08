import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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
import { index, update } from '@/routes/ceo/purchase-requests';
import type { CeoAllowedAction, PurchaseRequest } from '@/types';

type Props = {
    purchaseRequest: PurchaseRequest;
    allowedActions: CeoAllowedAction[];
};

export default function CeoPurchaseRequestShow({
    purchaseRequest,
    allowedActions,
}: Props) {
    const items = purchaseRequest.items ?? [];
    const canApprove = allowedActions.includes('approve');
    const canReject = allowedActions.includes('reject');

    return (
        <>
            <Head title={purchaseRequest.pr_number ?? 'Purchase request'} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={purchaseRequest.pr_number ?? 'Purchase request'}
                        description={`${purchaseRequest.department?.name ?? 'Department'} · ${purchaseRequest.requester?.name ?? 'Requester'}`}
                    />
                    <div className="flex items-center gap-2">
                        <Badge
                            variant={purchaseRequestStatusBadgeVariant(
                                purchaseRequest.status,
                            )}
                        >
                            {purchaseRequestStatusLabel(purchaseRequest.status)}
                        </Badge>
                        <Button variant="outline" asChild>
                            <Link href={index()}>Queue</Link>
                        </Button>
                    </div>
                </div>

                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    {purchaseRequest.earmark_id && (
                        <div>
                            <dt className="text-muted-foreground">Earmark</dt>
                            <dd>{purchaseRequest.earmark_id}</dd>
                        </div>
                    )}
                    {purchaseRequest.funding_source && (
                        <div>
                            <dt className="text-muted-foreground">Funding</dt>
                            <dd>{purchaseRequest.funding_source}</dd>
                        </div>
                    )}
                    {purchaseRequest.resolution_number && (
                        <div>
                            <dt className="text-muted-foreground">
                                Resolution
                            </dt>
                            <dd>{purchaseRequest.resolution_number}</dd>
                        </div>
                    )}
                    {purchaseRequest.procurement_method && (
                        <div>
                            <dt className="text-muted-foreground">Method</dt>
                            <dd>{purchaseRequest.procurement_method}</dd>
                        </div>
                    )}
                </dl>

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

                {purchaseRequest.rejection_reason && (
                    <p className="text-sm">
                        <span className="text-muted-foreground">
                            Deferral reason:{' '}
                        </span>
                        {purchaseRequest.rejection_reason}
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
                                    <TableCell className="font-medium">
                                        {item.is_lot
                                            ? (item.lot_name ?? item.item_name)
                                            : item.item_name}
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

                {(canApprove || canReject) && (
                    <div className="grid max-w-5xl gap-6 lg:grid-cols-2">
                        {canApprove && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Approve</CardTitle>
                                    <CardDescription>
                                        Sets Small Value Procurement, mints a
                                        resolution number, and sends the
                                        request to BAC.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Form
                                        {...update.form(purchaseRequest.id)}
                                        disableWhileProcessing
                                    >
                                        {({ processing }) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="decision"
                                                    value="approve"
                                                />
                                                <Button type="submit">
                                                    {processing && <Spinner />}
                                                    Approve for BAC
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </CardContent>
                            </Card>
                        )}
                        {canReject && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Defer</CardTitle>
                                    <CardDescription>
                                        Closes the request. The status is shown
                                        as Deferred.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Form
                                        {...update.form(purchaseRequest.id)}
                                        disableWhileProcessing
                                        className="space-y-3"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <input
                                                    type="hidden"
                                                    name="decision"
                                                    value="reject"
                                                />
                                                <div className="grid gap-2">
                                                    <Label htmlFor="rejection_reason">
                                                        Reason
                                                    </Label>
                                                    <Input
                                                        id="rejection_reason"
                                                        name="rejection_reason"
                                                        required
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.rejection_reason
                                                        }
                                                    />
                                                </div>
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                >
                                                    {processing && <Spinner />}
                                                    Defer
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

CeoPurchaseRequestShow.layout = {
    breadcrumbs: [{ title: 'CEO requests', href: index() }],
};
