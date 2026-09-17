import { Head, router, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index, show } from '@/routes/supply/purchase-requests';

type Item = {
    id: number;
    item_name: string | null;
    item_code: string | null;
    is_lot: boolean;
    lot_name: string | null;
    parent_lot_id: number | null;
    quantity_requested: number;
    estimated_unit_cost: string;
};

type PurchaseRequest = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    purpose: string | null;
    status: string;
    items: Item[];
};

type Props = {
    purchaseRequest: PurchaseRequest;
    statusLabel: string;
    canManageLots: boolean;
};

export default function SupplyPurchaseRequestShow({
    purchaseRequest,
    statusLabel,
    canManageLots,
}: Props) {
    const statusForm = useForm({
        action: 'activate',
        remarks: '',
        rejection_reason: '',
    });

    const lotForm = useForm({
        lot_name: '',
        item_ids: [] as number[],
    });

    const standalones = purchaseRequest.items.filter((item) => !item.is_lot && !item.parent_lot_id);

    return (
        <>
            <Head title={purchaseRequest.pr_number ?? 'Supply PR'} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={purchaseRequest.pr_number ?? `PR #${purchaseRequest.id}`}
                    description={`${purchaseRequest.pr_title ?? ''} · ${statusLabel}`}
                />

                <div className="grid gap-4 lg:grid-cols-2">
                    <form
                        className="space-y-3 rounded-lg border p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            statusForm.post(`/supply/purchase-requests/${purchaseRequest.id}/status`);
                        }}
                    >
                        <h2 className="font-medium">Status actions</h2>
                        <div className="flex flex-wrap gap-2">
                            {(['activate', 'return', 'reject', 'cancel'] as const).map((action) => (
                                <Button
                                    key={action}
                                    type="button"
                                    variant={statusForm.data.action === action ? 'default' : 'outline'}
                                    onClick={() => statusForm.setData('action', action)}
                                >
                                    {action === 'reject' ? 'Defer' : action.replaceAll('_', ' ')}
                                </Button>
                            ))}
                        </div>
                        <div className="space-y-2">
                            <Label>Remarks</Label>
                            <Textarea
                                value={statusForm.data.remarks}
                                onChange={(event) => statusForm.setData('remarks', event.target.value)}
                            />
                            <InputError message={statusForm.errors.remarks} />
                        </div>
                        <Button type="submit" disabled={statusForm.processing}>
                            Apply
                        </Button>
                    </form>

                    {canManageLots && (
                        <form
                            className="space-y-3 rounded-lg border p-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                lotForm.post(`/supply/purchase-requests/${purchaseRequest.id}/lots`);
                            }}
                        >
                            <h2 className="font-medium">Create lot</h2>
                            <div className="space-y-2">
                                <Label>Lot name</Label>
                                <Input
                                    value={lotForm.data.lot_name}
                                    onChange={(event) => lotForm.setData('lot_name', event.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Standalone items (min 2)</Label>
                                {standalones.map((item) => (
                                    <label key={item.id} className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={lotForm.data.item_ids.includes(item.id)}
                                            onChange={(event) => {
                                                const next = event.target.checked
                                                    ? [...lotForm.data.item_ids, item.id]
                                                    : lotForm.data.item_ids.filter((id) => id !== item.id);
                                                lotForm.setData('item_ids', next);
                                            }}
                                        />
                                        {item.item_code} {item.item_name}
                                    </label>
                                ))}
                                <InputError message={lotForm.errors.item_ids} />
                            </div>
                            <Button type="submit" disabled={lotForm.processing}>
                                Create lot
                            </Button>
                        </form>
                    )}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Item</th>
                                <th className="px-3 py-2">Qty</th>
                                <th className="px-3 py-2">Unit cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            {purchaseRequest.items.map((item) => (
                                <tr key={item.id} className="border-t">
                                    <td className={`px-3 py-2 ${item.parent_lot_id ? 'pl-8' : ''}`}>
                                        {item.is_lot ? item.lot_name : `${item.item_code} ${item.item_name}`}
                                    </td>
                                    <td className="px-3 py-2">{item.quantity_requested}</td>
                                    <td className="px-3 py-2">{item.estimated_unit_cost}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Button
                    variant="outline"
                    onClick={() => router.get(`/supply/purchase-requests/${purchaseRequest.id}/export`)}
                >
                    Export Excel
                </Button>
            </div>
        </>
    );
}

SupplyPurchaseRequestShow.layout = {
    breadcrumbs: [{ title: 'Supply queue', href: index() }],
};
