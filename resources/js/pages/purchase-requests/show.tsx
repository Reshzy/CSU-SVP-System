import { Head, Link } from '@inertiajs/react';
import { Building2, CalendarDays, Download, GitBranch, Layers, RotateCcw, UserRound, Wallet } from 'lucide-react';
import { Fragment } from 'react';
import { PrTimeline, type TimelineActivity } from '@/components/pr-timeline';
import {
    PurchaseRequestSignatories,
    type Signatories,
} from '@/components/purchase-request-signatories';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { exportMethod, index, show } from '@/routes/purchase-requests';
import { create as replacementCreate } from '@/routes/purchase-requests/replacement';

type Item = {
    id: number;
    item_code: string | null;
    item_name: string | null;
    detailed_specifications: string | null;
    quantity_requested: number;
    estimated_unit_cost: string;
    estimated_total_cost: string;
    unit_of_measure: string | null;
    is_lot: boolean;
    lot_name: string | null;
    parent_lot_id: number | null;
};

type PurchaseRequest = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    purpose: string | null;
    justification: string | null;
    status: string;
    estimated_total: string;
    funding_source: string | null;
    date_needed: string | null;
    submitted_at: string | null;
    created_at: string;
    department?: { name: string; code: string | null } | null;
    requester?: { name: string; email: string } | null;
    items: Item[];
    activities: TimelineActivity[];
    return_remarks: string | null;
    returned_at: string | null;
    is_archived: boolean;
    /** Serialized `returnedBy` relation; it shadows the `returned_by` id column. */
    returned_by: { id: number; name: string } | number | null;
    replaces: { id: number; pr_number: string | null } | null;
    replaced_by: { id: number; pr_number: string | null } | null;
};

type Props = {
    purchaseRequest: PurchaseRequest;
    statusLabel: string;
    canCreateReplacement: boolean;
    signatories: Signatories;
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
        timeZone: 'Asia/Manila',
    });
}

const negativeStatuses = ['cancelled', 'rejected', 'returned_by_supply'];

function StatTile({
    icon: Icon,
    label,
    children,
    muted = false,
}: {
    icon: typeof Wallet;
    label: string;
    children: React.ReactNode;
    muted?: boolean;
}) {
    return (
        <div className="bg-card min-w-0 space-y-1.5 p-4">
            <dt className="text-muted-foreground flex items-center gap-1.5 text-xs tracking-wide uppercase">
                <Icon className="size-3.5" />
                {label}
            </dt>
            <dd className={`truncate text-sm font-medium ${muted ? 'text-muted-foreground' : ''}`}>
                {children}
            </dd>
        </div>
    );
}

