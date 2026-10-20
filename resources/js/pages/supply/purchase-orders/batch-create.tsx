import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { batchStore, index } from '@/routes/supply/purchase-orders';

type WinnerItem = {
    purchase_request_item_id: number;
    quotation_item_id: number;
    item_name: string | null;
    quantity: number;
    unit_price: string;
    total_price: string;
};

type Winner = {
    supplier: { id: number; business_name: string; address: string | null; tin: string | null };
    quotation: { id: number };
    total_amount: number;
    items: WinnerItem[];
};

type Props = {
    purchaseRequest: { id: number; pr_number: string | null };
    itemGroup: { id: number } | null;
    winners: Winner[];
    defaultTerms: string;
};

export default function SupplyPurchaseOrdersBatchCreate({
    purchaseRequest,
    itemGroup,
    winners,
    defaultTerms,
}: Props) {
    const form = useForm({
        pr_item_group_id: itemGroup?.id ?? '',
        purchase_orders: winners.map((winner) => ({
            supplier_id: winner.supplier.id,
            quotation_id: winner.quotation.id,
            tin: winner.supplier.tin ?? '',
            supplier_name_override: '',
            funds_cluster: '',
            funds_available: '',
            ors_burs_no: '',
            ors_burs_date: '',
            total_amount: String(winner.total_amount),
            delivery_address: winner.supplier.address ?? '',
            delivery_date_required: '',
            terms_and_conditions: defaultTerms,
            special_instructions: '',
            items: winner.items.map((item) => ({
                purchase_request_item_id: item.purchase_request_item_id,
                quotation_item_id: item.quotation_item_id,
                quantity: item.quantity,
                unit_price: item.unit_price,
                total_price: item.total_price,
            })),
        })),
    });

    return (
        <>
            <Head title="Batch create purchase orders" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Batch create purchase orders" description={purchaseRequest.pr_number ?? ''} />
                <form
                    className="space-y-8"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(batchStore.url(purchaseRequest.id));
                    }}
                >
                    {winners.map((winner, index) => (
                        <div key={winner.supplier.id} className="max-w-xl space-y-4 rounded-lg border p-4">
                            <h2 className="font-medium">{winner.supplier.business_name}</h2>
                            <ul className="list-disc pl-5 text-sm">
                                {winner.items.map((item) => (
                                    <li key={item.quotation_item_id}>
                                        {item.item_name} × {item.quantity}
                                    </li>
                                ))}
                            </ul>
                            <div className="space-y-2">
                                <Label>Delivery address</Label>
                                <Textarea
                                    value={form.data.purchase_orders[index].delivery_address}
                                    onChange={(event) =>
                                        form.setData(
                                            `purchase_orders.${index}.delivery_address`,
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Delivery date required</Label>
                                <Input
                                    type="date"
                                    value={form.data.purchase_orders[index].delivery_date_required}
                                    onChange={(event) =>
                                        form.setData(
                                            `purchase_orders.${index}.delivery_date_required`,
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Terms and conditions</Label>
                                <Textarea
                                    value={form.data.purchase_orders[index].terms_and_conditions}
                                    onChange={(event) =>
                                        form.setData(
                                            `purchase_orders.${index}.terms_and_conditions`,
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                        </div>
                    ))}
                    <InputError message={form.errors.purchase_orders} />
                    <Button type="submit" disabled={form.processing}>
                        Create purchase orders
                    </Button>
                </form>
            </div>
        </>
    );
}

SupplyPurchaseOrdersBatchCreate.layout = {
    breadcrumbs: [{ title: 'Purchase orders', href: index() }],
};
