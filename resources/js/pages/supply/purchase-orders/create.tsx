import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index, store } from '@/routes/supply/purchase-orders';

type Winner = {
    supplier: { id: number; business_name: string; address: string | null; tin: string | null };
    quotation: { id: number };
    total_amount: number;
    items: {
        item_name: string | null;
        quantity: number;
        unit_price: string;
        total_price: string;
        unit_of_measure: string | null;
    }[];
};

type Props = {
    purchaseRequest: { id: number; pr_number: string | null };
    itemGroup: { id: number } | null;
    winningSupplier: Winner | null;
    nextPoNumber: string;
    defaultTerms: string;
};

export default function SupplyPurchaseOrdersCreate({
    purchaseRequest,
    itemGroup,
    winningSupplier,
    nextPoNumber,
    defaultTerms,
}: Props) {
    const form = useForm({
        pr_item_group_id: itemGroup?.id ?? '',
        supplier_id: winningSupplier?.supplier.id ?? '',
        quotation_id: winningSupplier?.quotation.id ?? '',
        tin: winningSupplier?.supplier.tin ?? '',
        supplier_name_override: '',
        funds_cluster: '',
        funds_available: '',
        ors_burs_no: '',
        ors_burs_date: '',
        total_amount: String(winningSupplier?.total_amount ?? ''),
        delivery_address: winningSupplier?.supplier.address ?? '',
        delivery_date_required: '',
        terms_and_conditions: defaultTerms,
        special_instructions: '',
    });

    return (
        <>
            <Head title="Create purchase order" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title={`Create PO ${nextPoNumber}`} description={purchaseRequest.pr_number ?? ''} />

                {winningSupplier && (
                    <div className="rounded-lg border p-4 text-sm">
                        <p className="font-medium">{winningSupplier.supplier.business_name}</p>
                        <ul className="mt-2 list-disc pl-5">
                            {winningSupplier.items.map((item) => (
                                <li key={`${item.item_name}-${item.quantity}`}>
                                    {item.item_name} ({item.unit_of_measure}) × {item.quantity} @ {item.unit_price}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <form
                    className="max-w-xl space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(store.url(purchaseRequest.id));
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
                        <InputError message={form.errors.delivery_date_required} />
                    </div>
                    <div className="space-y-2">
                        <Label>TIN</Label>
                        <Input value={form.data.tin} onChange={(event) => form.setData('tin', event.target.value)} />
                    </div>
                    <div className="space-y-2">
                        <Label>Funds cluster</Label>
                        <Input
                            value={form.data.funds_cluster}
                            onChange={(event) => form.setData('funds_cluster', event.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>ORS/BURS no.</Label>
                        <Input
                            value={form.data.ors_burs_no}
                            onChange={(event) => form.setData('ors_burs_no', event.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Terms and conditions</Label>
                        <Textarea
                            value={form.data.terms_and_conditions}
                            onChange={(event) => form.setData('terms_and_conditions', event.target.value)}
                        />
                        <InputError message={form.errors.terms_and_conditions} />
                    </div>
                    <div className="space-y-2">
                        <Label>Special instructions</Label>
                        <Textarea
                            value={form.data.special_instructions}
                            onChange={(event) => form.setData('special_instructions', event.target.value)}
                        />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Create purchase order
                    </Button>
                </form>
            </div>
        </>
    );
}

SupplyPurchaseOrdersCreate.layout = {
    breadcrumbs: [{ title: 'Purchase orders', href: index() }],
};
