import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index, store } from '@/routes/accounting/vouchers';

type PurchaseOrder = {
    id: number;
    po_number: string;
    total_amount: string | null;
    supplier?: { business_name: string } | null;
};

type Props = { purchaseOrder: PurchaseOrder };

export default function AccountingVouchersCreate({ purchaseOrder }: Props) {
    const form = useForm({
        amount: purchaseOrder.total_amount ?? '',
        voucher_date: '',
        remarks: '',
    });

    return (
        <>
            <Head title="Create disbursement voucher" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`DV for ${purchaseOrder.po_number}`}
                    description={purchaseOrder.supplier?.business_name ?? ''}
                />
                <form
                    className="max-w-xl space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(store.url(purchaseOrder.id));
                    }}
                >
                    <div className="space-y-2">
                        <Label>Amount</Label>
                        <Input value={form.data.amount} onChange={(event) => form.setData('amount', event.target.value)} />
                        <InputError message={form.errors.amount} />
                    </div>
                    <div className="space-y-2">
                        <Label>Voucher date</Label>
                        <Input
                            type="date"
                            value={form.data.voucher_date}
                            onChange={(event) => form.setData('voucher_date', event.target.value)}
                        />
                        <InputError message={form.errors.voucher_date} />
                    </div>
                    <div className="space-y-2">
                        <Label>Remarks</Label>
                        <Textarea value={form.data.remarks} onChange={(event) => form.setData('remarks', event.target.value)} />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Create voucher
                    </Button>
                </form>
            </div>
        </>
    );
}

AccountingVouchersCreate.layout = {
    breadcrumbs: [{ title: 'Disbursement vouchers', href: index() }],
};
