import { Head, Link } from '@inertiajs/react';
import { useMemo } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { usePrecognitiveForm } from '@/hooks/use-precognitive-form';
import {
    purchaseRequestCreateSchema,
    zodIssuesToFormErrors,
} from '@/lib/schemas';
import { index as ppmpIndex } from '@/routes/ppmp';
import { create, index, store } from '@/routes/purchase-requests';

type AvailableItem = {
    id: number;
    item_code: string | null;
    item_name: string | null;
    unit_of_measure: string | null;
    estimated_unit_cost: string;
    planned_qty: number;
    remaining_qty: number;
};

type FundCluster = { value: string; label: string };

type LineDraft = {
    is_lot: boolean;
    lot_name: string;
    parent_lot_index: string;
    ppmp_item_id: string;
    quantity_requested: string;
};

type Props = {
    fiscalYear: number;
    quarter: number;
    hasValidatedPpmp: boolean;
    availableItems: AvailableItem[];
    fundClusters: FundCluster[];
};

function emptyItem(): LineDraft {
    return {
        is_lot: false,
        lot_name: '',
        parent_lot_index: '',
        ppmp_item_id: '',
        quantity_requested: '1',
    };
}

function emptyLot(): LineDraft {
    return {
        is_lot: true,
        lot_name: '',
        parent_lot_index: '',
        ppmp_item_id: '',
        quantity_requested: '1',
    };
}

