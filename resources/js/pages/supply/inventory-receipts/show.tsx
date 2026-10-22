import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { index } from '@/routes/supply/inventory-receipts';

type Line = {
    id: number;
    description: string;
    unit_of_measure: string | null;
    quantity: string;
    unit_price: string | null;
    total_price: string | null;
};

type Receipt = {
    id: number;
    received_date: string;
    reference_no: string | null;
    status: string;
    notes: string | null;
    purchase_order?: { po_number: string } | null;
    received_by?: { name: string } | null;
    items: Line[];
};

type Props = { receipt: Receipt };

export default function SupplyInventoryReceiptsShow({ receipt }: Props) {
    return (
        <>
            <Head title={`Receipt ${receipt.id}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Receipt for ${receipt.purchase_order?.po_number ?? ''}`}
                    description={`${receipt.received_date} · ${receipt.status}`}
                />
                <p className="text-sm">Reference: {receipt.reference_no ?? '—'}</p>
                <p className="text-sm">Received by: {receipt.received_by?.name ?? '—'}</p>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Description</th>
                                <th className="px-3 py-2">UOM</th>
                                <th className="px-3 py-2">Qty</th>
                                <th className="px-3 py-2">Unit price</th>
                                <th className="px-3 py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {receipt.items.map((item) => (
                                <tr key={item.id} className="border-t">
                                    <td className="px-3 py-2">{item.description}</td>
                                    <td className="px-3 py-2">{item.unit_of_measure}</td>
                                    <td className="px-3 py-2">{item.quantity}</td>
                                    <td className="px-3 py-2">{item.unit_price}</td>
                                    <td className="px-3 py-2">{item.total_price}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {receipt.notes && <p className="text-sm">{receipt.notes}</p>}
            </div>
        </>
    );
}

SupplyInventoryReceiptsShow.layout = {
    breadcrumbs: [{ title: 'Inventory receipts', href: index() }],
};
