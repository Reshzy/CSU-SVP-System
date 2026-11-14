import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    cartItemsPayload,
    PurchaseRequestItemPicker,
    type AvailablePpmpItem,
    type PurchaseRequestCartLine,
    type PurchaseRequestCartLot,
} from '@/components/purchase-request-item-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { usePrecognitiveForm } from '@/hooks/use-precognitive-form';
import {
    purchaseRequestCreateSchema,
    zodIssuesToFormErrors,
} from '@/lib/schemas';
import { index as ppmpIndex } from '@/routes/ppmp';
import { create, index, show, store } from '@/routes/purchase-requests';
import { store as replacementStore } from '@/routes/purchase-requests/replacement';

type Props = {
    fiscalYear: number;
    quarter: number;
    hasValidatedPpmp: boolean;
    ppmpId: number | null;
    canUpdatePpmp: boolean;
    availableItems: AvailablePpmpItem[];
    replacing?: ReplacingPurchaseRequest | null;
};

type ReplacingPurchaseRequest = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    purpose: string | null;
    return_remarks: string | null;
    returned_at: string | null;
    returned_by_name: string | null;
    lots: PurchaseRequestCartLot[];
    lines: PurchaseRequestCartLine[];
};

export default function PurchaseRequestCreate({
    fiscalYear,
    quarter,
    hasValidatedPpmp,
    ppmpId,
    canUpdatePpmp,
    availableItems,
    replacing = null,
}: Props) {
    const submitUrl = replacing
        ? replacementStore.url(replacing.id)
        : store.url();
    const form = usePrecognitiveForm('post', submitUrl, {
        pr_title: replacing?.pr_title ?? '',
        purpose: replacing?.purpose ?? '',
    });
    const [lines, setLines] = useState<PurchaseRequestCartLine[]>(
        replacing?.lines ?? [],
    );
    const [lots, setLots] = useState<PurchaseRequestCartLot[]>(
        replacing?.lots ?? [],
    );

    const estimatedTotal = useMemo(() => {
        return lines.reduce((sum, line) => {
            const item = availableItems.find(
                (candidate) => candidate.id === line.ppmp_item_id,
            );

            if (!item) {
                return sum;
            }

            return sum + line.quantity_requested * Number(line.estimated_unit_cost);
        }, 0);
    }, [availableItems, lines]);

    return (
        <>
            <Head
                title={
                    replacing
                        ? 'Replacement purchase request'
                        : 'New purchase request'
                }
            />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={
                        replacing
                            ? `Replace ${replacing.pr_number ?? 'returned request'}`
                            : 'New purchase request'
                    }
                    description={
                        replacing
                            ? `FY ${fiscalYear} · Q${quarter}. The replacement goes to Supply Office review and the returned request is archived.`
                            : `FY ${fiscalYear} · Q${quarter}. Submitted requests go straight to Supply Office review.`
                    }
                />

                {replacing && (
                    <div
                        className="space-y-1 rounded-lg border border-amber-500/40 bg-amber-500/5 p-4 text-sm"
                        data-testid="replacement-return-remarks"
                    >
                        <p className="font-medium">
                            Returned by{' '}
                            {replacing.returned_by_name ?? 'Supply Office'}
                            {replacing.returned_at &&
                                ` on ${new Date(replacing.returned_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' })}`}
                        </p>
                        <p className="whitespace-pre-line text-muted-foreground">
                            {replacing.return_remarks ?? 'No remarks given.'}
                        </p>
                        <p>
                            <Link
                                href={show.url(replacing.id)}
                                className="underline"
                            >
                                View returned request
                            </Link>
                        </p>
                    </div>
                )}

                {!hasValidatedPpmp ? (
                    <p className="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm">
                        Your department needs a validated PPMP for {fiscalYear}{' '}
                        before you can create a purchase request.{' '}
                        <Link href={ppmpIndex.url()} className="underline">
                            Open PPMP
                        </Link>
                    </p>
                ) : (
                    <>
                        <form
                            id="pr-create"
                            onSubmit={(event) => {
                                event.preventDefault();

                                const payload = {
                                    pr_title: form.data.pr_title,
                                    purpose: form.data.purpose,
                                    items: cartItemsPayload(lots, lines),
                                };
                                const parsed =
                                    purchaseRequestCreateSchema.safeParse(
                                        payload,
                                    );

                                if (!parsed.success) {
                                    form.setError(
                                        zodIssuesToFormErrors(parsed.error),
                                    );

                                    return;
                                }

                                form.transform(() => payload);
                                form.post(submitUrl);
                            }}
                            className="grid gap-4 md:grid-cols-2"
                        >
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="pr_title">Title</Label>
                                <Input
                                    id="pr_title"
                                    value={form.data.pr_title}
                                    onChange={(event) =>
                                        form.setData(
                                            'pr_title',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.validate('pr_title')}
                                />
                                <InputError message={form.errors.pr_title} />
                            </div>

                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="purpose">Purpose</Label>
                                <Textarea
                                    id="purpose"
                                    value={form.data.purpose}
                                    onChange={(event) =>
                                        form.setData(
                                            'purpose',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.validate('purpose')}
                                />
                                <InputError message={form.errors.purpose} />
                            </div>
                        </form>

                        <PurchaseRequestItemPicker
                            availableItems={availableItems}
                            lines={lines}
                            lots={lots}
                            onLinesChange={setLines}
                            onLotsChange={setLots}
                            errors={form.errors}
                            ppmpId={ppmpId}
                            canUpdatePpmp={canUpdatePpmp}
                        />

                        <div className="flex items-center justify-between gap-4">
                            <p className="text-sm text-muted-foreground">
                                Estimated total:{' '}
                                <span className="font-medium text-foreground">
                                    {estimatedTotal.toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                    })}
                                </span>
                            </p>
                            <Button
                                type="submit"
                                form="pr-create"
                                disabled={
                                    form.processing || lines.length === 0
                                }
                                data-testid="submit-purchase-request"
                            >
                                {replacing
                                    ? 'Submit replacement'
                                    : 'Submit to Supply'}
                            </Button>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

PurchaseRequestCreate.layout = {
    breadcrumbs: [
        { title: 'Purchase requests', href: index() },
        { title: 'Create', href: create() },
    ],
};
