import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Item = { id: number; item_name: string | null; estimated_unit_cost: string; quantity_requested: number };
type Supplier = { id: number; business_name: string };
type Quotation = { id: number; quotation_number: string; bac_status: string; total_amount: string; supplier?: { business_name: string } };

type Props = {
    purchaseRequest: {
        id: number;
        pr_number: string | null;
        quotable_items: Item[];
        quotations: Quotation[];
    };
    suppliers: Supplier[];
    statusLabel: string;
};

export default function BacQuotationsManage({ purchaseRequest, suppliers, statusLabel }: Props) {
    const form = useForm({
        supplier_id: String(suppliers[0]?.id ?? ''),
        items: purchaseRequest.quotable_items.map((item) => ({
            purchase_request_item_id: item.id,
            unit_price: '',
        })),
    });

    return (
        <>
            <Head title={`Quotations ${purchaseRequest.pr_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title={purchaseRequest.pr_number ?? 'Quotations'} description={statusLabel} />

                <form
                    className="space-y-4 rounded-lg border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            supplier_id: Number(data.supplier_id),
                            items: data.items.map((item) => ({
                                purchase_request_item_id: item.purchase_request_item_id,
                                unit_price: item.unit_price === '' ? null : Number(item.unit_price),
                            })),
                        })).post(`/bac/quotations/${purchaseRequest.id}`);
                    }}
                >
                    <div className="space-y-2">
                        <Label>Supplier</Label>
                        <select
                            className="border-input h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.supplier_id}
                            onChange={(event) => form.setData('supplier_id', event.target.value)}
                        >
                            {suppliers.map((supplier) => (
                                <option key={supplier.id} value={supplier.id}>
                                    {supplier.business_name}
                                </option>
                            ))}
                        </select>
                    </div>
                    {purchaseRequest.quotable_items.map((item, index) => (
                        <div key={item.id} className="grid gap-2 md:grid-cols-[1fr_8rem]">
                            <p className="text-sm">
                                {item.item_name} (ABC {item.estimated_unit_cost})
                            </p>
                            <Input
                                type="number"
                                step="0.01"
                                value={form.data.items[index]?.unit_price ?? ''}
                                onChange={(event) => {
                                    const items = [...form.data.items];
                                    items[index] = {
                                        ...items[index],
                                        unit_price: event.target.value,
                                    };
                                    form.setData('items', items);
                                }}
                            />
                        </div>
                    ))}
                    <Button type="submit">Store quotation</Button>
                </form>

                <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" onClick={() => form.post(`/bac/quotations/${purchaseRequest.id}/rfq/generate`)}>
                        Generate RFQ
                    </Button>
                    <Button type="button" variant="outline" onClick={() => (window.location.href = `/bac/quotations/${purchaseRequest.id}/aoq`)}>
                        Open AOQ
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Quotation</th>
                                <th className="px-3 py-2">Supplier</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {purchaseRequest.quotations.map((quotation) => (
                                <tr key={quotation.id} className="border-t">
                                    <td className="px-3 py-2">{quotation.quotation_number}</td>
                                    <td className="px-3 py-2">{quotation.supplier?.business_name}</td>
                                    <td className="px-3 py-2">{quotation.bac_status}</td>
                                    <td className="px-3 py-2">{quotation.total_amount}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
