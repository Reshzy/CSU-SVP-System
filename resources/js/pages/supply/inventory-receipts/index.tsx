import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { index, show } from '@/routes/supply/inventory-receipts';
import type { Paginated } from '@/types';

type Receipt = {
    id: number;
    received_date: string;
    reference_no: string | null;
    status: string;
    purchase_order?: { po_number: string } | null;
    received_by?: { name: string } | null;
};

type Props = { receipts: Paginated<Receipt> };

export default function SupplyInventoryReceiptsIndex({ receipts }: Props) {
    return (
        <>
            <Head title="Inventory receipts" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Inventory receipts" description="Free-text receiving lines posted against purchase orders." />
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Date</th>
                                <th className="px-3 py-2">PO</th>
                                <th className="px-3 py-2">Reference</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Received by</th>
                            </tr>
                        </thead>
                        <tbody>
                            {receipts.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                                        No receipts recorded.
                                    </td>
                                </tr>
                            )}
                            {receipts.data.map((receipt) => (
                                <tr key={receipt.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link href={show.url(receipt.id)} className="text-primary underline">
                                            {receipt.received_date}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">{receipt.purchase_order?.po_number}</td>
                                    <td className="px-3 py-2">{receipt.reference_no}</td>
                                    <td className="px-3 py-2">{receipt.status}</td>
                                    <td className="px-3 py-2">{receipt.received_by?.name}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

SupplyInventoryReceiptsIndex.layout = {
    breadcrumbs: [{ title: 'Inventory receipts', href: index() }],
};