export default function PurchaseRequestShow({
    purchaseRequest,
    statusLabel,
    canCreateReplacement,
    signatories,
}: Props) {
    const returnedByName =
        typeof purchaseRequest.returned_by === 'object' && purchaseRequest.returned_by !== null
            ? purchaseRequest.returned_by.name
            : null;
    const topLevelItems = purchaseRequest.items.filter((item) => item.parent_lot_id === null);
    const childrenOf = (lotId: number): Item[] =>
        purchaseRequest.items.filter((item) => item.parent_lot_id === lotId);
    const lineItemCount = purchaseRequest.items.filter((item) => !item.is_lot).length;
    const lotCount = purchaseRequest.items.filter((item) => item.is_lot).length;

    const rowNumbers = new Map<number, number>();

    topLevelItems
        .flatMap((item) => (item.is_lot ? childrenOf(item.id) : [item]))
        .forEach((item, position) => rowNumbers.set(item.id, position + 1));

    const statusIsNegative = negativeStatuses.includes(purchaseRequest.status);

    const renderItemRow = (item: Item, isChild: boolean) => {
        const specs =
            item.detailed_specifications?.trim() &&
            item.detailed_specifications.trim() !== item.item_name?.trim()
                ? item.detailed_specifications
                : null;

        return (
            <tr
                key={item.id}
                className={`align-top ${isChild ? 'bg-muted/20 border-t border-dashed shadow-[inset_3px_0_0_color-mix(in_oklab,var(--color-primary)_45%,transparent)]' : 'border-t'}`}
            >
                <td className="text-muted-foreground w-10 px-4 py-3 tabular-nums">
                    {rowNumbers.get(item.id)}
                </td>
                <td className={`py-3 pr-3 ${isChild ? 'pl-6' : ''}`}>
                    {item.item_code && (
                        <p className="text-muted-foreground font-mono text-xs">{item.item_code}</p>
                    )}
                    <p>{item.item_name}</p>
                    {specs && (
                        <p className="text-muted-foreground mt-1 text-xs whitespace-pre-wrap">
                            {specs}
                        </p>
                    )}
                </td>
                <td className="px-3 py-3 text-right whitespace-nowrap tabular-nums">
                    {item.quantity_requested}{' '}
                    <span className="text-muted-foreground">{item.unit_of_measure}</span>
                </td>
                <td className="px-3 py-3 text-right whitespace-nowrap tabular-nums">
                    {formatPeso(item.estimated_unit_cost)}
                </td>
                <td className="px-4 py-3 text-right font-medium whitespace-nowrap tabular-nums">
                    {formatPeso(item.estimated_total_cost)}
                </td>
            </tr>
        );
    };

    return (
        <>
            <Head title={purchaseRequest.pr_number ?? 'Purchase request'} />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 space-y-2">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="font-mono text-2xl font-semibold tracking-tight">
                                {purchaseRequest.pr_number ?? `PR #${purchaseRequest.id}`}
                            </h1>
                            <Badge variant={statusIsNegative ? 'destructive' : 'default'}>
                                <span data-testid="pr-status">{statusLabel}</span>
                            </Badge>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            {purchaseRequest.pr_title ?? 'Purchase request detail'}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <a href={exportMethod.url(purchaseRequest.id)}>
                            <Download />
                            Export Excel
                        </a>
                    </Button>
                </header>

                {purchaseRequest.status === 'returned_by_supply' && (
                    <section
                        className="flex flex-wrap items-start justify-between gap-4 rounded-lg border border-amber-500/40 bg-amber-500/5 p-4"
                        data-testid="pr-returned-banner"
                    >
                        <div className="min-w-0 space-y-1 text-sm">
                            <p className="flex items-center gap-1.5 font-medium">
                                <RotateCcw className="size-4" />
                                Returned by {returnedByName ?? 'Supply Office'}
                                {purchaseRequest.returned_at &&
                                    ` on ${formatDate(purchaseRequest.returned_at)}`}
                            </p>
                            <p className="text-muted-foreground whitespace-pre-line">
                                {purchaseRequest.return_remarks ?? 'No remarks given.'}
                            </p>
                            {purchaseRequest.replaced_by && (
                                <p>
                                    Replaced by{' '}
                                    <Link
                                        href={show.url(purchaseRequest.replaced_by.id)}
                                        className="font-mono underline"
                                    >
                                        {purchaseRequest.replaced_by.pr_number}
                                    </Link>
                                </p>
                            )}
                        </div>
                        {canCreateReplacement && (
                            <Button asChild>
                                <Link href={replacementCreate.url(purchaseRequest.id)}>
                                    <GitBranch />
                                    Create replacement
                                </Link>
                            </Button>
                        )}
                    </section>
                )}

                {purchaseRequest.replaces && (
                    <p className="text-muted-foreground flex items-center gap-1.5 text-sm">
                        <GitBranch className="size-4" />
                        Replaces{' '}
                        <Link
                            href={show.url(purchaseRequest.replaces.id)}
                            className="font-mono underline"
                        >
                            {purchaseRequest.replaces.pr_number}
                        </Link>
                    </p>
                )}

                {/* Summary strip */}
                <dl className="bg-border grid grid-cols-2 gap-px overflow-hidden rounded-lg border lg:grid-cols-5">
                    <div className="bg-card col-span-2 space-y-1.5 p-4 lg:col-span-1">
                        <dt className="text-muted-foreground text-xs tracking-wide uppercase">
                            Estimated total
                        </dt>
                        <dd className="text-xl font-semibold tabular-nums">
                            {formatPeso(purchaseRequest.estimated_total)}
                        </dd>
                    </div>
                    <StatTile icon={Building2} label="Department">
                        <span title={purchaseRequest.department?.name}>
                            {purchaseRequest.department?.code ??
                                purchaseRequest.department?.name ??
                                '—'}
                        </span>
                    </StatTile>
                    <StatTile icon={UserRound} label="Requester">
                        {purchaseRequest.requester?.name ?? '—'}
                    </StatTile>
                    <StatTile icon={CalendarDays} label="Submitted">
                        {formatDate(purchaseRequest.submitted_at ?? purchaseRequest.created_at)}
                    </StatTile>
                    <StatTile icon={Wallet} label="Funding" muted={!purchaseRequest.funding_source}>
                        {purchaseRequest.funding_source ?? 'Not yet assigned'}
                    </StatTile>
                </dl>

                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        {/* Purpose & justification */}
                        <section className="bg-card grid gap-6 rounded-lg border p-5 text-sm sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <h2 className="text-muted-foreground text-xs tracking-wide uppercase">
                                    Purpose
                                </h2>
                                <p className="whitespace-pre-wrap">
                                    {purchaseRequest.purpose || '—'}
                                </p>
                            </div>
                            <div className="space-y-1.5">
                                <h2 className="text-muted-foreground text-xs tracking-wide uppercase">
                                    Justification
                                </h2>
                                <p className="whitespace-pre-wrap">
                                    {purchaseRequest.justification || '—'}
                                </p>
                            </div>
                        </section>

                        {/* Items */}
                        <section className="bg-card overflow-hidden rounded-lg border">
                            <div className="flex flex-wrap items-baseline justify-between gap-2 border-b px-4 py-3">
                                <h2 className="font-medium">Items</h2>
                                <p className="text-muted-foreground text-xs">
                                    {lineItemCount} {lineItemCount === 1 ? 'item' : 'items'}
                                    {lotCount > 0 &&
                                        ` · ${lotCount} ${lotCount === 1 ? 'lot' : 'lots'}`}
                                    {purchaseRequest.date_needed &&
                                        ` · needed by ${formatDate(purchaseRequest.date_needed)}`}
                                </p>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[640px] text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground text-left text-xs tracking-wide uppercase">
                                        <tr>
                                            <th className="w-10 px-4 py-2 font-medium">#</th>
                                            <th className="py-2 pr-3 font-medium">Item</th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Qty
                                            </th>
                                            <th className="px-3 py-2 text-right font-medium">
                                                Unit cost
                                            </th>
                                            <th className="px-4 py-2 text-right font-medium">
                                                Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {topLevelItems.map((item) => {
                                            if (!item.is_lot) {
                                                return renderItemRow(item, false);
                                            }

                                            const children = childrenOf(item.id);

                                            return (
                                                <Fragment key={item.id}>
                                                    <tr className="bg-muted/60 border-t shadow-[inset_3px_0_0_var(--color-primary)]">
                                                        <td colSpan={4} className="px-4 py-2.5">
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <Layers className="text-primary size-4" />
                                                                <span className="font-medium">
                                                                    {item.lot_name ??
                                                                        'Untitled lot'}
                                                                </span>
                                                                <Badge variant="outline">Lot</Badge>
                                                                <span className="text-muted-foreground text-xs">
                                                                    {children.length}{' '}
                                                                    {children.length === 1
                                                                        ? 'item'
                                                                        : 'items'}
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td className="px-4 py-2.5 text-right font-medium whitespace-nowrap tabular-nums">
                                                            {formatPeso(item.estimated_total_cost)}
                                                        </td>
                                                    </tr>
                                                    {children.map((child) =>
                                                        renderItemRow(child, true),
                                                    )}
                                                </Fragment>
                                            );
                                        })}
                                        {topLevelItems.length === 0 && (
                                            <tr className="border-t">
                                                <td
                                                    colSpan={5}
                                                    className="text-muted-foreground px-4 py-8 text-center"
                                                >
                                                    No items on this request.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                    <tfoot className="bg-muted/50">
                                        <tr className="border-t font-medium">
                                            <td colSpan={4} className="px-4 py-3 text-right">
                                                Estimated total
                                            </td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap tabular-nums">
                                                {formatPeso(purchaseRequest.estimated_total)}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </section>

                        <PurchaseRequestSignatories signatories={signatories} />
                    </div>

                    {/* Timeline */}
                    <aside className="space-y-3 xl:sticky xl:top-4">
                        <div className="flex items-baseline justify-between">
                            <h2 className="font-medium">Activity</h2>
                            <span className="text-muted-foreground text-xs">
                                {purchaseRequest.activities.length} events
                            </span>
                        </div>
                        <PrTimeline activities={purchaseRequest.activities} />
                    </aside>
                </div>
            </div>
        </>
    );
}

PurchaseRequestShow.layout = {
    breadcrumbs: [{ title: 'Purchase requests', href: index() }],
};
