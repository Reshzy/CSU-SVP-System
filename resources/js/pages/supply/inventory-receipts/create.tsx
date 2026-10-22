import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index, store } from '@/routes/supply/inventory-receipts';

type PurchaseOrder = {
    id: number;
    po_number: string;
};

type Props = { purchaseOrder: PurchaseOrder };

export default function SupplyInventoryReceiptsCreate({ purchaseOrder }: Props) {
    const form = useForm({
        received_date: '',
        reference_no: '',
        notes: '',
        items: [{ description: '', unit_of_measure: '', quantity: '1', unit_price: '' }],
    });

    return (
        <>
            <Head title="Record inventory receipt" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title={`Receipt for ${purchaseOrder.po_number}`} description="Lines are free text and are not tied to PO item IDs." />
                <form
                    className="max-w-xl space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(store.url(purchaseOrder.id));
                    }}
                >
                    <div className="space-y-2">
                        <Label>Received date</Label>
                        <Input
                            type="date"
                            value={form.data.received_date}
                            onChange={(event) => form.setData('received_date', event.target.value)}
                        />
                        <InputError message={form.errors.received_date} />
                    </div>
                    <div className="space-y-2">
                        <Label>Reference no.</Label>
                        <Input
                            value={form.data.reference_no}
                            onChange={(event) => form.setData('reference_no', event.target.value)}
                        />
                    </div>
                    {form.data.items.map((item, itemIndex) => (
                        <div key={itemIndex} className="space-y-2 rounded-lg border p-3">
                            <Label>Description</Label>
                            <Input
                                value={item.description}
                                onChange={(event) => {
                                    const items = [...form.data.items];
                                    items[itemIndex] = { ...items[itemIndex], description: event.target.value };
                                    form.setData('items', items);
                                }}
                            />
                            <Label>UOM</Label>
                            <Input
                                value={item.unit_of_measure}
                                onChange={(event) => {
                                    const items = [...form.data.items];
                                    items[itemIndex] = { ...items[itemIndex], unit_of_measure: event.target.value };
                                    form.setData('items', items);
                                }}
                            />
                            <Label>Quantity</Label>
                            <Input
                                value={item.quantity}
                                onChange={(event) => {
                                    const items = [...form.data.items];
                                    items[itemIndex] = { ...items[itemIndex], quantity: event.target.value };
                                    form.setData('items', items);
                                }}
                            />
                            <Label>Unit price</Label>
                            <Input
                                value={item.unit_price}
                                onChange={(event) => {
                                    const items = [...form.data.items];
                                    items[itemIndex] = { ...items[itemIndex], unit_price: event.target.value };
                                    form.setData('items', items);
                                }}
                            />
                        </div>
                    ))}
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            form.setData('items', [
                                ...form.data.items,
                                { description: '', unit_of_measure: '', quantity: '1', unit_price: '' },
                            ])
                        }
                    >
                        Add line
                    </Button>
                    <div className="space-y-2">
                        <Label>Notes</Label>
                        <Textarea value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Post receipt
                    </Button>
                </form>
            </div>
        </>
    );
}

SupplyInventoryReceiptsCreate.layout = {
    breadcrumbs: [{ title: 'Inventory receipts', href: index() }],
};
