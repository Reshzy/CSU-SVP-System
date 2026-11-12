import { Head, router, useForm } from '@inertiajs/react';
import { Download, Layers, Pencil, Search, Unlink, X } from 'lucide-react';
import { Fragment, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { PrTimeline, type TimelineActivity } from '@/components/pr-timeline';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { exportMethod, index, status } from '@/routes/supply/purchase-requests';
import { update as updateDetails } from '@/routes/supply/purchase-requests/details';
import {
    destroy as destroyLot,
    store as storeLot,
    update as updateLot,
} from '@/routes/supply/purchase-requests/lots';

type Item = {
    id: number;
    item_name: string | null;
    item_code: string | null;
    detailed_specifications: string | null;
    unit_of_measure: string | null;
    is_lot: boolean;
    lot_name: string | null;
    parent_lot_id: number | null;
    quantity_requested: number;
    estimated_unit_cost: string;
    estimated_total_cost: string;
};

type PurchaseRequest = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    purpose: string | null;
    justification: string | null;
    status: string;
    date_needed: string | null;
    created_at: string;
    department?: { name: string; code: string | null } | null;
    requester?: { name: string; email: string } | null;
    items: Item[];
    activities: TimelineActivity[];
};

type StatusAction = 'start_review' | 'activate' | 'return' | 'reject' | 'cancel';

type Props = {
    purchaseRequest: PurchaseRequest;
    statusLabel: string;
    allowedActions: StatusAction[];
    canManageLots: boolean;
    canEditDetails: boolean;
};

const actionMeta: Record<
    StatusAction,
    {
        label: string;
        hint: string;
        destructive: boolean;
        requiresRemarks: boolean;
    }
> = {
    start_review: {
        label: 'Start review',
        hint: 'Mark this request as under Supply Office review.',
        destructive: false,
        requiresRemarks: false,
    },
    activate: {
        label: 'Forward to Budget',
        hint: 'Send to the Budget Office for earmarking.',
        destructive: false,
        requiresRemarks: false,
    },
    return: {
        label: 'Return to requester',
        hint: 'Send back for corrections. The requester sees your remarks.',
        destructive: true,
        requiresRemarks: true,
    },
    reject: {
        label: 'Defer',
        hint: 'Defer this request. Your remarks become the reason.',
        destructive: true,
        requiresRemarks: true,
    },
    cancel: {
        label: 'Cancel request',
        hint: 'Cancel this request permanently.',
        destructive: true,
        requiresRemarks: true,
    },
};

const peso = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
});