export default function PurchaseRequestCreate({
    fiscalYear,
    quarter,
    hasValidatedPpmp,
    availableItems,
    fundClusters,
}: Props) {
    const form = usePrecognitiveForm('post', store.url(), {
        pr_title: '',
        purpose: '',
        justification: '',
        date_needed: '',
        fund_cluster_code: '01',
        fund_details: '',
        budget_code: '',
        items: [emptyItem()] as LineDraft[],
    });

    const estimatedTotal = useMemo(() => {
        return form.data.items.reduce((sum, line, index) => {
            if (line.is_lot) {
                return sum;
            }

            const item = availableItems.find(
                (candidate) => String(candidate.id) === line.ppmp_item_id,
            );

            if (!item) {
                return sum;
            }

            const parentIsLot =
                line.parent_lot_index !== '' &&
                form.data.items[Number(line.parent_lot_index)]?.is_lot;

            if (parentIsLot && Number(line.parent_lot_index) < index) {
                // Lot header already sums children on the server; skip double-count
                // only when attaching to a lot that is also in the payload.
            }

            return (
                sum +
                Number(line.quantity_requested || 0) *
                    Number(item.estimated_unit_cost)
            );
        }, 0);
    }, [availableItems, form.data.items]);

    function updateLine(index: number, patch: Partial<LineDraft>): void {
        const items = form.data.items.map((line, lineIndex) =>
            lineIndex === index ? { ...line, ...patch } : line,
        );
        form.setData('items', items);
    }

    function submitPayload() {
        return {
            pr_title: form.data.pr_title,
            purpose: form.data.purpose,
            justification: form.data.justification || null,
            date_needed: form.data.date_needed,
            fund_cluster_code: form.data.fund_cluster_code,
            fund_details: form.data.fund_details || null,
            budget_code: form.data.budget_code || null,
            items: form.data.items.map((line) => {
                if (line.is_lot) {
                    return {
                        is_lot: true,
                        lot_name: line.lot_name,
                        ppmp_item_id: null,
                        quantity_requested: null,
                        parent_lot_index: null,
                    };
                }

                return {
                    is_lot: false,
                    lot_name: null,
                    ppmp_item_id: Number(line.ppmp_item_id),
                    quantity_requested: Number(line.quantity_requested),
                    parent_lot_index:
                        line.parent_lot_index === ''
                            ? null
                            : Number(line.parent_lot_index),
                };
            }),
        };
    }

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
                        <Link
                            href={ppmpIndex.url()}
                            className="underline"
                        >
                            Open PPMP
                        </Link>
                    </p>
                ) : (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            const payload = submitPayload();
                            const parsed =
                                purchaseRequestCreateSchema.safeParse(payload);

                            if (!parsed.success) {
                                form.setError(
                                    zodIssuesToFormErrors(parsed.error),
                                );

                                return;
                            }

                            form.transform(() => payload);
                            form.post(store.url());
                        }}
                        className="flex max-w-4xl flex-col gap-6"
                    >
                        <div className="grid gap-4 md:grid-cols-2">
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

                            <div className="space-y-2">
                                <Label htmlFor="date_needed">Date needed</Label>
                                <Input
                                    id="date_needed"
                                    type="date"
                                    value={form.data.date_needed}
                                    onChange={(event) =>
                                        form.setData(
                                            'date_needed',
                                            event.target.value,
                                        )
                                    }
                                    onBlur={() => form.validate('date_needed')}
                                />
                                <InputError
                                    message={form.errors.date_needed}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label>Fund cluster</Label>
                                <Select
                                    value={form.data.fund_cluster_code}
                                    onValueChange={(value) =>
                                        form.setData('fund_cluster_code', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {fundClusters.map((cluster) => (
                                            <SelectItem
                                                key={cluster.value}
                                                value={cluster.value}
                                            >
                                                {cluster.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={form.errors.fund_cluster_code}
                                />
                            </div>
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h2 className="text-base font-medium">Items</h2>
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            form.setData('items', [
                                                ...form.data.items,
                                                emptyItem(),
                                            ])
                                        }
                                    >
                                        Add line
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            form.setData('items', [
                                                ...form.data.items,
                                                emptyLot(),
                                            ])
                                        }
                                    >
                                        Add lot
                                    </Button>
                                </div>
                            </div>

                            {form.data.items.map((line, index) => (
                                <div
                                    key={index}
                                    className="grid gap-3 rounded-lg border p-3 md:grid-cols-[1fr_8rem_8rem_auto]"
                                >
                                    {line.is_lot ? (
                                        <div className="space-y-2 md:col-span-2">
                                            <Label>Lot name</Label>
                                            <Input
                                                value={line.lot_name}
                                                onChange={(event) =>
                                                    updateLine(index, {
                                                        lot_name:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                            <InputError
                                                message={
                                                    form.errors[
                                                        `items.${index}.lot_name`
                                                    ]
                                                }
                                            />
                                        </div>
                                    ) : (
                                        <>
                                            <div className="space-y-2">
                                                <Label>PPMP item</Label>
                                                <Select
                                                    value={line.ppmp_item_id}
                                                    onValueChange={(value) =>
                                                        updateLine(index, {
                                                            ppmp_item_id: value,
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger data-testid="ppmp-item-select">
                                                        <SelectValue placeholder="Select item" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {availableItems.map(
                                                            (item) => (
                                                                <SelectItem
                                                                    key={item.id}
                                                                    value={String(
                                                                        item.id,
                                                                    )}
                                                                    disabled={
                                                                        item.remaining_qty <=
                                                                        0
                                                                    }
                                                                >
                                                                    {item.item_code}{' '}
                                                                    —{' '}
                                                                    {
                                                                        item.item_name
                                                                    }{' '}
                                                                    (rem{' '}
                                                                    {
                                                                        item.remaining_qty
                                                                    }
                                                                    )
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={
                                                        form.errors[
                                                            `items.${index}.ppmp_item_id`
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Qty</Label>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    value={
                                                        line.quantity_requested
                                                    }
                                                    onChange={(event) =>
                                                        updateLine(index, {
                                                            quantity_requested:
                                                                event.target
                                                                    .value,
                                                        })
                                                    }
                                                />
                                                <InputError
                                                    message={
                                                        form.errors[
                                                            `items.${index}.quantity_requested`
                                                        ]
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Lot</Label>
                                                <Select
                                                    value={
                                                        line.parent_lot_index ||
                                                        'none'
                                                    }
                                                    onValueChange={(value) =>
                                                        updateLine(index, {
                                                            parent_lot_index:
                                                                value ===
                                                                'none'
                                                                    ? ''
                                                                    : value,
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="None" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="none">
                                                            Standalone
                                                        </SelectItem>
                                                        {form.data.items.map(
                                                            (
                                                                candidate,
                                                                candidateIndex,
                                                            ) =>
                                                                candidate.is_lot ? (
                                                                    <SelectItem
                                                                        key={
                                                                            candidateIndex
                                                                        }
                                                                        value={String(
                                                                            candidateIndex,
                                                                        )}
                                                                    >
                                                                        {candidate.lot_name ||
                                                                            `Lot ${candidateIndex + 1}`}
                                                                    </SelectItem>
                                                                ) : null,
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </>
                                    )}

                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            disabled={
                                                form.data.items.length === 1
                                            }
                                            onClick={() =>
                                                form.setData(
                                                    'items',
                                                    form.data.items.filter(
                                                        (
                                                            _,
                                                            candidateIndex,
                                                        ) =>
                                                            candidateIndex !==
                                                            index,
                                                    ),
                                                )
                                            }
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            <InputError message={form.errors.items} />
                        </div>

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
                                disabled={form.processing}
                                data-testid="submit-purchase-request"
                            >
                                Submit to Supply
                            </Button>
                        </div>
                    </form>
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
