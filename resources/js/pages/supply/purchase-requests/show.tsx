import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    exportMethod,
    index,
    status,
} from '@/routes/supply/purchase-requests';
import {
    destroy as destroyLot,
    store as storeLot,
    update as updateLot,
} from '@/routes/supply/purchase-requests/lots';

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

const statusActions = [
    'start_review',
    'activate',
    'return',
    'reject',
    'cancel',
] as const;

export default function SupplyPurchaseRequestShow({
    purchaseRequest,
    statusLabel,
    canManageLots,
}: Props) {
    const statusForm = useForm({
        action: purchaseRequest.status === 'submitted' ? 'start_review' : 'activate',
        remarks: '',
        rejection_reason: '',
    });

    const lotForm = useForm({
        lot_name: '',
        item_ids: [] as number[],
    });

    const [editingLotId, setEditingLotId] = useState<number | null>(null);

    const lots = purchaseRequest.items.filter((item) => item.is_lot);
    const standalones = purchaseRequest.items.filter(
        (item) => !item.is_lot && !item.parent_lot_id,
    );
    const editingLot = lots.find((lot) => lot.id === editingLotId);
    const selectableForEdit = purchaseRequest.items.filter(
        (item) =>
            !item.is_lot &&
            (item.parent_lot_id === null || item.parent_lot_id === editingLotId),
    );

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
                            statusForm.post(status.url(purchaseRequest.id));
                        }}
                    >
                        <h2 className="font-medium">Status actions</h2>
                        <div className="flex flex-wrap gap-2">
                            {statusActions.map((action) => (
                                <Button
                                    key={action}
                                    type="button"
                                    variant={
                                        statusForm.data.action === action
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() =>
                                        statusForm.setData('action', action)
                                    }
                                >
                                    {action === 'reject'
                                        ? 'Defer'
                                        : action.replaceAll('_', ' ')}
                                </Button>
                            ))}
                        </div>
                        <div className="space-y-2">
                            <Label>Remarks</Label>
                            <Textarea
                                value={statusForm.data.remarks}
                                onChange={(event) =>
                                    statusForm.setData(
                                        'remarks',
                                        event.target.value,
                                    )
                                }
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
                                lotForm.post(storeLot.url(purchaseRequest.id));
                            }}
                        >
                            <h2 className="font-medium">Create lot</h2>
                            <div className="space-y-2">
                                <Label>Lot name</Label>
                                <Input
                                    value={lotForm.data.lot_name}
                                    onChange={(event) =>
                                        lotForm.setData(
                                            'lot_name',
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Standalone items (min 2)</Label>
                                {standalones.map((item) => (
                                    <label
                                        key={item.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={lotForm.data.item_ids.includes(
                                                item.id,
                                            )}
                                            onChange={(event) => {
                                                const next = event.target.checked
                                                    ? [
                                                          ...lotForm.data.item_ids,
                                                          item.id,
                                                      ]
                                                    : lotForm.data.item_ids.filter(
                                                          (id) => id !== item.id,
                                                      );
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

                {canManageLots && lots.length > 0 && (
                    <div className="space-y-3 rounded-lg border p-4">
                        <h2 className="font-medium">Existing lots</h2>
                        {lots.map((lot) => (
                            <div
                                key={lot.id}
                                className="flex flex-wrap items-center justify-between gap-2 border-b py-2 last:border-b-0"
                            >
                                <p className="text-sm">{lot.lot_name}</p>
                                <div className="flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        type="button"
                                        onClick={() => {
                                            setEditingLotId(lot.id);
                                            lotForm.setData({
                                                lot_name: lot.lot_name ?? '',
                                                item_ids: purchaseRequest.items
                                                    .filter(
                                                        (item) =>
                                                            item.parent_lot_id ===
                                                            lot.id,
                                                    )
                                                    .map((item) => item.id),
                                            });
                                        }}
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        type="button"
                                        onClick={() =>
                                            router.delete(
                                                destroyLot.url({
                                                    purchaseRequest:
                                                        purchaseRequest.id,
                                                    lot: lot.id,
                                                }),
                                            )
                                        }
                                    >
                                        Remove
                                    </Button>
                                </div>
                            </div>
                        ))}

                        {editingLot && (
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    lotForm.put(
                                        updateLot.url({
                                            purchaseRequest: purchaseRequest.id,
                                            lot: editingLot.id,
                                        }),
                                    );
                                }}
                            >
                                <Label>Update {editingLot.lot_name}</Label>
                                <Input
                                    value={lotForm.data.lot_name}
                                    onChange={(event) =>
                                        lotForm.setData(
                                            'lot_name',
                                            event.target.value,
                                        )
                                    }
                                />
                                {selectableForEdit.map((item) => (
                                    <label
                                        key={item.id}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={lotForm.data.item_ids.includes(
                                                item.id,
                                            )}
                                            onChange={(event) => {
                                                const next = event.target.checked
                                                    ? [
                                                          ...lotForm.data.item_ids,
                                                          item.id,
                                                      ]
                                                    : lotForm.data.item_ids.filter(
                                                          (id) => id !== item.id,
                                                      );
                                                lotForm.setData('item_ids', next);
                                            }}
                                        />
                                        {item.item_code} {item.item_name}
                                    </label>
                                ))}
                                <div className="flex gap-2">
                                    <Button type="submit">Save lot</Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setEditingLotId(null)}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        )}
                    </div>
                )}

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
                                    <td
                                        className={`px-3 py-2 ${item.parent_lot_id ? 'pl-8' : ''}`}
                                    >
                                        {item.is_lot
                                            ? item.lot_name
                                            : `${item.item_code} ${item.item_name}`}
                                    </td>
                                    <td className="px-3 py-2">
                                        {item.quantity_requested}
                                    </td>
                                    <td className="px-3 py-2">
                                        {item.estimated_unit_cost}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Button variant="outline" asChild>
                    <a href={exportMethod.url(purchaseRequest.id)}>
                        Export Excel
                    </a>
                </Button>
            </div>
        </>
    );
}

SupplyPurchaseRequestShow.layout = {
    breadcrumbs: [{ title: 'Supply queue', href: index() }],
};
