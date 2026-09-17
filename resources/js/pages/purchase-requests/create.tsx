import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
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
    key: string;
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

export default function PurchaseRequestCreate({
    fiscalYear,
    quarter,
    hasValidatedPpmp,
    availableItems,
    fundClusters,
}: Props) {
    const [lines, setLines] = useState<LineDraft[]>([
        { key: crypto.randomUUID(), ppmp_item_id: '', quantity_requested: '1' },
    ]);

    const form = useForm({
        pr_title: '',
        purpose: '',
        justification: '',
        date_needed: '',
        fund_cluster_code: '01',
        fund_details: '',
        budget_code: '',
        items: [] as Array<{ ppmp_item_id: number; quantity_requested: number }>,
    });

    const estimatedTotal = useMemo(() => {
        return lines.reduce((sum, line) => {
            const item = availableItems.find((candidate) => String(candidate.id) === line.ppmp_item_id);
            if (!item) {
                return sum;
            }

            return sum + Number(line.quantity_requested || 0) * Number(item.estimated_unit_cost);
        }, 0);
    }, [availableItems, lines]);

    function submit(event: React.FormEvent) {
        event.preventDefault();

        form
            .transform((data) => ({
                ...data,
                items: lines
                    .filter((line) => line.ppmp_item_id !== '')
                    .map((line) => ({
                        ppmp_item_id: Number(line.ppmp_item_id),
                        quantity_requested: Number(line.quantity_requested),
                    })),
            }))
            .post(store.url());
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
                        Your department needs a validated PPMP for {fiscalYear} before you can create a
                        purchase request.{' '}
                        <Link href="/ppmp" className="underline">
                            Open PPMP
                        </Link>
                    </p>
                ) : (
                    <form onSubmit={submit} className="flex max-w-4xl flex-col gap-6">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="pr_title">Title</Label>
                                <Input
                                    id="pr_title"
                                    value={form.data.pr_title}
                                    onChange={(event) => form.setData('pr_title', event.target.value)}
                                />
                                <InputError message={form.errors.pr_title} />
                            </div>

                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="purpose">Purpose</Label>
                                <Textarea
                                    id="purpose"
                                    value={form.data.purpose}
                                    onChange={(event) => form.setData('purpose', event.target.value)}
                                />
                                <InputError message={form.errors.purpose} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="date_needed">Date needed</Label>
                                <Input
                                    id="date_needed"
                                    type="date"
                                    value={form.data.date_needed}
                                    onChange={(event) => form.setData('date_needed', event.target.value)}
                                />
                                <InputError message={form.errors.date_needed} />
                            </div>

                            <div className="space-y-2">
                                <Label>Fund cluster</Label>
                                <Select
                                    value={form.data.fund_cluster_code}
                                    onValueChange={(value) => form.setData('fund_cluster_code', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {fundClusters.map((cluster) => (
                                            <SelectItem key={cluster.value} value={cluster.value}>
                                                {cluster.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={form.errors.fund_cluster_code} />
                            </div>
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <h2 className="text-base font-medium">Items</h2>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        setLines((current) => [
                                            ...current,
                                            {
                                                key: crypto.randomUUID(),
                                                ppmp_item_id: '',
                                                quantity_requested: '1',
                                            },
                                        ])
                                    }
                                >
                                    Add line
                                </Button>
                            </div>

                            {lines.map((line, index) => (
                                <div
                                    key={line.key}
                                    className="grid gap-3 rounded-lg border p-3 md:grid-cols-[1fr_8rem_auto]"
                                >
                                    <div className="space-y-2">
                                        <Label>PPMP item</Label>
                                        <Select
                                            value={line.ppmp_item_id}
                                            onValueChange={(value) =>
                                                setLines((current) =>
                                                    current.map((candidate, candidateIndex) =>
                                                        candidateIndex === index
                                                            ? { ...candidate, ppmp_item_id: value }
                                                            : candidate,
                                                    ),
                                                )
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select item" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {availableItems.map((item) => (
                                                    <SelectItem
                                                        key={item.id}
                                                        value={String(item.id)}
                                                        disabled={item.remaining_qty <= 0}
                                                    >
                                                        {item.item_code} — {item.item_name} (rem{' '}
                                                        {item.remaining_qty})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={form.errors[`items.${index}.ppmp_item_id`]} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Qty</Label>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={line.quantity_requested}
                                            onChange={(event) =>
                                                setLines((current) =>
                                                    current.map((candidate, candidateIndex) =>
                                                        candidateIndex === index
                                                            ? {
                                                                  ...candidate,
                                                                  quantity_requested: event.target.value,
                                                              }
                                                            : candidate,
                                                    ),
                                                )
                                            }
                                        />
                                        <InputError
                                            message={form.errors[`items.${index}.quantity_requested`]}
                                        />
                                    </div>

                                    <div className="flex items-end">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            disabled={lines.length === 1}
                                            onClick={() =>
                                                setLines((current) =>
                                                    current.filter((_, candidateIndex) => candidateIndex !== index),
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
                            <Button type="submit" disabled={form.processing}>
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
