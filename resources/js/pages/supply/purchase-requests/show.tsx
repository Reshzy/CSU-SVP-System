import { Form, Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import { index, status } from '@/routes/supply/purchase-requests';
import {
    destroy as destroyLot,
    store as storeLot,
    update as updateLot,
} from '@/routes/supply/purchase-requests/lots';
import type {
    PurchaseRequest,
    PurchaseRequestItem,
    SupplyAllowedAction,
    SupplyStandaloneItem,
} from '@/types';

type Props = {
    purchaseRequest: PurchaseRequest;
    canManageLots: boolean;
    allowedActions: SupplyAllowedAction[];
    standalones: SupplyStandaloneItem[];
};

export default function SupplyPurchaseRequestShow({
    purchaseRequest,
    canManageLots,
    allowedActions,
    standalones,
}: Props) {
    const items = purchaseRequest.items ?? [];
    const lots = items.filter((item) => item.is_lot);
    const canStartReview = allowedActions.includes('start_review');
    const canActivate = allowedActions.includes('activate');
    const canReturn = allowedActions.includes('return');
    const canReject = allowedActions.includes('reject');
    const canCancel = allowedActions.includes('cancel');

    return (
        <>
            <Head
                title={purchaseRequest.pr_number ?? 'Purchase request'}
            />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={purchaseRequest.pr_number ?? 'Purchase request'}
                        description={`${purchaseRequest.department?.name ?? 'Department'} · ${purchaseRequest.requester?.name ?? 'Requester'} · Q${purchaseRequest.pr_quarter ?? '—'}`}
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
                    <p className="text-sm">
                        <span className="text-muted-foreground">
                            Return remarks:{' '}
                        </span>
                        {purchaseRequest.return_remarks}
                    </p>
                )}
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
                                    <TableCell>
                                        <div className="font-medium">
                                            {item.is_lot
                                                ? (item.lot_name ??
                                                  item.item_name)
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

                {canManageLots && (
                    <LotManager
                        purchaseRequest={purchaseRequest}
                        lots={lots}
                        items={items}
                        standalones={standalones}
                    />
                )}

                {(canStartReview ||
                    canActivate ||
                    canReturn ||
                    canReject ||
                    canCancel) && (
                    <div className="grid max-w-5xl gap-6 lg:grid-cols-2">
                        {canStartReview && (
                            <ActionCard
                                title="Start review"
                                description="Move this legacy submitted request into Supply Office review."
                                action="start_review"
                                purchaseRequestId={purchaseRequest.id}
                                buttonLabel="Start review"
                            />
                        )}
                        {canActivate && (
                            <ActionCard
                                title="Activate"
                                description="Send this request to Budget Office for earmarking."
                                action="activate"
                                purchaseRequestId={purchaseRequest.id}
                                buttonLabel="Activate for Budget Office"
                            />
                        )}
                        {canReturn && (
                            <ActionCard
                                title="Return to department"
                                description="The requester can submit a replacement. Remarks are required."
                                action="return"
                                purchaseRequestId={purchaseRequest.id}
                                buttonLabel="Return"
                                remarksName="remarks"
                                remarksLabel="Remarks"
                                variant="outline"
                            />
                        )}
                        {canReject && (
                            <ActionCard
                                title="Defer"
                                description="Closes the request. The status is shown as Deferred."
                                action="reject"
                                purchaseRequestId={purchaseRequest.id}
                                buttonLabel="Defer"
                                remarksName="rejection_reason"
                                remarksLabel="Reason"
                                variant="destructive"
                            />
                        )}
                        {canCancel && (
                            <ActionCard
                                title="Cancel"
                                description="Stops this request and releases the reserved budget."
                                action="cancel"
                                purchaseRequestId={purchaseRequest.id}
                                buttonLabel="Cancel"
                                variant="outline"
                            />
                        )}
                    </div>
                )}
            </div>
        </>
    );
}

SupplyPurchaseRequestShow.layout = {
    breadcrumbs: [{ title: 'Supply requests', href: index() }],
};

