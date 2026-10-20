import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    download as downloadGroupRfq,
    generate as generateGroupRfq,
    regenerate as regenerateGroupRfq,
} from '@/routes/bac/item-groups/rfq';
import {
    aoq,
    groupQuotations,
    index,
    manage,
    store,
} from '@/routes/bac/quotations';

type Item = {
    id: number;
    item_name: string | null;
    estimated_unit_cost: string;
    quantity_requested: number;
};

type Quotation = {
    id: number;
    quotation_number: string;
    bac_status: string;
    total_amount: string;
    supplier?: { business_name: string } | null;
};

type ItemGroup = {
    id: number;
    group_code: string;
    group_name: string;
    rfq_generation: { id: number; rfq_number: string } | null;
    quotations: Quotation[];
};

type Supplier = { id: number; business_name: string };

type Props = {
    purchaseRequest: {
        id: number;
        pr_number: string | null;
        item_groups: { id: number; group_code: string }[];
    };
    itemGroup: ItemGroup;
    quotableItems: Item[];
    suppliers: Supplier[];
    statusLabel: string;
};

export default function BacGroupQuotations({
    purchaseRequest,
    itemGroup,
    quotableItems,
    suppliers,
    statusLabel,
}: Props) {
    const storeForm = useForm({
        supplier_id: String(suppliers[0]?.id ?? ''),
        pr_item_group_id: itemGroup.id,
        items: quotableItems.map((item) => ({
            purchase_request_item_id: item.id,
            unit_price: '',
        })),
    });

    const hasRfq = itemGroup.rfq_generation !== null;

    const otherGroups = useMemo(
        () =>
            purchaseRequest.item_groups.filter(
                (group) => group.id !== itemGroup.id,
            ),
        [purchaseRequest.item_groups, itemGroup.id],
    );

    return (
        <>
            <Head
                title={`${itemGroup.group_code} quotations ${purchaseRequest.pr_number}`}
            />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`${itemGroup.group_code} · ${itemGroup.group_name}`}
                    description={statusLabel}
                />

                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href={manage.url(purchaseRequest.id)}>
                            Back to quotations
                        </Link>
                    </Button>
                    {otherGroups.map((group) => (
                        <Button key={group.id} variant="ghost" asChild>
                            <Link
                                href={groupQuotations.url({
                                    purchaseRequest: purchaseRequest.id,
                                    itemGroup: group.id,
                                })}
                            >
                                {group.group_code}
                            </Link>
                        </Button>
                    ))}
                </div>

                <form
                    className="space-y-4 rounded-lg border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        storeForm
                            .transform((data) => ({
                                supplier_id: Number(data.supplier_id),
                                pr_item_group_id: itemGroup.id,
                                items: data.items.map((item) => ({
                                    purchase_request_item_id:
                                        item.purchase_request_item_id,
                                    unit_price:
                                        item.unit_price === ''
                                            ? null
                                            : Number(item.unit_price),
                                })),
                            }))
                            .post(store.url(purchaseRequest.id));
                    }}
                >
                    <h2 className="font-medium">
                        Store quotation for {itemGroup.group_code}
                    </h2>
                    <div className="space-y-2">
                        <Label>Supplier</Label>
                        <select
                            className="border-input h-9 w-full rounded-md border px-3 text-sm"
                            value={storeForm.data.supplier_id}
                            onChange={(event) =>
                                storeForm.setData(
                                    'supplier_id',
                                    event.target.value,
                                )
                            }
                        >
                            {suppliers.map((supplier) => (
                                <option key={supplier.id} value={supplier.id}>
                                    {supplier.business_name}
                                </option>
                            ))}
                        </select>
                        <InputError message={storeForm.errors.supplier_id} />
                    </div>
                    {quotableItems.map((item, index) => (
                        <div
                            key={item.id}
                            className="grid gap-2 md:grid-cols-[1fr_8rem]"
                        >
                            <p className="text-sm">
                                {item.item_name} (ABC {item.estimated_unit_cost}
                                )
                            </p>
                            <Input
                                type="number"
                                step="0.01"
                                value={
                                    storeForm.data.items[index]?.unit_price ??
                                    ''
                                }
                                onChange={(event) => {
                                    const items = [...storeForm.data.items];
                                    items[index] = {
                                        ...items[index],
                                        unit_price: event.target.value,
                                    };
                                    storeForm.setData('items', items);
                                }}
                            />
                        </div>
                    ))}
                    <InputError message={storeForm.errors.items} />
                    <Button type="submit" disabled={storeForm.processing}>
                        Store quotation
                    </Button>
                </form>

                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() =>
                            router.post(generateGroupRfq.url(itemGroup.id))
                        }
                    >
                        Generate group RFQ
                    </Button>
                    {hasRfq && (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        regenerateGroupRfq.url(itemGroup.id),
                                    )
                                }
                            >
                                Regenerate group RFQ
                            </Button>
                            <Button variant="outline" asChild>
                                <a href={downloadGroupRfq.url(itemGroup.id)}>
                                    Download group RFQ
                                </a>
                            </Button>
                        </>
                    )}
                    <Button variant="outline" asChild>
                        <Link href={aoq.url(purchaseRequest.id)}>Open AOQ</Link>
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">
                                    Quotation
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Supplier
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Status
                                </th>
                                <th className="px-3 py-2 font-medium">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {itemGroup.quotations.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No quotations stored for this group
                                        yet.
                                    </td>
                                </tr>
                            )}
                            {itemGroup.quotations.map((quotation) => (
                                <tr key={quotation.id} className="border-t">
                                    <td className="px-3 py-2">
                                        {quotation.quotation_number}
                                    </td>
                                    <td className="px-3 py-2">
                                        {quotation.supplier?.business_name}
                                    </td>
                                    <td className="px-3 py-2 capitalize">
                                        {quotation.bac_status.replaceAll(
                                            '_',
                                            ' ',
                                        )}
                                    </td>
                                    <td className="px-3 py-2">
                                        {quotation.total_amount}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

BacGroupQuotations.layout = {
    breadcrumbs: [{ title: 'BAC quotations', href: index() }],
};
