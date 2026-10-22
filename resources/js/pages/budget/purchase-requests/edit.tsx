import { Head, useForm } from '@inertiajs/react';
import { earmarkSchema, zodIssuesToFormErrors } from '@/lib/schemas';
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
    amendEarmark,
    exportEarmark,
    index,
    reject,
    update,
} from '@/routes/budget/purchase-requests';

type PurchaseRequest = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    status: string;
    earmark_id: string | null;
    legal_basis: string | null;
    fund_cluster_code: string | null;
    earmark_programs_activities: string | null;
    earmark_responsibility_center: string | null;
    earmark_date_to: string | null;
    current_step_notes: string | null;
};

type FundCluster = { value: string; label: string };

type Props = {
    purchaseRequest: PurchaseRequest;
    statusLabel: string;
    fundClusters: FundCluster[];
};

function dateInputValue(value: string | null): string {
    return value ? value.slice(0, 10) : '';
}

export default function BudgetPurchaseRequestEdit({
    purchaseRequest,
    statusLabel,
    fundClusters,
}: Props) {
    const form = usePrecognitiveForm(
        'put',
        update.url(purchaseRequest.id),
        {
            legal_basis: purchaseRequest.legal_basis ?? 'Section 86 of RA 9184',
            fund_cluster_code: purchaseRequest.fund_cluster_code ?? '',
            earmark_programs_activities:
                purchaseRequest.earmark_programs_activities ?? '',
            earmark_responsibility_center:
                purchaseRequest.earmark_responsibility_center ?? '',
            earmark_date_to: dateInputValue(purchaseRequest.earmark_date_to),
            current_step_notes: purchaseRequest.current_step_notes ?? '',
            earmark_object_expenditures: [
                { description: 'Supplies', amount: 0 },
            ],
        },
    );

    const rejectForm = useForm({
        rejection_reason: '',
    });

    function earmarkPayload() {
        return {
            legal_basis: form.data.legal_basis,
            fund_cluster_code: form.data.fund_cluster_code,
            earmark_programs_activities: form.data.earmark_programs_activities,
            earmark_responsibility_center:
                form.data.earmark_responsibility_center,
            earmark_date_to: form.data.earmark_date_to,
            current_step_notes: form.data.current_step_notes,
            earmark_object_expenditures:
                form.data.earmark_object_expenditures.map((row) => ({
                    description: row.description,
                    amount: Number(row.amount ?? 0),
                })),
        };
    }

    function applyZod(): boolean {
        const parsed = earmarkSchema.safeParse(earmarkPayload());

        if (!parsed.success) {
            form.setError(zodIssuesToFormErrors(parsed.error));

            return false;
        }

        return true;
    }

    const canApprove = purchaseRequest.status === 'budget_office_review';
    const canAmend = Boolean(purchaseRequest.earmark_id);

    return (
        <>
            <Head title={`Earmark ${purchaseRequest.pr_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={purchaseRequest.pr_number ?? 'Earmark'}
                    description={`${purchaseRequest.pr_title} · ${statusLabel}`}
                />

                <form
                    className="grid max-w-3xl gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (!applyZod()) {
                            return;
                        }

                        form.put(update.url(purchaseRequest.id));
                    }}
                >
                    <div className="space-y-2">
                        <Label htmlFor="legal_basis">Legal basis</Label>
                        <Input
                            id="legal_basis"
                            value={form.data.legal_basis}
                            onChange={(event) =>
                                form.setData('legal_basis', event.target.value)
                            }
                            onBlur={() => form.validate('legal_basis')}
                        />
                        <InputError message={form.errors.legal_basis} />
                    </div>
                    <div className="space-y-2">
                        <Label>Fund cluster</Label>
                        <Select
                            value={form.data.fund_cluster_code || undefined}
                            onValueChange={(value) =>
                                form.setData('fund_cluster_code', value)
                            }
                        >
                            <SelectTrigger data-testid="fund-cluster-select">
                                <SelectValue placeholder="Select fund cluster" />
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
                        <InputError message={form.errors.fund_cluster_code} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="earmark_programs_activities">
                            Programs / activities
                        </Label>
                        <Textarea
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
                        />
                        <InputError
                            message={form.errors.earmark_programs_activities}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="earmark_responsibility_center">
                            Responsibility center
                        </Label>
                        <Input
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
                        />
                        <InputError
                            message={
                                form.errors.earmark_responsibility_center
                            }
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="earmark_date_to">Earmark date to</Label>
                        <Input
                            id="earmark_date_to"
                            type="date"
                            value={form.data.earmark_date_to}
                            onChange={(event) =>
                                form.setData(
                                    'earmark_date_to',
                                    event.target.value,
                                )
                            }
                            onBlur={() => form.validate('earmark_date_to')}
                        />
                        <InputError message={form.errors.earmark_date_to} />
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {canApprove && (
                            <Button type="submit" disabled={form.processing}>
                                Approve earmark
                            </Button>
                        )}
                        {canAmend && (
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={() => {
                                    if (!applyZod()) {
                                        return;
                                    }

                                    form.patch(
                                        amendEarmark.url(purchaseRequest.id),
                                    );
                                }}
                            >
                                Amend
                            </Button>
                        )}
                        <Button variant="outline" asChild>
                            <a href={exportEarmark.url(purchaseRequest.id)}>
                                Export earmark
                            </a>
                        </Button>
                        {canApprove && (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    rejectForm.post(
                                        reject.url(purchaseRequest.id),
                                    )
                                }
                            >
                                Defer
                            </Button>
                        )}
                    </div>
                    {canApprove && (
                        <div className="space-y-2">
                            <Label>Deferral reason</Label>
                            <Textarea
                                value={rejectForm.data.rejection_reason}
                                onChange={(event) =>
                                    rejectForm.setData(
                                        'rejection_reason',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={rejectForm.errors.rejection_reason}
                            />
                        </div>
                    )}
                </form>
            </div>
        </>
    );
}

BudgetPurchaseRequestEdit.layout = {
    breadcrumbs: [{ title: 'Budget queue', href: index() }],
};
