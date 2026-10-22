import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { create, index, show } from '@/routes/accounting/vouchers';
import type { Paginated } from '@/types';

type Voucher = {
    id: number;
    voucher_number: string;
    amount: string;
    voucher_date: string;
    status: string;
    purchase_order?: { po_number: string } | null;
    supplier?: { business_name: string } | null;
};

type PayableOrder = {
    id: number;
    po_number: string;
    status: string;
    total_amount: string | null;
    supplier?: { business_name: string } | null;
};

type Props = {
    vouchers: Paginated<Voucher>;
    payableOrders: PayableOrder[];
};

export default function AccountingVouchersIndex({ vouchers, payableOrders }: Props) {
    return (
        <>
            <Head title="Disbursement vouchers" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Disbursement vouchers" description="Accounting Office payment chain." />

                {payableOrders.length > 0 && (
                    <div className="space-y-2">
                        <h2 className="text-sm font-medium">Payable purchase orders</h2>
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-3 py-2">PO</th>
                                        <th className="px-3 py-2">Supplier</th>
                                        <th className="px-3 py-2">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payableOrders.map((purchaseOrder) => (
                                        <tr key={purchaseOrder.id} className="border-t">
                                            <td className="px-3 py-2">{purchaseOrder.po_number}</td>
                                            <td className="px-3 py-2">{purchaseOrder.supplier?.business_name}</td>
                                            <td className="px-3 py-2">
                                                <Link href={create.url(purchaseOrder.id)} className="text-primary underline">
                                                    Create DV
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Voucher</th>
                                <th className="px-3 py-2">PO</th>
                                <th className="px-3 py-2">Supplier</th>
                                <th className="px-3 py-2">Amount</th>
                                <th className="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {vouchers.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                                        No vouchers yet.
                                    </td>
                                </tr>
                            )}
                            {vouchers.data.map((voucher) => (
                                <tr key={voucher.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link href={show.url(voucher.id)} className="text-primary underline">
                                            {voucher.voucher_number}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">{voucher.purchase_order?.po_number}</td>
                                    <td className="px-3 py-2">{voucher.supplier?.business_name}</td>
                                    <td className="px-3 py-2">{voucher.amount}</td>
                                    <td className="px-3 py-2">{voucher.status}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

AccountingVouchersIndex.layout = {
    breadcrumbs: [{ title: 'Disbursement vouchers', href: index() }],
};