function formatPeso(value: string | number): string {
    return peso.format(Number(value) || 0);
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function itemLabel(item: Item): string {
    return item.is_lot ? (item.lot_name ?? 'Untitled lot') : `${item.item_name ?? ''}`.trim();
}

function matchesSearch(item: Item, term: string): boolean {
    if (term === '') {
        return true;
    }

    return [item.item_code, item.item_name, item.lot_name, item.detailed_specifications]
        .filter(Boolean)
        .some((value) => value!.toLowerCase().includes(term));
}

function SummaryField({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="min-w-0 space-y-1">
            <dt className="text-muted-foreground text-xs tracking-wide uppercase">{label}</dt>
            <dd className="truncate text-sm font-medium">{children}</dd>
        </div>
    );
}

export default function SupplyPurchaseRequestShow({
    purchaseRequest,
    statusLabel,
    allowedActions,
    canManageLots,
    canEditDetails,
}: Props) {
    const lineItems = purchaseRequest.items.filter((item) => !item.is_lot);
    const lots = purchaseRequest.items.filter((item) => item.is_lot);
    const standalones = lineItems.filter((item) => item.parent_lot_id === null);
    const grandTotal = lineItems.reduce((sum, item) => sum + Number(item.estimated_total_cost), 0);

    const rowNumbers = new Map<number, number>();

    [
        ...lots.flatMap((lot) => lineItems.filter((item) => item.parent_lot_id === lot.id)),
        ...standalones,
    ].forEach((item, position) => rowNumbers.set(item.id, position + 1));

    const [search, setSearch] = useState('');
    const searchTerm = search.trim().toLowerCase();

    /* ---------------- Details editing ---------------- */

    const [editingNarrative, setEditingNarrative] = useState(false);
    const [editingSpecs, setEditingSpecs] = useState(false);

    const initialDetails = () => ({
        purpose: purchaseRequest.purpose ?? '',
        justification: purchaseRequest.justification ?? '',
        items: lineItems.map((item) => ({
            id: item.id,
            detailed_specifications: item.detailed_specifications ?? '',
        })),
    });

    const detailsForm = useForm(initialDetails());

    const specFor = (itemId: number): string =>
        detailsForm.data.items.find((row) => row.id === itemId)?.detailed_specifications ?? '';

    const setSpecFor = (itemId: number, value: string) => {
        detailsForm.setData(
            'items',
            detailsForm.data.items.map((row) =>
                row.id === itemId ? { ...row, detailed_specifications: value } : row,
            ),
        );
    };

    const cancelDetailsEdit = () => {
        detailsForm.setData(initialDetails());
        detailsForm.clearErrors();
        setEditingNarrative(false);
        setEditingSpecs(false);
    };

    const saveDetails = () => {
        detailsForm.patch(updateDetails.url(purchaseRequest.id), {
            preserveScroll: true,
            onSuccess: () => {
                setEditingNarrative(false);
                setEditingSpecs(false);
            },
        });
    };

    /* ---------------- Lots ---------------- */

    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [editingLotId, setEditingLotId] = useState<number | null>(null);
    const [lotPendingRemoval, setLotPendingRemoval] = useState<Item | null>(null);
    const lotForm = useForm<{ lot_name: string; item_ids: number[] }>({ lot_name: '', item_ids: [] });

    const isSelectable = (item: Item): boolean =>
        canManageLots &&
        !editingSpecs &&
        (item.parent_lot_id === null || item.parent_lot_id === editingLotId);

    const toggleSelected = (itemId: number, checked: boolean) => {
        setSelectedIds((current) =>
            checked ? [...current, itemId] : current.filter((id) => id !== itemId),
        );
    };

    const clearSelection = () => {
        setSelectedIds([]);
        setEditingLotId(null);
        lotForm.reset();
        lotForm.clearErrors();
    };

    const startEditingLot = (lot: Item) => {
        setEditingLotId(lot.id);
        lotForm.setData('lot_name', lot.lot_name ?? '');
        setSelectedIds(
            lineItems.filter((item) => item.parent_lot_id === lot.id).map((item) => item.id),
        );
    };

    const submitLot = () => {
        const payload = {
            lot_name: lotForm.data.lot_name,
            item_ids: selectedIds,
        };
        const options = { preserveScroll: true, onSuccess: clearSelection };

        lotForm.transform(() => payload);

        if (editingLotId !== null) {
            lotForm.put(
                updateLot.url({
                    purchaseRequest: purchaseRequest.id,
                    lot: editingLotId,
                }),
                options,
            );

            return;
        }

        lotForm.post(storeLot.url(purchaseRequest.id), options);
    };

    const confirmRemoveLot = () => {
        if (!lotPendingRemoval) {
            return;
        }

        router.delete(
            destroyLot.url({
                purchaseRequest: purchaseRequest.id,
                lot: lotPendingRemoval.id,
            }),
            {
                preserveScroll: true,
                onFinish: () => setLotPendingRemoval(null),
            },
        );
    };

    /* ---------------- Status ---------------- */

    const statusForm = useForm<{
        action: StatusAction | '';
        remarks: string;
        rejection_reason: string;
    }>({
        action: allowedActions[0] ?? '',
        remarks: '',
        rejection_reason: '',
    });
    const [confirmingStatus, setConfirmingStatus] = useState(false);
    const selectedAction = statusForm.data.action ? actionMeta[statusForm.data.action] : null;
    const remarksMissing =
        selectedAction?.requiresRemarks === true && statusForm.data.remarks.trim() === '';

    const submitStatus = () => {
        statusForm.transform((data) => ({
            ...data,
            rejection_reason: data.action === 'reject' ? data.remarks : '',
        }));
        statusForm.post(status.url(purchaseRequest.id), {
            preserveScroll: true,
            onFinish: () => setConfirmingStatus(false),
        });
    };

    /* ---------------- Rendering helpers ---------------- */

    const renderItemRow = (item: Item, isChild: boolean) => {
        const selectable = isSelectable(item);
        const specs =
            item.detailed_specifications?.trim() !== item.item_name?.trim()
                ? item.detailed_specifications
                : null;

        return (
            <tr
                key={item.id}
                className={`border-t align-top ${selectedIds.includes(item.id) ? 'bg-primary/[0.04]' : ''}`}
            >
                {canManageLots && (
                    <td className="w-10 px-3 py-2.5">
                        <Checkbox
                            aria-label={`Select ${itemLabel(item)}`}
                            disabled={!selectable}
                            checked={selectedIds.includes(item.id)}
                            onCheckedChange={(checked) => toggleSelected(item.id, checked === true)}
                        />
                    </td>
                )}
                <td className="text-muted-foreground w-10 px-3 py-2.5 tabular-nums">
                    {rowNumbers.get(item.id)}
                </td>
                <td className={`px-3 py-2.5 ${isChild ? 'pl-8' : ''}`}>
                    <p className="text-muted-foreground font-mono text-xs">{item.item_code}</p>
                    <p>{itemLabel(item)}</p>
                    {editingSpecs ? (
                        <Textarea
                            aria-label={`Specifications for ${itemLabel(item)}`}
                            className="mt-2 min-h-16 text-sm"
                            placeholder="Add detailed specifications"
                            value={specFor(item.id)}
                            onChange={(event) => setSpecFor(item.id, event.target.value)}
                        />
                    ) : (
                        specs && (
                            <p className="text-muted-foreground mt-1 text-xs whitespace-pre-wrap">
                                {specs}
                            </p>
                        )
                    )}
                </td>
                <td className="px-3 py-2.5 text-right whitespace-nowrap tabular-nums">
                    {item.quantity_requested}{' '}
                    <span className="text-muted-foreground">{item.unit_of_measure}</span>
                </td>
                <td className="px-3 py-2.5 text-right whitespace-nowrap tabular-nums">
                    {formatPeso(item.estimated_unit_cost)}
                </td>
                <td className="px-3 py-2.5 text-right font-medium whitespace-nowrap tabular-nums">
                    {formatPeso(item.estimated_total_cost)}
                </td>
            </tr>
        );
    };

    const columnCount = canManageLots ? 6 : 5;
    const visibleStandalones = standalones.filter((item) => matchesSearch(item, searchTerm));
    const visibleLots = lots
        .map((lot) => {
            const children = lineItems.filter((item) => item.parent_lot_id === lot.id);
            const visibleChildren = matchesSearch(lot, searchTerm)
                ? children
                : children.filter((item) => matchesSearch(item, searchTerm));

            return { lot, children, visibleChildren };
        })
        .filter(({ visibleChildren }) => visibleChildren.length > 0);

    const showLotBar = canManageLots && (selectedIds.length > 0 || editingLotId !== null);
    const editingLot = lots.find((lot) => lot.id === editingLotId);

    return (
        <>
            <Head title={purchaseRequest.pr_number ?? 'Supply PR'} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="space-y-2">
                        <Badge
                            variant={
                                ['cancelled', 'rejected'].includes(purchaseRequest.status)
                                    ? 'destructive'
                                    : 'default'
                            }
                        >
                            {statusLabel}
                        </Badge>
                        <Heading
                            title={purchaseRequest.pr_number ?? `PR #${purchaseRequest.id}`}
                            description={purchaseRequest.pr_title ?? undefined}
                        />
                    </div>
                    <Button variant="outline" asChild>
                        <a href={exportMethod.url(purchaseRequest.id)}>
                            <Download />
                            Export Excel
                        </a>
                    </Button>
                </div>

                {/* Summary */}
                <dl className="grid grid-cols-2 gap-4 rounded-lg border p-4 sm:grid-cols-3 lg:grid-cols-6">
                    <SummaryField label="Requester">
                        {purchaseRequest.requester?.name ?? '—'}
                    </SummaryField>
                    <SummaryField label="Department">
                        {purchaseRequest.department?.code ??
                            purchaseRequest.department?.name ??
                            '—'}
                    </SummaryField>
                    <SummaryField label="Submitted">
                        {formatDate(purchaseRequest.created_at)}
                    </SummaryField>
                    <SummaryField label="Date needed">
                        {formatDate(purchaseRequest.date_needed)}
                    </SummaryField>
                    <SummaryField label="Line items">{lineItems.length}</SummaryField>
                    <SummaryField label="Estimated total">
                        <span className="tabular-nums">{formatPeso(grandTotal)}</span>
                    </SummaryField>
                </dl>

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        {/* Purpose & justification */}
                        <section className="space-y-4 rounded-lg border p-4">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="font-medium">Purpose &amp; justification</h2>
                                {canEditDetails && !editingNarrative && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => setEditingNarrative(true)}
                                    >
                                        <Pencil />
                                        Edit
                                    </Button>
                                )}
                            </div>

                            {editingNarrative ? (
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="purpose">Purpose</Label>
                                        <Textarea
                                            id="purpose"
                                            value={detailsForm.data.purpose}
                                            onChange={(event) =>
                                                detailsForm.setData('purpose', event.target.value)
                                            }
                                        />
                                        <InputError message={detailsForm.errors.purpose} />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="justification">Justification</Label>
                                        <Textarea
                                            id="justification"
                                            value={detailsForm.data.justification}
                                            onChange={(event) =>
                                                detailsForm.setData(
                                                    'justification',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError message={detailsForm.errors.justification} />
                                    </div>
                                    <div className="flex gap-2">
                                        <Button
                                            size="sm"
                                            disabled={detailsForm.processing}
                                            onClick={saveDetails}
                                        >
                                            Save
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={cancelDetailsEdit}
                                        >
                                            Cancel
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <div className="grid gap-4 text-sm sm:grid-cols-2">
                                    <div className="space-y-1">
                                        <p className="text-muted-foreground text-xs tracking-wide uppercase">
                                            Purpose
                                        </p>
                                        <p className="whitespace-pre-wrap">
                                            {purchaseRequest.purpose || '—'}
                                        </p>
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-muted-foreground text-xs tracking-wide uppercase">
                                            Justification
                                        </p>
                                        <p className="whitespace-pre-wrap">
                                            {purchaseRequest.justification || '—'}
                                        </p>
                                    </div>
                                </div>
                            )}
                        </section>

                        {/* Items */}
                        <section className="rounded-lg border">
                            <div className="flex flex-wrap items-center justify-between gap-2 border-b p-3">
                                <div className="relative w-full sm:w-72">
                                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                                    <Input
                                        aria-label="Search items"
                                        className="pl-9"
                                        placeholder="Search items or specs"
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                    />
                                </div>
                                <div className="flex items-center gap-2">
                                    {canManageLots && !editingSpecs && selectedIds.length === 0 && (
                                        <p className="text-muted-foreground hidden text-xs md:block">
                                            Select 2+ items to group them into a lot
                                        </p>
                                    )}
                                    {canEditDetails &&
                                        (editingSpecs ? (
                                            <>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={cancelDetailsEdit}
                                                >
                                                    Cancel
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    disabled={detailsForm.processing}
                                                    onClick={saveDetails}
                                                >
                                                    Save specifications
                                                </Button>
                                            </>
                                        ) : (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => {
                                                    clearSelection();
                                                    setEditingSpecs(true);
                                                }}
                                            >
                                                <Pencil />
                                                Edit specifications
                                            </Button>
                                        ))}
                                </div>
                            </div>

                            <div className="max-h-[70vh] overflow-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted sticky top-0 z-10 text-left">
                                        <tr>
                                            {canManageLots && <th className="w-10 px-3 py-2" />}
                                            <th className="w-10 px-3 py-2">#</th>
                                            <th className="px-3 py-2">Item</th>
                                            <th className="px-3 py-2 text-right">Qty</th>
                                            <th className="px-3 py-2 text-right">Unit cost</th>
                                            <th className="px-3 py-2 text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {visibleLots.map(({ lot, children, visibleChildren }) => (
                                            <Fragment key={lot.id}>
                                                <tr className="bg-muted/40 border-t">
                                                    <td
                                                        colSpan={columnCount - 1}
                                                        className="px-3 py-2"
                                                    >
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <Layers className="text-muted-foreground size-4" />
                                                            <span className="font-medium">
                                                                {lot.lot_name}
                                                            </span>
                                                            <span className="text-muted-foreground text-xs">
                                                                {children.length} items
                                                            </span>
                                                            {canManageLots && !editingSpecs && (
                                                                <span className="ml-auto flex gap-1">
                                                                    <Button
                                                                        size="sm"
                                                                        variant="ghost"
                                                                        onClick={() =>
                                                                            startEditingLot(lot)
                                                                        }
                                                                    >
                                                                        <Pencil />
                                                                        Edit
                                                                    </Button>
                                                                    <Button
                                                                        size="sm"
                                                                        variant="ghost"
                                                                        onClick={() =>
                                                                            setLotPendingRemoval(
                                                                                lot,
                                                                            )
                                                                        }
                                                                    >
                                                                        <Unlink />
                                                                        Ungroup
                                                                    </Button>
                                                                </span>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-3 py-2 text-right font-medium whitespace-nowrap tabular-nums">
                                                        {formatPeso(lot.estimated_total_cost)}
                                                    </td>
                                                </tr>
                                                {visibleChildren.map((item) =>
                                                    renderItemRow(item, true),
                                                )}
                                            </Fragment>
                                        ))}
                                        {visibleStandalones.map((item) =>
                                            renderItemRow(item, false),
                                        )}
                                        {visibleLots.length === 0 &&
                                            visibleStandalones.length === 0 && (
                                                <tr className="border-t">
                                                    <td
                                                        colSpan={columnCount}
                                                        className="text-muted-foreground px-3 py-8 text-center"
                                                    >
                                                        No items match “{search}
                                                        ”.
                                                    </td>
                                                </tr>
                                            )}
                                    </tbody>
                                    <tfoot className="bg-muted sticky bottom-0">
                                        <tr className="border-t font-medium">
                                            <td
                                                colSpan={columnCount - 1}
                                                className="px-3 py-2.5 text-right"
                                            >
                                                Estimated total
                                            </td>
                                            <td className="px-3 py-2.5 text-right whitespace-nowrap tabular-nums">
                                                {formatPeso(grandTotal)}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            {showLotBar && (
                                <div className="bg-card sticky bottom-0 z-20 flex flex-wrap items-end gap-3 rounded-b-lg border-t p-3 shadow-[0_-8px_24px_-12px_rgb(0_0_0/0.35)]">
                                    <div className="min-w-48 flex-1 space-y-1">
                                        <Label htmlFor="lot_name">
                                            {editingLot
                                                ? `Edit lot · ${selectedIds.length} items`
                                                : `Group ${selectedIds.length} selected into a lot`}
                                        </Label>
                                        <Input
                                            id="lot_name"
                                            placeholder="Lot name, e.g. Office supplies"
                                            value={lotForm.data.lot_name}
                                            onChange={(event) =>
                                                lotForm.setData('lot_name', event.target.value)
                                            }
                                        />
                                        <InputError
                                            message={
                                                lotForm.errors.lot_name ?? lotForm.errors.item_ids
                                            }
                                        />
                                    </div>
                                    <div className="flex gap-2">
                                        <Button variant="ghost" onClick={clearSelection}>
                                            <X />
                                            Clear
                                        </Button>
                                        <Button
                                            disabled={
                                                lotForm.processing ||
                                                selectedIds.length < 2 ||
                                                lotForm.data.lot_name.trim() === ''
                                            }
                                            onClick={submitLot}
                                        >
                                            {editingLot ? 'Save lot' : 'Create lot'}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </section>
                    </div>

                    {/* Sidebar */}
                    <aside className="flex flex-col gap-6 xl:sticky xl:top-4">
                        <section className="space-y-4 rounded-lg border p-4">
                            <h2 className="font-medium">Supply action</h2>
                            {allowedActions.length === 0 ? (
                                <p className="text-muted-foreground text-sm">
                                    No supply actions are available while this request is{' '}
                                    {statusLabel.toLowerCase()}.
                                </p>
                            ) : (
                                <form
                                    className="space-y-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();

                                        if (selectedAction?.destructive) {
                                            setConfirmingStatus(true);

                                            return;
                                        }

                                        submitStatus();
                                    }}
                                >
                                    <div
                                        role="radiogroup"
                                        aria-label="Status action"
                                        className="space-y-2"
                                    >
                                        {allowedActions.map((action) => {
                                            const meta = actionMeta[action];
                                            const isSelected = statusForm.data.action === action;

                                            return (
                                                <button
                                                    key={action}
                                                    type="button"
                                                    role="radio"
                                                    aria-checked={isSelected}
                                                    onClick={() =>
                                                        statusForm.setData('action', action)
                                                    }
                                                    className={`w-full rounded-md border px-3 py-2 text-left transition-colors ${
                                                        isSelected
                                                            ? meta.destructive
                                                                ? 'border-destructive bg-destructive/[0.06]'
                                                                : 'border-primary bg-primary/[0.06]'
                                                            : 'hover:bg-muted/60'
                                                    }`}
                                                >
                                                    <p className="text-sm font-medium">
                                                        {meta.label}
                                                    </p>
                                                    <p className="text-muted-foreground text-xs">
                                                        {meta.hint}
                                                    </p>
                                                </button>
                                            );
                                        })}
                                    </div>
                                    <InputError message={statusForm.errors.action} />

                                    <div className="space-y-2">
                                        <Label htmlFor="remarks">
                                            Remarks
                                            {selectedAction?.requiresRemarks ? '' : ' (optional)'}
                                        </Label>
                                        <Textarea
                                            id="remarks"
                                            value={statusForm.data.remarks}
                                            onChange={(event) =>
                                                statusForm.setData('remarks', event.target.value)
                                            }
                                        />
                                        <InputError message={statusForm.errors.remarks} />
                                    </div>

                                    <Button
                                        type="submit"
                                        className="w-full"
                                        variant={
                                            selectedAction?.destructive ? 'destructive' : 'default'
                                        }
                                        disabled={statusForm.processing || remarksMissing}
                                    >
                                        {selectedAction?.label ?? 'Apply'}
                                    </Button>
                                </form>
                            )}
                        </section>

                        <section className="space-y-3">
                            <h2 className="text-base font-medium">Activity timeline</h2>
                            <PrTimeline activities={purchaseRequest.activities ?? []} />
                        </section>
                    </aside>
                </div>
            </div>

            <Dialog open={confirmingStatus} onOpenChange={setConfirmingStatus}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{selectedAction?.label}?</DialogTitle>
                        <DialogDescription>
                            {selectedAction?.hint} The requester will be notified.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setConfirmingStatus(false)}>
                            Go back
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={statusForm.processing}
                            onClick={submitStatus}
                        >
                            {selectedAction?.label}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={lotPendingRemoval !== null}
                onOpenChange={(open) => !open && setLotPendingRemoval(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Ungroup “{lotPendingRemoval?.lot_name}”?</DialogTitle>
                        <DialogDescription>
                            The lot is removed and its items become standalone again. No items are
                            deleted.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="ghost" onClick={() => setLotPendingRemoval(null)}>
                            Keep lot
                        </Button>
                        <Button variant="destructive" onClick={confirmRemoveLot}>
                            Ungroup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

SupplyPurchaseRequestShow.layout = {
    breadcrumbs: [{ title: 'Supply queue', href: index() }],
};
