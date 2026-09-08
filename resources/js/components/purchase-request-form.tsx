import { useForm, useHttp } from '@inertiajs/react';
import { useMemo, useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { cn } from '@/lib/utils';
import {
    purchaseRequestSchema,
    type PurchaseRequestFormValues,
} from '@/lib/purchase-request-schema';
import { validate as validateBudget } from '@/routes/api/budget';
import { store } from '@/routes/purchase-requests';
import type {
    BudgetValidateResponse,
    DepartmentBudgetSummary,
    PpmpLineForPr,
} from '@/types';

type QtyMap = Record<number, number>;

type Props = {
    categorizedItems: Record<string, PpmpLineForPr[]>;
    departmentBudget: DepartmentBudgetSummary;
    currentQuarter: number;
    quarterLabel: string;
    actionUrl?: string;
    defaults?: {
        purpose?: string;
        justification?: string;
        quantities?: QtyMap;
        lotName?: string;
    };
    submitLabel?: string;
};

export function PurchaseRequestForm({
    categorizedItems,
    departmentBudget,
    currentQuarter,
    quarterLabel,
    actionUrl,
    defaults,
    submitLabel = 'Submit request',
}: Props) {
    const lines = useMemo(
        () => Object.values(categorizedItems).flat(),
        [categorizedItems],
    );

    const [quantities, setQuantities] = useState<QtyMap>(
        () => defaults?.quantities ?? {},
    );
    const [lotName, setLotName] = useState(defaults?.lotName ?? '');
    const [clientError, setClientError] = useState<string | null>(null);

    const selected = lines.filter((line) => (quantities[line.id] ?? 0) > 0);

    const requiredTotal = selected.reduce((sum, line) => {
        return sum + (quantities[line.id] ?? 0) * Number(line.estimated_unit_cost);
    }, 0);

    const form = useForm('post', actionUrl ?? store.url(), {
        purpose: defaults?.purpose ?? '',
        justification: defaults?.justification ?? '',
        items: [] as PurchaseRequestFormValues['items'],
    });

    const budget = useHttp<
        { amount: number },
        BudgetValidateResponse
    >('post', validateBudget.url(), { amount: 0 });

    const buildPayload = (): PurchaseRequestFormValues['items'] => {
        const standalones = selected.map((line) => ({
            ppmp_item_id: line.id,
            item_code: line.item_code,
            item_name: line.item_name,
            detailed_specifications: null,
            unit_of_measure: line.unit_of_measure,
            quantity_requested: quantities[line.id] ?? 0,
            estimated_unit_cost: Number(line.estimated_unit_cost),
            is_lot: false,
            lot_name: null,
            parent_lot_index: null,
        }));

        if (lotName.trim() !== '' && standalones.length >= 2) {
            const header = {
                ppmp_item_id: null,
                item_code: null,
                item_name: lotName.trim(),
                detailed_specifications: null,
                unit_of_measure: 'lot',
                quantity_requested: 1,
                estimated_unit_cost: requiredTotal,
                is_lot: true,
                lot_name: lotName.trim(),
                parent_lot_index: null,
            };

            return [
                header,
                ...standalones.map((item) => ({
                    ...item,
                    parent_lot_index: 0,
                })),
            ];
        }

        return standalones;
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const items = buildPayload();
        const parsed = purchaseRequestSchema.safeParse({
            purpose: form.data.purpose,
            justification: form.data.justification,
            items,
        });

        if (!parsed.success) {
            setClientError(parsed.error.issues[0]?.message ?? 'Check the form.');

            return;
        }

        setClientError(null);
        form.transform(() => parsed.data);
        form.post(actionUrl ?? store.url());
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="rounded-xl border p-4 text-sm">
                <p>
                    Current quarter: Q{currentQuarter} ({quarterLabel}). Available
                    budget: {departmentBudget.available_budget.toFixed(2)}.
                    Required: {requiredTotal.toFixed(2)}.
                </p>
                {budget.response?.data && (
                    <p className="text-muted-foreground mt-1">
                        {budget.response.data.can_reserve
                            ? 'This amount can be reserved.'
                            : `Shortage: ${budget.response.data.shortage.toFixed(2)}`}
                    </p>
                )}
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="purpose">Purpose</Label>
                    <Input
                        id="purpose"
                        value={form.data.purpose}
                        onChange={(event) =>
                            form.setData('purpose', event.target.value)
                        }
                        onBlur={() => form.validate('purpose')}
                    />
                    <InputError message={form.errors.purpose} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="justification">Justification</Label>
                    <textarea
                        id="justification"
                        value={form.data.justification}
                        onChange={(event) =>
                            form.setData('justification', event.target.value)
                        }
                        onBlur={() => form.validate('justification')}
                        className={cn(
                            'border-input flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
                            'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                        )}
                    />
                    <InputError message={form.errors.justification} />
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="lot_name">
                    Optional lot name (needs at least two items)
                </Label>
                <Input
                    id="lot_name"
                    value={lotName}
                    onChange={(event) => setLotName(event.target.value)}
                    placeholder="Leave blank for standalone items"
                />
            </div>

            {Object.entries(categorizedItems).map(([category, items]) => (
                <div key={category} className="rounded-xl border">
                    <div className="border-b px-4 py-2 text-sm font-medium">
                        {category}
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Item</TableHead>
                                <TableHead className="text-right">
                                    Remaining Q{currentQuarter}
                                </TableHead>
                                <TableHead className="text-right">
                                    Unit cost
                                </TableHead>
                                <TableHead className="w-28">Qty</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.map((item) => {
                                const available =
                                    item.has_current_quarter_qty &&
                                    item.remaining_qty > 0;

                                return (
                                    <TableRow key={item.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {item.item_name}
                                            </div>
                                            <div className="text-muted-foreground text-xs">
                                                {item.item_code} ·{' '}
                                                {item.unit_of_measure}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {item.remaining_qty}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {item.estimated_unit_cost}
                                        </TableCell>
                                        <TableCell>
                                            <Input
                                                type="number"
                                                min={0}
                                                max={item.remaining_qty}
                                                disabled={!available}
                                                value={quantities[item.id] ?? 0}
                                                onChange={(event) =>
                                                    setQuantities((current) => ({
                                                        ...current,
                                                        [item.id]: Number(
                                                            event.target.value,
                                                        ),
                                                    }))
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>
            ))}

            <InputError
                message={
                    typeof form.errors.items === 'string'
                        ? form.errors.items
                        : (form.errors as Record<string, string | undefined>)
                              .budget
                }
            />
            <InputError message={clientError ?? undefined} />

            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={requiredTotal <= 0 || budget.processing}
                    onClick={() => {
                        budget.setData('amount', requiredTotal);
                        budget.post(validateBudget.url());
                    }}
                >
                    {budget.processing && <Spinner />}
                    Check budget
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
