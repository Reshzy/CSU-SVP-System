import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { index, update } from '@/routes/accounting/vouchers';

type Voucher = {
    id: number;
    voucher_number: string;
    amount: string;
    voucher_date: string;
    status: string;
    remarks: string | null;
    purchase_order?: { po_number: string; purchase_request?: { pr_number: string | null } | null } | null;
    supplier?: { business_name: string } | null;
    prepared_by?: { name: string } | null;
};

type Props = { voucher: Voucher };

const actions = ['approve', 'release', 'mark_paid', 'cancel'] as const;

export default function AccountingVouchersShow({ voucher }: Props) {
    const form = useForm({
        action: 'approve',
        remarks: voucher.remarks ?? '',
    });

    return (
        <>
            <Head title={voucher.voucher_number} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={voucher.voucher_number}
                    description={`${voucher.purchase_order?.po_number ?? ''} · ${voucher.status}`}
                />
                <p className="text-sm">Supplier: {voucher.supplier?.business_name}</p>
                <p className="text-sm">PR: {voucher.purchase_order?.purchase_request?.pr_number}</p>
                <p className="text-sm">Amount: {voucher.amount}</p>
                <p className="text-sm">Prepared by: {voucher.prepared_by?.name}</p>

                <form
                    className="max-w-xl space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(update.url(voucher.id));
                    }}
                >
                    <div className="space-y-2">
                        <Label>Action</Label>
                        <select
                            className="border-input h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.action}
                            onChange={(event) => form.setData('action', event.target.value as (typeof actions)[number])}
                        >
                            {actions.map((action) => (
                                <option key={action} value={action}>
                                    {action.replaceAll('_', ' ')}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Update voucher
                    </Button>
                </form>
            </div>
        </>
    );
}

AccountingVouchersShow.layout = {
    breadcrumbs: [{ title: 'Disbursement vouchers', href: index() }],
};
