import { Head, Link, router, useForm, useHttp } from '@inertiajs/react';
import { flexRender } from '@tanstack/react-table/flex-render';
import {
    getCoreRowModel,
    legacyCreateColumnHelper,
    useLegacyTable,
} from '@tanstack/react-table/legacy';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { create as createGroups, edit as editGroups } from '@/routes/bac/item-groups';
import {
    download as downloadGroupRfq,
    generate as generateGroupRfq,
    regenerate as regenerateGroupRfq,
} from '@/routes/bac/item-groups/rfq';
import { markFailed } from '@/routes/bac/pr-items';
import { withdraw, withdrawalPreview } from '@/routes/bac/quotation-items';
import {
    aoq,
    createReplacementPr,
    evaluate,
    finalize,
    groupQuotations,
    index,
    store,
} from '@/routes/bac/quotations';
import {
    download as downloadRfq,
    generate as generateRfq,
    regenerate as regenerateRfq,
} from '@/routes/bac/quotations/rfq';

type QuoteLine = {
    id: number;
    is_winner: boolean;
    is_withdrawn: boolean;
    is_within_abc: boolean;
    unit_price: string | null;
    disqualification_reason: string | null;
};

type Item = {
    id: number;
    item_name: string | null;
    estimated_unit_cost: string;
    quantity_requested: number;
    pr_item_group_id: number | null;
    procurement_status: string;
    quotation_items: QuoteLine[];
};

type DocumentRow = {
    id: number;
    document_type: string;
};

type Quotation = {
    id: number;
    quotation_number: string;
    bac_status: string;
    total_amount: string;
    technical_score: string | null;
    financial_score: string | null;
    is_winning_bid: boolean;
    pr_item_group_id: number | null;
    supplier?: { business_name: string } | null;
};

type ItemGroup = {
    id: number;
    group_code: string;
    group_name: string;
    status: string;
    rfq_generation: { id: number } | null;
    aoq_generation: { id: number } | null;
};

type FailedItem = {
    id: number;
    item_name: string | null;
};

type Supplier = { id: number; business_name: string };

type Props = {
    purchaseRequest: {
        id: number;
        pr_number: string | null;
        status: string;
        documents: DocumentRow[];
        quotable_items: Item[];
        quotations: Quotation[];
        item_groups: ItemGroup[];
    };
    suppliers: Supplier[];
    statusLabel: string;
    canManageGroups: boolean;
    groupsLocked: boolean;
    failedItemsNeedingRePr: FailedItem[];
};

type WithdrawalPreview = {
    would_cause_failure: boolean;
    next_bidder: {
        supplier: string;
        unit_price: string | number | null;
        total_price: string | number | null;
    } | null;
};

const columnHelper = legacyCreateColumnHelper<Quotation>();

