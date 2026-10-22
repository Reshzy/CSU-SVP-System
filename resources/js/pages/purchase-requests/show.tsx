import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { PrTimeline, type TimelineActivity } from '@/components/pr-timeline';
import { Button } from '@/components/ui/button';
import { exportMethod, index } from '@/routes/purchase-requests';

type Item = {
    id: number;
    item_code: string | null;
    item_name: string | null;
    quantity_requested: number;
    estimated_unit_cost: string;
    estimated_total_cost: string;
    unit_of_measure: string | null;
    is_lot: boolean;
    lot_name: string | null;
    parent_lot_id: number | null;
};

type PurchaseRequest = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    purpose: string | null;
    status: string;
    estimated_total: string;
    funding_source: string | null;
    date_needed: string | null;
    department?: { name: string } | null;
    requester?: { name: string; email: string } | null;
    items: Item[];
    activities: TimelineActivity[];
};

type Props = {
    purchaseRequest: PurchaseRequest;
    statusLabel: string;
};

export default function PurchaseRequestShow({ purchaseRequest, statusLabel }: Props) {
    return (
        <>
            <Head title={purchaseRequest.pr_number ?? 'Purchase request'} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={purchaseRequest.pr_number ?? `PR #${purchaseRequest.id}`}
                        description={purchaseRequest.pr_title ?? 'Purchase request detail'}
                    />
                    <Button variant="outline" asChild>
                        <a href={exportMethod.url(purchaseRequest.id)}>Export Excel</a>
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-lg border p-4 text-sm">
                        <p>
                            <span className="text-muted-foreground">Status:</span>{' '}
                            <span className="capitalize" data-testid="pr-status">
                                {statusLabel}
                            </span>
                        </p>
                        <p>
                            <span className="text-muted-foreground">Department:</span>{' '}
                            {purchaseRequest.department?.name}
                        </p>
                        <p>
                            <span className="text-muted-foreground">Requester:</span>{' '}
                            {purchaseRequest.requester?.name}
                        </p>
                        <p>
                            <span className="text-muted-foreground">Funding:</span>{' '}
                            {purchaseRequest.funding_source ?? 'Not yet assigned'}
                        </p>
                        <p>
                            <span className="text-muted-foreground">Total:</span>{' '}
                            {Number(purchaseRequest.estimated_total).toLocaleString(undefined, {
                                minimumFractionDigits: 2,
                            })}
                        </p>
                    </div>
                    <div className="rounded-lg border p-4 text-sm">
                        <p className="font-medium">Purpose</p>
                        <p className="mt-2 whitespace-pre-wrap text-muted-foreground">
                            {purchaseRequest.purpose}
                        </p>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Item</th>
                                <th className="px-3 py-2">Qty</th>
                                <th className="px-3 py-2">Unit cost</th>
                                <th className="px-3 py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {purchaseRequest.items.map((item) => (
                                <tr key={item.id} className="border-t">
                                    <td className={`px-3 py-2 ${item.parent_lot_id ? 'pl-8' : ''}`}>
                                        {item.is_lot
                                            ? item.lot_name
                                            : `${item.item_code ?? ''} ${item.item_name ?? ''}`.trim()}
                                    </td>
                                    <td className="px-3 py-2">
                                        {item.quantity_requested} {item.unit_of_measure}
                                    </td>
                                    <td className="px-3 py-2">
                                        {Number(item.estimated_unit_cost).toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                        })}
                                    </td>
                                    <td className="px-3 py-2">
                                        {Number(item.estimated_total_cost).toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                        })}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="space-y-3">
                    <h2 className="text-base font-medium">Activity timeline</h2>
                    <PrTimeline activities={purchaseRequest.activities} />
                </div>
            </div>
        </>
    );
}

PurchaseRequestShow.layout = {
    breadcrumbs: [{ title: 'Purchase requests', href: index() }],
};
