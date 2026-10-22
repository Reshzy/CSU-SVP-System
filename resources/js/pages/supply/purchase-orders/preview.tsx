import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { batchCreate, index } from '@/routes/supply/purchase-orders';

type Winner = {
    supplier: { id: number; business_name: string };
    total_amount: number;
    item_count: number;
    items: { item_name: string | null; quantity: number; unit_price: string; total_price: string }[];
};

type Props = {
    purchaseRequest: { id: number; pr_number: string | null; pr_title: string | null };
    itemGroup: { id: number; group_code: string } | null;
    winners: Winner[];
};

export default function SupplyPurchaseOrdersPreview({ purchaseRequest, itemGroup, winners }: Props) {
    return (
        <>
            <Head title="PO preview" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Preview POs for ${purchaseRequest.pr_number}`}
                    description={`${winners.length} winning suppliers.`}
                />
                <div className="space-y-4">
                    {winners.map((winner) => (
                        <div key={winner.supplier.id} className="rounded-lg border p-4">
                            <h2 className="font-medium">{winner.supplier.business_name}</h2>
                            <p className="text-sm text-muted-foreground">
                                {winner.item_count} items ·{' '}
                                {Number(winner.total_amount).toLocaleString(undefined, { minimumFractionDigits: 2 })}
                            </p>
                            <ul className="mt-2 list-disc pl-5 text-sm">
                                {winner.items.map((item) => (
                                    <li key={`${item.item_name}-${item.quantity}`}>
                                        {item.item_name} × {item.quantity}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
                <Button asChild>
                    <Link
                        href={batchCreate.url(purchaseRequest.id, {
                            query: itemGroup ? { group: itemGroup.id } : {},
                        })}
                    >
                        Create batch purchase orders
                    </Link>
                </Button>
            </div>
        </>
    );
}

SupplyPurchaseOrdersPreview.layout = {
    breadcrumbs: [{ title: 'Purchase orders', href: index() }],
};
