import { Head, Link } from '@inertiajs/react';
import { CornerDownLeft, Send } from 'lucide-react';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    cartItemsPayload,
    PurchaseRequestItemPicker,
    StepHeading,
    type AvailablePpmpItem,
    type PurchaseRequestCartLine,
    type PurchaseRequestCartLot,
} from '@/components/purchase-request-item-picker';
import {
    PurchaseRequestSignatories,
    type Signatories,
} from '@/components/purchase-request-signatories';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
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
    signatories: Signatories;
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
    signatories,
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

    const estimatedTotal = useMemo(
        () =>
            lines.reduce(
                (sum, line) =>
                    sum +
                    line.quantity_requested * Number(line.estimated_unit_cost),
                0,
            ),
        [lines],
    );
    const lotCount = lots.filter((lot) =>
        lines.some((line) => line.lot_id === lot.id),
    ).length;
    const itemsError = (form.errors as Record<string, string | undefined>)
        .items;

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
                <div className="space-y-2">
                    {replacing ? (
                        <Badge variant="outline">
                            Replacing{' '}
                            {replacing.pr_number ?? 'returned request'}
                        </Badge>
                    ) : null}
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
                </div>

                {replacing && (
                    <Alert data-testid="replacement-return-remarks">
                        <CornerDownLeft />
                        <AlertTitle>
                            Returned by{' '}
                            {replacing.returned_by_name ?? 'Supply Office'}
                            {replacing.returned_at &&
                                ` on ${new Date(replacing.returned_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'Asia/Manila' })}`}
                        </AlertTitle>
                        <AlertDescription className="space-y-2">
                            <p className="whitespace-pre-line">
                                {replacing.return_remarks ??
                                    'No remarks given.'}
                            </p>
                            <p>
                                <Link
                                    href={show.url(replacing.id)}
                                    className="underline"
                                >
                                    View returned request
                                </Link>
                            </p>
                        </AlertDescription>
                    </Alert>
                )}

                {!hasValidatedPpmp ? (
                    <p className="border-destructive/40 bg-destructive/5 rounded-lg border p-4 text-sm">
                        Your department needs a validated PPMP for {fiscalYear}{' '}
                        before you can create a purchase request.{' '}
                        <Link href={ppmpIndex.url()} className="underline">
                            Open PPMP
                        </Link>
                    </p>
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <StepHeading
                                    step={1}
                                    title="Request details"
                                    description="A short title and why the items are needed."
                                />
                            </CardHeader>
                            <CardContent>
                                <form
                                    id="pr-create"
                                    onSubmit={(event) => {
                                        event.preventDefault();

                                        const payload = {
                                            pr_title: form.data.pr_title,
                                            purpose: form.data.purpose,
                                            items: cartItemsPayload(
                                                lots,
                                                lines,
                                            ),
                                        };
                                        const parsed =
                                            purchaseRequestCreateSchema.safeParse(
                                                payload,
                                            );

                                        if (!parsed.success) {
                                            form.setError(
                                                zodIssuesToFormErrors(
                                                    parsed.error,
                                                ),
                                            );

                                            return;
                                        }

                                        form.transform(() => payload);
                                        form.post(submitUrl);
                                    }}
                                    className="grid gap-4"
                                >
                                    <div className="space-y-2">
                                        <Label htmlFor="pr_title">Title</Label>
                                        <Input
                                            id="pr_title"
                                            placeholder="e.g. Office supplies for Q1"
                                            value={form.data.pr_title}
                                            onChange={(event) =>
                                                form.setData(
                                                    'pr_title',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.validate('pr_title')
                                            }
                                        />
                                        <InputError
                                            message={form.errors.pr_title}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="purpose">Purpose</Label>
                                        <Textarea
                                            id="purpose"
                                            placeholder="What the items will be used for"
                                            value={form.data.purpose}
                                            onChange={(event) =>
                                                form.setData(
                                                    'purpose',
                                                    event.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.validate('purpose')
                                            }
                                        />
                                        <InputError
                                            message={form.errors.purpose}
                                        />
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

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

                        <PurchaseRequestSignatories
                            signatories={signatories}
                        />

                        <div className="bg-card sticky bottom-0 z-20 -mx-4 -mb-4 flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3 shadow-[0_-8px_24px_-12px_rgb(0_0_0/0.35)]">
                            <div className="space-y-0.5">
                                <p className="text-muted-foreground text-sm">
                                    {lines.length}{' '}
                                    {lines.length === 1 ? 'item' : 'items'}
                                    {lotCount > 0
                                        ? ` · ${lotCount} ${lotCount === 1 ? 'lot' : 'lots'}`
                                        : ''}{' '}
                                    · Estimated total{' '}
                                    <span className="text-foreground text-base font-semibold tabular-nums">
                                        {estimatedTotal.toLocaleString(
                                            undefined,
                                            {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2,
                                            },
                                        )}
                                    </span>
                                </p>
                                <InputError message={itemsError} />
                            </div>
                            <Button
                                type="submit"
                                form="pr-create"
                                disabled={
                                    form.processing || lines.length === 0
                                }
                                data-testid="submit-purchase-request"
                            >
                                {form.processing ? <Spinner /> : <Send />}
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
