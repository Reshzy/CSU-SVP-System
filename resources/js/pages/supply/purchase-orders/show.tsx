import { Head, Link, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { create as createReceipt } from '@/routes/supply/inventory-receipts';
import { edit, exportMethod, index, update } from '@/routes/supply/purchase-orders';

type Item = {
    id: number;
    quantity: number;
    unit_price: string;
    total_price: string;
    purchase_request_item?: { item_name: string | null; unit_of_measure: string | null } | null;
};

type PurchaseOrder = {
    id: number;
    po_number: string;
    status: string;
    total_amount: string | null;
    delivery_address: string;
    delivery_date_required: string;
    terms_and_conditions: string;
    supplier?: { business_name: string } | null;
    purchase_request?: { pr_number: string | null } | null;
    items: Item[];
};

type Props = { purchaseOrder: PurchaseOrder };

const actions = ['send_to_supplier', 'acknowledge', 'mark_delivered', 'complete'] as const;

export default function SupplyPurchaseOrdersShow({ purchaseOrder }: Props) {
    const form = useForm({
        action: 'send_to_supplier',
        inspection_file: null as File | null,
    });

    return (
        <>
            <Head title={purchaseOrder.po_number} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={purchaseOrder.po_number}
                    description={`${purchaseOrder.supplier?.business_name ?? ''} · ${purchaseOrder.status.replaceAll('_', ' ')}`}
                />

                <div className="flex flex-wrap gap-3 text-sm">
                    <Link href={edit.url(purchaseOrder.id)} className="text-primary underline">
                        Edit
                    </Link>
                    <a href={exportMethod.url(purchaseOrder.id)} className="text-primary underline">
                        Export Excel
                    </a>
                    <Link href={createReceipt.url(purchaseOrder.id)} className="text-primary underline">
                        Record receipt
                    </Link>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Item</th>
                                <th className="px-3 py-2">Qty</th>
                                <th className="px-3 py-2">Unit price</th>
                                <th className="px-3 py-2">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {purchaseOrder.items.map((item) => (
                                <tr key={item.id} className="border-t">
                                    <td className="px-3 py-2">{item.purchase_request_item?.item_name}</td>
                                    <td className="px-3 py-2">{item.quantity}</td>
                                    <td className="px-3 py-2">{item.unit_price}</td>
                                    <td className="px-3 py-2">{item.total_price}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <form
                    className="max-w-xl space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(update.url(purchaseOrder.id), { forceFormData: true });
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
                    {form.data.action === 'complete' && (
                        <div className="space-y-2">
                            <Label>Inspection report (optional)</Label>
                            <input
                                type="file"
                                onChange={(event) => form.setData('inspection_file', event.target.files?.[0] ?? null)}
                            />
                        </div>
                    )}
                    <Button type="submit" disabled={form.processing}>
                        Update PO
                    </Button>
                </form>
            </div>
        </>
    );
}

SupplyPurchaseOrdersShow.layout = {
    breadcrumbs: [{ title: 'Purchase orders', href: index() }],
};
