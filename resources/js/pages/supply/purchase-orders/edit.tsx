import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index, update } from '@/routes/supply/purchase-orders';

type PurchaseOrder = {
    id: number;
    po_number: string;
    supplier_id: number;
    tin: string | null;
    supplier_name_override: string | null;
    funds_cluster: string | null;
    funds_available: string | null;
    ors_burs_no: string | null;
    ors_burs_date: string | null;
    total_amount: string | null;
    delivery_address: string;
    delivery_date_required: string;
    terms_and_conditions: string;
    special_instructions: string | null;
};

type Props = { purchaseOrder: PurchaseOrder };

export default function SupplyPurchaseOrdersEdit({ purchaseOrder }: Props) {
    const form = useForm({
        action: 'edit',
        supplier_id: purchaseOrder.supplier_id,
        tin: purchaseOrder.tin ?? '',
        supplier_name_override: purchaseOrder.supplier_name_override ?? '',
        funds_cluster: purchaseOrder.funds_cluster ?? '',
        funds_available: purchaseOrder.funds_available ?? '',
        ors_burs_no: purchaseOrder.ors_burs_no ?? '',
        ors_burs_date: purchaseOrder.ors_burs_date ?? '',
        total_amount: purchaseOrder.total_amount ?? '',
        delivery_address: purchaseOrder.delivery_address,
        delivery_date_required: purchaseOrder.delivery_date_required,
        terms_and_conditions: purchaseOrder.terms_and_conditions,
        special_instructions: purchaseOrder.special_instructions ?? '',
    });

    return (
        <>
            <Head title={`Edit ${purchaseOrder.po_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title={`Edit ${purchaseOrder.po_number}`} />
                <form
                    className="max-w-xl space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(update.url(purchaseOrder.id));
                    }}
                >
                    <div className="space-y-2">
                        <Label>Delivery address</Label>
                        <Textarea
                            value={form.data.delivery_address}
                            onChange={(event) => form.setData('delivery_address', event.target.value)}
                        />
                        <InputError message={form.errors.delivery_address} />
                    </div>
                    <div className="space-y-2">
                        <Label>Delivery date required</Label>
                        <Input
                            type="date"
                            value={form.data.delivery_date_required}
                            onChange={(event) => form.setData('delivery_date_required', event.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Terms and conditions</Label>
                        <Textarea
                            value={form.data.terms_and_conditions}
                            onChange={(event) => form.setData('terms_and_conditions', event.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Special instructions</Label>
                        <Textarea
                            value={form.data.special_instructions}
                            onChange={(event) => form.setData('special_instructions', event.target.value)}
                        />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Save
                    </Button>
                </form>
            </div>
        </>
    );
}

SupplyPurchaseOrdersEdit.layout = {
    breadcrumbs: [{ title: 'Purchase orders', href: index() }],
};
