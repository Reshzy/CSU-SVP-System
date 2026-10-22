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
import { create, index, store } from '@/routes/purchase-requests';

type Props = {
    fiscalYear: number;
    quarter: number;
    hasValidatedPpmp: boolean;
    availableItems: AvailablePpmpItem[];
};

export default function PurchaseRequestCreate({
    fiscalYear,
    quarter,
    hasValidatedPpmp,
    availableItems,
}: Props) {
    const form = usePrecognitiveForm('post', store.url(), {
        pr_title: '',
        purpose: '',
    });
    const [lines, setLines] = useState<PurchaseRequestCartLine[]>([]);
    const [lots, setLots] = useState<PurchaseRequestCartLot[]>([]);

    const estimatedTotal = useMemo(() => {
        return lines.reduce((sum, line) => {
            const item = availableItems.find(
                (candidate) => candidate.id === line.ppmp_item_id,
            );

            if (!item) {
                return sum;
            }

            return (
                sum +
                line.quantity_requested * Number(item.estimated_unit_cost)
            );
        }, 0);
    }, [availableItems, lines]);

    return (
        <>
            <Head title="New purchase request" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="New purchase request"
                    description={`FY ${fiscalYear} · Q${quarter}. Submitted requests go straight to Supply Office review.`}
                />

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
                                form.post(store.url());
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
                                Submit to Supply
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
