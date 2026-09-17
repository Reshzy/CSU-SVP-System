import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type PurchaseRequest = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    status: string;
    legal_basis: string | null;
    earmark_programs_activities: string | null;
    earmark_responsibility_center: string | null;
    earmark_date_to: string | null;
    current_step_notes: string | null;
};

type Props = {
    purchaseRequest: PurchaseRequest;
    statusLabel: string;
};

export default function BudgetPurchaseRequestEdit({ purchaseRequest, statusLabel }: Props) {
    const form = useForm({
        legal_basis: purchaseRequest.legal_basis ?? 'Section 86 of RA 9184',
        earmark_programs_activities: purchaseRequest.earmark_programs_activities ?? '',
        earmark_responsibility_center: purchaseRequest.earmark_responsibility_center ?? '',
        earmark_date_to: purchaseRequest.earmark_date_to ?? '',
        current_step_notes: purchaseRequest.current_step_notes ?? '',
        earmark_object_expenditures: [{ description: 'Supplies', amount: 0 }],
        rejection_reason: '',
    });

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
                        form.put(`/budget/purchase-requests/${purchaseRequest.id}`);
                    }}
                >
                    <div className="space-y-2">
                        <Label>Legal basis</Label>
                        <Input
                            value={form.data.legal_basis}
                            onChange={(event) => form.setData('legal_basis', event.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Programs / activities</Label>
                        <Textarea
                            value={form.data.earmark_programs_activities}
                            onChange={(event) =>
                                form.setData('earmark_programs_activities', event.target.value)
                            }
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Responsibility center</Label>
                        <Input
                            value={form.data.earmark_responsibility_center}
                            onChange={(event) =>
                                form.setData('earmark_responsibility_center', event.target.value)
                            }
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Earmark date to</Label>
                        <Input
                            type="date"
                            value={form.data.earmark_date_to}
                            onChange={(event) => form.setData('earmark_date_to', event.target.value)}
                        />
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            Approve earmark
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                form.post(`/budget/purchase-requests/${purchaseRequest.id}/reject`, {
                                    data: { rejection_reason: form.data.rejection_reason || 'Deferred' },
                                })
                            }
                        >
                            Defer
                        </Button>
                    </div>
                    <div className="space-y-2">
                        <Label>Deferral reason</Label>
                        <Textarea
                            value={form.data.rejection_reason}
                            onChange={(event) => form.setData('rejection_reason', event.target.value)}
                        />
                    </div>
                </form>
            </div>
        </>
    );
}
