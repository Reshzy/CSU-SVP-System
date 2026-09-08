import { Form, Head, Link } from '@inertiajs/react';
import { EarmarkForm } from '@/components/earmark-form';
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
import {
    amend,
    exportEarmark,
    index,
    reject,
    update,
} from '@/routes/budget/purchase-requests';
import type {
    BudgetAllowedAction,
    FundClusterOption,
    PurchaseRequest,
} from '@/types';

type Props = {
    purchaseRequest: PurchaseRequest;
    allowedActions: BudgetAllowedAction[];
    fundClusters: FundClusterOption[];
};

export default function BudgetPurchaseRequestEdit({
    purchaseRequest,
    allowedActions,
    fundClusters,
}: Props) {
    const items = purchaseRequest.items ?? [];
    const canApprove = allowedActions.includes('approve');
    const canReject = allowedActions.includes('reject');
    const canExport = allowedActions.includes('export');
    const canAmend = allowedActions.includes('amend');

    return (
        <>
            <Head title={purchaseRequest.pr_number ?? 'Earmark'} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={purchaseRequest.pr_number ?? 'Purchase request'}
                        description={`${purchaseRequest.department?.name ?? 'Department'} · ${purchaseRequest.requester?.name ?? 'Requester'}`}
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge
                            variant={purchaseRequestStatusBadgeVariant(
                                purchaseRequest.status,
                            )}
                        >
                            {purchaseRequestStatusLabel(purchaseRequest.status)}
                        </Badge>
                        {canExport && (
                            <Button variant="outline" asChild>
                                <a
                                    href={exportEarmark.url(purchaseRequest.id)}
                                >
                                    Export earmark
                                </a>
                            </Button>
                        )}
                        {canAmend && (
                            <Button variant="outline" asChild>
                                <Link href={amend(purchaseRequest.id)}>
                                    Amend earmark
                                </Link>
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <Link href={index()}>Queue</Link>
                        </Button>
                    </div>
                </div>

                {purchaseRequest.earmark_id && (
                    <p className="text-sm">
                        <span className="text-muted-foreground">
                            Earmark:{' '}
                        </span>
                        {purchaseRequest.earmark_id}
                    </p>
                )}

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
                                                ? (item.lot_name ??
                                                  item.item_name)
                                                : item.item_name}
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

                {canApprove && (
                    <div className="rounded-xl border p-4">
                        <h2 className="mb-4 text-sm font-medium">
                            Earmark and approve
                        </h2>
                        <EarmarkForm
                            actionUrl={update.url(purchaseRequest.id)}
                            method="put"
                            purchaseRequest={purchaseRequest}
                            fundClusters={fundClusters}
                            submitLabel="Approve earmark"
                        />
                    </div>
                )}

                {canReject && (
                    <Card className="max-w-xl">
                        <CardHeader>
                            <CardTitle>Defer</CardTitle>
                            <CardDescription>
                                Closes the request. The status is shown as
                                Deferred.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...reject.form(purchaseRequest.id)}
                                disableWhileProcessing
                                className="space-y-3"
                            >
                                {({ processing, errors }) => (
                                    <>
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
        </>
    );
}

BudgetPurchaseRequestEdit.layout = {
    breadcrumbs: [{ title: 'Budget requests', href: index() }],
};
