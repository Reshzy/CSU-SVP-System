import { useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    earmarkSchema,
    type EarmarkFormValues,
} from '@/lib/earmark-schema';
import { cn } from '@/lib/utils';
import type { FundClusterOption, PurchaseRequest } from '@/types';

const textareaClass = cn(
    'border-input flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
);

const emptyLine = (): EarmarkFormValues['earmark_object_expenditures'][number] => ({
    code: null,
    description: '',
    amount: 0,
});

function dateInputValue(value: string | null | undefined): string {
    return value ? value.slice(0, 10) : '';
}

type Props = {
    actionUrl: string;
    method: 'put' | 'patch';
    purchaseRequest: PurchaseRequest;
    fundClusters: FundClusterOption[];
    submitLabel: string;
};

export function EarmarkForm({
    actionUrl,
    method,
    purchaseRequest,
    fundClusters,
    submitLabel,
}: Props) {
    const [clientError, setClientError] = useState<string | null>(null);

    const form = useForm(method, actionUrl, {
        legal_basis: purchaseRequest.legal_basis ?? '',
        earmark_programs_activities:
            purchaseRequest.earmark_programs_activities ?? '',
        earmark_responsibility_center:
            purchaseRequest.earmark_responsibility_center ?? '',
        earmark_date_to: dateInputValue(purchaseRequest.earmark_date_to),
        earmark_object_expenditures:
            purchaseRequest.earmark_object_expenditures?.map((item) => ({
                code: item.code ?? null,
                description: item.description,
                amount: Number(item.amount),
            })) ?? [emptyLine()],
        fund_cluster_code: (purchaseRequest.fund_cluster_code ??
            '01') as EarmarkFormValues['fund_cluster_code'],
        fund_details: purchaseRequest.fund_details ?? '',
        budget_code: purchaseRequest.budget_code ?? '',
        current_step_notes: purchaseRequest.current_step_notes ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const parsed = earmarkSchema.safeParse({
            ...form.data,
            fund_details: form.data.fund_details || null,
            budget_code: form.data.budget_code || null,
            current_step_notes: form.data.current_step_notes || null,
            earmark_object_expenditures:
                form.data.earmark_object_expenditures.map((item) => ({
                    ...item,
                    code: item.code || null,
                    amount: Number(item.amount),
                })),
        });

        if (!parsed.success) {
            setClientError(
                parsed.error.issues[0]?.message ?? 'Check the earmark form.',
            );

            return;
        }

        setClientError(null);
        form.transform(() => parsed.data);

        if (method === 'put') {
            form.put(actionUrl);
        } else {
            form.patch(actionUrl);
        }
    };

    const lines = form.data.earmark_object_expenditures;

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="legal_basis">Legal basis</Label>
                    <Input
                        id="legal_basis"
                        value={form.data.legal_basis}
                        placeholder="Section 86 of RA 9184"
                        onChange={(event) =>
                            form.setData('legal_basis', event.target.value)
                        }
                        onBlur={() => form.validate('legal_basis')}
                    />
                    <InputError message={form.errors.legal_basis} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="earmark_date_to">Earmark date to</Label>
                    <Input
                        id="earmark_date_to"
                        type="date"
                        value={form.data.earmark_date_to}
                        onChange={(event) =>
                            form.setData('earmark_date_to', event.target.value)
                        }
                        onBlur={() => form.validate('earmark_date_to')}
                    />
                    <InputError message={form.errors.earmark_date_to} />
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="earmark_programs_activities">
                        Programs / activities
                    </Label>
                    <textarea
                        id="earmark_programs_activities"
                        value={form.data.earmark_programs_activities}
                        onChange={(event) =>
                            form.setData(
                                'earmark_programs_activities',
                                event.target.value,
                            )
                        }
                        onBlur={() =>
                            form.validate('earmark_programs_activities')
                        }
                        className={textareaClass}
                    />
                    <InputError
                        message={form.errors.earmark_programs_activities}
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="earmark_responsibility_center">
                        Responsibility center
                    </Label>
                    <textarea
                        id="earmark_responsibility_center"
                        value={form.data.earmark_responsibility_center}
                        onChange={(event) =>
                            form.setData(
                                'earmark_responsibility_center',
                                event.target.value,
                            )
                        }
                        onBlur={() =>
                            form.validate('earmark_responsibility_center')
                        }
                        className={textareaClass}
                    />
                    <InputError
                        message={form.errors.earmark_responsibility_center}
                    />
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-3">
                <div className="space-y-2">
                    <Label htmlFor="fund_cluster_code">Fund cluster</Label>
                    <select
                        id="fund_cluster_code"
                        value={form.data.fund_cluster_code}
                        onChange={(event) =>
                            form.setData(
                                'fund_cluster_code',
                                event.target
                                    .value as EarmarkFormValues['fund_cluster_code'],
                            )
                        }
                        onBlur={() => form.validate('fund_cluster_code')}
                        className={cn(
                            'border-input h-9 w-full rounded-md border bg-transparent px-3 text-sm shadow-xs',
                        )}
                    >
                        {fundClusters.map((cluster) => (
                            <option key={cluster.code} value={cluster.code}>
                                {cluster.code} — {cluster.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.fund_cluster_code} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="fund_details">Fund details</Label>
                    <Input
                        id="fund_details"
                        value={form.data.fund_details ?? ''}
                        onChange={(event) =>
                            form.setData('fund_details', event.target.value)
                        }
                    />
                    <InputError message={form.errors.fund_details} />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="budget_code">Budget code</Label>
                    <Input
                        id="budget_code"
                        value={form.data.budget_code ?? ''}
                        onChange={(event) =>
                            form.setData('budget_code', event.target.value)
                        }
                    />
                    <InputError message={form.errors.budget_code} />
                </div>
            </div>

            <div className="space-y-2">
                <Label htmlFor="current_step_notes">Notes</Label>
                <textarea
                    id="current_step_notes"
                    value={form.data.current_step_notes ?? ''}
                    onChange={(event) =>
                        form.setData('current_step_notes', event.target.value)
                    }
                    className={textareaClass}
                />
                <InputError message={form.errors.current_step_notes} />
            </div>

            <div className="space-y-3">
                <div className="flex items-center justify-between gap-2">
                    <Label>Object of expenditures</Label>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            form.setData('earmark_object_expenditures', [
                                ...lines,
                                emptyLine(),
                            ])
                        }
                    >
                        Add line
                    </Button>
                </div>
                {lines.map((line, index) => (
                    <div
                        key={index}
                        className="grid gap-2 rounded-xl border p-3 md:grid-cols-[8rem_1fr_8rem_auto]"
                    >
                        <Input
                            placeholder="Code"
                            value={line.code ?? ''}
                            onChange={(event) => {
                                const next = [...lines];
                                next[index] = {
                                    ...line,
                                    code: event.target.value,
                                };
                                form.setData(
                                    'earmark_object_expenditures',
                                    next,
                                );
                            }}
                        />
                        <Input
                            placeholder="Description"
                            value={line.description}
                            onChange={(event) => {
                                const next = [...lines];
                                next[index] = {
                                    ...line,
                                    description: event.target.value,
                                };
                                form.setData(
                                    'earmark_object_expenditures',
                                    next,
                                );
                            }}
                        />
                        <Input
                            type="number"
                            min={0}
                            step="0.01"
                            value={line.amount}
                            onChange={(event) => {
                                const next = [...lines];
                                next[index] = {
                                    ...line,
                                    amount: Number(event.target.value),
                                };
                                form.setData(
                                    'earmark_object_expenditures',
                                    next,
                                );
                            }}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={lines.length <= 1}
                            onClick={() =>
                                form.setData(
                                    'earmark_object_expenditures',
                                    lines.filter((_, i) => i !== index),
                                )
                            }
                        >
                            Remove
                        </Button>
                        <InputError
                            message={
                                form.errors[
                                    `earmark_object_expenditures.${index}.description`
                                ]
                            }
                        />
                    </div>
                ))}
                <InputError message={form.errors.earmark_object_expenditures} />
            </div>

            <InputError message={clientError ?? undefined} />

            <Button type="submit" disabled={form.processing}>
                {form.processing && <Spinner />}
                {submitLabel}
            </Button>
        </form>
    );
}