export default function BacQuotationsManage({
    purchaseRequest,
    suppliers,
    statusLabel,
    canManageGroups,
    groupsLocked,
    failedItemsNeedingRePr,
}: Props) {
    const groups = purchaseRequest.item_groups ?? [];
    const hasGroups = groups.length > 0;
    const hasRfq = purchaseRequest.documents.some(
        (document) => document.document_type === 'bac_rfq',
    );
    const [evaluatingId, setEvaluatingId] = useState<number | null>(null);
    const [selectedGroupId, setSelectedGroupId] = useState(
        String(groups[0]?.id ?? ''),
    );
    const [withdrawingId, setWithdrawingId] = useState<number | null>(null);
    const [failingItemId, setFailingItemId] = useState<number | null>(null);
    const [preview, setPreview] = useState<WithdrawalPreview | null>(null);
    const { submit } = useHttp();

    const scopedItems = hasGroups
        ? purchaseRequest.quotable_items.filter(
              (item) => String(item.pr_item_group_id) === selectedGroupId,
          )
        : purchaseRequest.quotable_items;

    const storeForm = useForm({
        supplier_id: String(suppliers[0]?.id ?? ''),
        items: scopedItems.map((item) => ({
            purchase_request_item_id: item.id,
            unit_price: '',
        })),
    });

    const withdrawForm = useForm({
        withdrawal_reason: '',
    });

    const failForm = useForm({
        failure_reason: '',
    });

    const evaluateForm = useForm({
        technical_score: '80',
        financial_score: '80',
        bac_status: 'compliant',
    });

    const columns = useMemo(
        () => [
            columnHelper.accessor('quotation_number', { header: 'Quotation' }),
            columnHelper.accessor(
                (row) => row.supplier?.business_name ?? '',
                { id: 'supplier', header: 'Supplier' },
            ),
            columnHelper.accessor('bac_status', {
                header: 'Status',
                cell: (info) => (
                    <span className="capitalize">
                        {info.getValue().replaceAll('_', ' ')}
                    </span>
                ),
            }),
            columnHelper.accessor('total_amount', { header: 'Total' }),
            columnHelper.display({
                id: 'actions',
                header: 'Actions',
                cell: (info) => (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            onClick={() =>
                                setEvaluatingId(info.row.original.id)
                            }
                        >
                            Evaluate
                        </Button>
                        {!hasGroups &&
                            purchaseRequest.status !== 'bac_approved' && (
                            <Button
                                size="sm"
                                type="button"
                                onClick={() =>
                                    router.post(
                                        finalize.url(purchaseRequest.id),
                                        {
                                            quotation_id: info.row.original.id,
                                        },
                                    )
                                }
                            >
                                Finalize
                            </Button>
                        )}
                    </div>
                ),
            }),
        ],
        [hasGroups, purchaseRequest.id, purchaseRequest.status],
    );

    const table = useLegacyTable({
        data: purchaseRequest.quotations,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    const syncStoreItems = (groupId: string) => {
        const items = (
            hasGroups
                ? purchaseRequest.quotable_items.filter(
                      (item) => String(item.pr_item_group_id) === groupId,
                  )
                : purchaseRequest.quotable_items
        ).map((item) => ({
            purchase_request_item_id: item.id,
            unit_price: '',
        }));
        storeForm.setData('items', items);
    };

    return (
        <>
            <Head title={`Quotations ${purchaseRequest.pr_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={purchaseRequest.pr_number ?? 'Quotations'}
                    description={statusLabel}
                />

                {canManageGroups && (!hasGroups || !groupsLocked) && (
                    <div className="flex flex-wrap gap-2">
                        {hasGroups ? (
                            <Button variant="outline" asChild>
                                <Link href={editGroups.url(purchaseRequest.id)}>
                                    Edit groups
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" asChild>
                                <Link
                                    href={createGroups.url(purchaseRequest.id)}
                                >
                                    Split into groups
                                </Link>
                            </Button>
                        )}
                    </div>
                )}

                {hasGroups && (
                    <div className="space-y-3 rounded-lg border p-4">
                        <h2 className="font-medium">Item groups</h2>
                        <div className="flex flex-wrap gap-2">
                            {groups.map((group) => (
                                <div
                                    key={group.id}
                                    className="flex flex-wrap items-center gap-2 rounded-md border px-3 py-2 text-sm"
                                >
                                    <span className="font-medium">
                                        {group.group_code} {group.group_name}
                                    </span>
                                    <Button size="sm" variant="outline" asChild>
                                        <Link
                                            href={groupQuotations.url({
                                                purchaseRequest:
                                                    purchaseRequest.id,
                                                itemGroup: group.id,
                                            })}
                                        >
                                            Quotations
                                        </Link>
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        type="button"
                                        onClick={() =>
                                            router.post(
                                                generateGroupRfq.url(group.id),
                                            )
                                        }
                                    >
                                        Generate RFQ
                                    </Button>
                                    {group.rfq_generation && (
                                        <>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        regenerateGroupRfq.url(
                                                            group.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                Regenerate RFQ
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <a
                                                    href={downloadGroupRfq.url(
                                                        group.id,
                                                    )}
                                                >
                                                    Download RFQ
                                                </a>
                                            </Button>
                                        </>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                <form
                    className="space-y-4 rounded-lg border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        storeForm.transform((data) => ({
                            supplier_id: Number(data.supplier_id),
                            pr_item_group_id: hasGroups
                                ? Number(selectedGroupId)
                                : null,
                            items: data.items.map((item) => ({
                                purchase_request_item_id:
                                    item.purchase_request_item_id,
                                unit_price:
                                    item.unit_price === ''
                                        ? null
                                        : Number(item.unit_price),
                            })),
                        }));
                        storeForm.post(store.url(purchaseRequest.id));
                    }}
                >
                    <h2 className="font-medium">Store quotation</h2>
                    {hasGroups && (
                        <div className="space-y-2">
                            <Label>Group</Label>
                            <select
                                className="border-input h-9 w-full rounded-md border px-3 text-sm"
                                value={selectedGroupId}
                                onChange={(event) => {
                                    setSelectedGroupId(event.target.value);
                                    syncStoreItems(event.target.value);
                                }}
                            >
                                {groups.map((group) => (
                                    <option key={group.id} value={group.id}>
                                        {group.group_code} {group.group_name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}
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
                    {scopedItems.map((item, index) => (
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
                    <InputError
                        message={storeForm.errors.procurement_method}
                    />
                    <Button type="submit" disabled={storeForm.processing}>
                        Store quotation
                    </Button>
                </form>

                {!hasGroups && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.post(generateRfq.url(purchaseRequest.id))
                            }
                        >
                            Generate RFQ
                        </Button>
                        {hasRfq && (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            regenerateRfq.url(
                                                purchaseRequest.id,
                                            ),
                                        )
                                    }
                                >
                                    Regenerate RFQ
                                </Button>
                                <Button variant="outline" asChild>
                                    <a
                                        href={downloadRfq.url(
                                            purchaseRequest.id,
                                        )}
                                    >
                                        Download RFQ
                                    </a>
                                </Button>
                            </>
                        )}
                        <Button variant="outline" asChild>
                            <Link href={aoq.url(purchaseRequest.id)}>
                                Open AOQ
                            </Link>
                        </Button>
                    </div>
                )}

                {hasGroups && (
                    <Button variant="outline" asChild>
                        <Link href={aoq.url(purchaseRequest.id)}>Open AOQ</Link>
                    </Button>
                )}

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            {table.getHeaderGroups().map((headerGroup) => (
                                <tr key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <th
                                            key={header.id}
                                            className="px-3 py-2 font-medium"
                                        >
                                            {flexRender(
                                                header.column.columnDef.header,
                                                header.getContext(),
                                            )}
                                        </th>
                                    ))}
                                </tr>
                            ))}
                        </thead>
                        <tbody>
                            {table.getRowModel().rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={columns.length}
                                        className="px-3 py-8 text-center text-muted-foreground"
                                    >
                                        No quotations stored yet.
                                    </td>
                                </tr>
                            )}
                            {table.getRowModel().rows.map((row) => (
                                <tr key={row.id} className="border-t">
                                    {row.getVisibleCells().map((cell) => (
                                        <td key={cell.id} className="px-3 py-2">
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="space-y-3 rounded-lg border p-4">
                    <h2 className="font-medium">Winners, withdrawal, failed</h2>
                    {purchaseRequest.quotable_items.map((item) => {
                        const winning = item.quotation_items.find(
                            (line) => line.is_winner && !line.is_withdrawn,
                        );
                        const hasEligible = item.quotation_items.some(
                            (line) =>
                                !line.is_withdrawn &&
                                line.unit_price !== null &&
                                line.is_within_abc &&
                                !line.disqualification_reason,
                        );

                        return (
                            <div
                                key={item.id}
                                className="flex flex-wrap items-center justify-between gap-2 border-b py-2 last:border-b-0"
                            >
                                <p className="text-sm">
                                    {item.item_name}{' '}
                                    <span className="text-muted-foreground">
                                        {item.procurement_status}
                                    </span>
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {winning && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            type="button"
                                            onClick={() => {
                                                setWithdrawingId(winning.id);
                                                setPreview(null);
                                                void submit(
                                                    withdrawalPreview(
                                                        winning.id,
                                                    ),
                                                ).then((response) => {
                                                    setPreview(
                                                        response as WithdrawalPreview,
                                                    );
                                                });
                                            }}
                                        >
                                            Withdraw winner
                                        </Button>
                                    )}
                                    {!hasEligible &&
                                        item.procurement_status !==
                                            'failed' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                type="button"
                                                onClick={() =>
                                                    setFailingItemId(item.id)
                                                }
                                            >
                                                Mark failed
                                            </Button>
                                        )}
                                </div>
                            </div>
                        );
                    })}

                    {withdrawingId !== null && (
                        <form
                            className="grid max-w-xl gap-3 rounded-lg border p-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                withdrawForm.post(withdraw.url(withdrawingId), {
                                    onSuccess: () => setWithdrawingId(null),
                                });
                            }}
                        >
                            {preview?.would_cause_failure && (
                                <p className="text-sm text-destructive">
                                    This withdrawal has no successor and will
                                    mark the item as failed.
                                </p>
                            )}
                            {preview?.next_bidder && (
                                <p className="text-sm text-muted-foreground">
                                    Next bidder:{' '}
                                    {preview.next_bidder.supplier} at{' '}
                                    {preview.next_bidder.unit_price}
                                </p>
                            )}
                            <Label>Withdrawal reason</Label>
                            <Textarea
                                value={withdrawForm.data.withdrawal_reason}
                                onChange={(event) =>
                                    withdrawForm.setData(
                                        'withdrawal_reason',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={withdrawForm.errors.withdrawal_reason}
                            />
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={withdrawForm.processing}
                                >
                                    Withdraw
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => {
                                        setWithdrawingId(null);
                                        setPreview(null);
                                    }}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    )}

                    {failingItemId !== null && (
                        <form
                            className="grid max-w-xl gap-3 rounded-lg border p-4"
                            onSubmit={(event) => {
                                event.preventDefault();
                                failForm.post(markFailed.url(failingItemId), {
                                    onSuccess: () => setFailingItemId(null),
                                });
                            }}
                        >
                            <Label>Failure reason</Label>
                            <Textarea
                                value={failForm.data.failure_reason}
                                onChange={(event) =>
                                    failForm.setData(
                                        'failure_reason',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError
                                message={failForm.errors.failure_reason}
                            />
                            <div className="flex gap-2">
                                <Button
                                    type="submit"
                                    disabled={failForm.processing}
                                >
                                    Mark failed
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setFailingItemId(null)}
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    )}

                    {failedItemsNeedingRePr.length > 0 && (
                        <Button
                            type="button"
                            onClick={() =>
                                router.post(
                                    createReplacementPr.url(purchaseRequest.id),
                                )
                            }
                        >
                            Create replacement PR (
                            {failedItemsNeedingRePr.length} item
                            {failedItemsNeedingRePr.length === 1 ? '' : 's'})
                        </Button>
                    )}
                </div>

                {evaluatingId !== null && (
                    <form
                        className="grid max-w-xl gap-3 rounded-lg border p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            evaluateForm.post(evaluate.url(evaluatingId));
                        }}
                    >
                        <h2 className="font-medium">Evaluate quotation</h2>
                        <div className="space-y-2">
                            <Label>Technical score</Label>
                            <Input
                                type="number"
                                min={0}
                                max={100}
                                value={evaluateForm.data.technical_score}
                                onChange={(event) =>
                                    evaluateForm.setData(
                                        'technical_score',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Financial score</Label>
                            <Input
                                type="number"
                                min={0}
                                max={100}
                                value={evaluateForm.data.financial_score}
                                onChange={(event) =>
                                    evaluateForm.setData(
                                        'financial_score',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Status</Label>
                            <select
                                className="border-input h-9 w-full rounded-md border px-3 text-sm"
                                value={evaluateForm.data.bac_status}
                                onChange={(event) =>
                                    evaluateForm.setData(
                                        'bac_status',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="compliant">Compliant</option>
                                <option value="non_compliant">
                                    Non-compliant
                                </option>
                                <option value="lowest_bidder">
                                    Lowest bidder
                                </option>
                            </select>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                type="submit"
                                disabled={evaluateForm.processing}
                            >
                                Save evaluation
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setEvaluatingId(null)}
                            >
                                Cancel
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

BacQuotationsManage.layout = {
    breadcrumbs: [{ title: 'BAC quotations', href: index() }],
};