function ActionCard({
    title,
    description,
    action,
    purchaseRequestId,
    buttonLabel,
    remarksName,
    remarksLabel,
    variant = 'default',
}: {
    title: string;
    description: string;
    action: SupplyAllowedAction;
    purchaseRequestId: number;
    buttonLabel: string;
    remarksName?: 'remarks' | 'rejection_reason';
    remarksLabel?: string;
    variant?: 'default' | 'outline' | 'destructive';
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    {...status.form(purchaseRequestId)}
                    disableWhileProcessing
                    className="space-y-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <input type="hidden" name="action" value={action} />
                            {remarksName && (
                                <div className="grid gap-2">
                                    <Label htmlFor={`${action}-${remarksName}`}>
                                        {remarksLabel}
                                    </Label>
                                    <Input
                                        id={`${action}-${remarksName}`}
                                        name={remarksName}
                                        required
                                    />
                                    <InputError
                                        message={errors[remarksName]}
                                    />
                                </div>
                            )}
                            <InputError message={errors.action} />
                            <Button
                                type="submit"
                                variant={variant}
                                data-test={`supply-${action}-button`}
                            >
                                {processing && <Spinner />}
                                {buttonLabel}
                            </Button>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}

function LotManager({
    purchaseRequest,
    lots,
    items,
    standalones,
}: {
    purchaseRequest: PurchaseRequest;
    lots: PurchaseRequestItem[];
    items: PurchaseRequestItem[];
    standalones: SupplyStandaloneItem[];
}) {
    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-medium">Lots</h2>
                <CreateLotDialog
                    purchaseRequestId={purchaseRequest.id}
                    standalones={standalones}
                />
            </div>
            {lots.length === 0 && (
                <p className="text-muted-foreground text-sm">
                    No lots yet. Group at least two standalone items into one
                    bid line.
                </p>
            )}
            {lots.map((lot) => {
                const children = items.filter(
                    (item) => item.parent_lot_id === lot.id,
                );

                return (
                    <div
                        key={lot.id}
                        className="flex flex-wrap items-center justify-between gap-2 rounded-xl border p-3 text-sm"
                    >
                        <div>
                            <p className="font-medium">
                                {lot.lot_name ?? lot.item_name}
                            </p>
                            <p className="text-muted-foreground text-xs">
                                {children.length} items ·{' '}
                                {lot.estimated_total_cost}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <EditLotDialog
                                purchaseRequestId={purchaseRequest.id}
                                lot={lot}
                                childrenItems={children}
                                standalones={standalones}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    router.delete(
                                        destroyLot.url({
                                            purchase_request:
                                                purchaseRequest.id,
                                            lot: lot.id,
                                        }),
                                    )
                                }
                            >
                                Remove
                            </Button>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function CreateLotDialog({
    purchaseRequestId,
    standalones,
}: {
    purchaseRequestId: number;
    standalones: SupplyStandaloneItem[];
}) {
    const [open, setOpen] = useState(false);
    const [lotName, setLotName] = useState('');
    const [selected, setSelected] = useState<number[]>([]);

    const toggle = (id: number) => {
        setSelected((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" disabled={standalones.length < 2}>
                    Create lot
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create lot</DialogTitle>
                    <DialogDescription>
                        Choose at least two standalone items. The lot is one
                        bid line; children are not priced separately.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...storeLot.form(purchaseRequestId)}
                    disableWhileProcessing
                    className="space-y-3"
                    onSuccess={() => {
                        setOpen(false);
                        setLotName('');
                        setSelected([]);
                    }}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="lot_name">Lot name</Label>
                                <Input
                                    id="lot_name"
                                    name="lot_name"
                                    value={lotName}
                                    onChange={(event) =>
                                        setLotName(event.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.lot_name} />
                            </div>
                            <div className="grid gap-2">
                                {standalones.map((item) => (
                                    <label
                                        key={item.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={selected.includes(item.id)}
                                            onCheckedChange={() =>
                                                toggle(item.id)
                                            }
                                        />
                                        {item.item_name}
                                    </label>
                                ))}
                                {selected.map((id) => (
                                    <input
                                        key={id}
                                        type="hidden"
                                        name="item_ids[]"
                                        value={id}
                                    />
                                ))}
                                <InputError message={errors.item_ids} />
                            </div>
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || selected.length < 2
                                    }
                                >
                                    {processing && <Spinner />}
                                    Save lot
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function EditLotDialog({
    purchaseRequestId,
    lot,
    childrenItems,
    standalones,
}: {
    purchaseRequestId: number;
    lot: PurchaseRequestItem;
    childrenItems: PurchaseRequestItem[];
    standalones: SupplyStandaloneItem[];
}) {
    const candidates = useMemo(() => {
        const childRows: SupplyStandaloneItem[] = childrenItems.map(
            (item) => ({
                id: item.id,
                item_name: item.item_name,
                item_code: item.item_code,
                estimated_total_cost: item.estimated_total_cost,
            }),
        );

        return [...childRows, ...standalones];
    }, [childrenItems, standalones]);

    const [open, setOpen] = useState(false);
    const [lotName, setLotName] = useState(lot.lot_name ?? lot.item_name);
    const [selected, setSelected] = useState<number[]>(
        childrenItems.map((item) => item.id),
    );

    const toggle = (id: number) => {
        setSelected((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (next) {
                    setLotName(lot.lot_name ?? lot.item_name);
                    setSelected(childrenItems.map((item) => item.id));
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Update lot</DialogTitle>
                    <DialogDescription>
                        Keep at least two items in the lot.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...updateLot.form({
                        purchase_request: purchaseRequestId,
                        lot: lot.id,
                    })}
                    disableWhileProcessing
                    className="space-y-3"
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`lot_name_${lot.id}`}>
                                    Lot name
                                </Label>
                                <Input
                                    id={`lot_name_${lot.id}`}
                                    name="lot_name"
                                    value={lotName}
                                    onChange={(event) =>
                                        setLotName(event.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.lot_name} />
                            </div>
                            <div className="grid gap-2">
                                {candidates.map((item) => (
                                    <label
                                        key={item.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={selected.includes(item.id)}
                                            onCheckedChange={() =>
                                                toggle(item.id)
                                            }
                                        />
                                        {item.item_name}
                                    </label>
                                ))}
                                {selected.map((id) => (
                                    <input
                                        key={id}
                                        type="hidden"
                                        name="item_ids[]"
                                        value={id}
                                    />
                                ))}
                                <InputError message={errors.item_ids} />
                            </div>
                            <DialogFooter>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || selected.length < 2
                                    }
                                >
                                    {processing && <Spinner />}
                                    Update lot
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
