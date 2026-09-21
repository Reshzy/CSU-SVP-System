import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    createColumnHelper,
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    aoq,
    evaluate,
    finalize,
    index,
    store,
} from '@/routes/bac/quotations';
import {
    download as downloadRfq,
    generate as generateRfq,
    regenerate as regenerateRfq,
} from '@/routes/bac/quotations/rfq';

type Item = {
    id: number;
    item_name: string | null;
    estimated_unit_cost: string;
    quantity_requested: number;
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
    supplier?: { business_name: string } | null;
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
    };
    suppliers: Supplier[];
    statusLabel: string;
};

const columnHelper = createColumnHelper<Quotation>();

export default function BacQuotationsManage({
    purchaseRequest,
    suppliers,
    statusLabel,
}: Props) {
    const hasRfq = purchaseRequest.documents.some(
        (document) => document.document_type === 'bac_rfq',
    );
    const [evaluatingId, setEvaluatingId] = useState<number | null>(null);

    const storeForm = useForm({
        supplier_id: String(suppliers[0]?.id ?? ''),
        items: purchaseRequest.quotable_items.map((item) => ({
            purchase_request_item_id: item.id,
            unit_price: '',
        })),
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
                        {purchaseRequest.status !== 'bac_approved' && (
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
        [purchaseRequest.id, purchaseRequest.status],
    );

    const table = useReactTable({
        data: purchaseRequest.quotations,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <Head title={`Quotations ${purchaseRequest.pr_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={purchaseRequest.pr_number ?? 'Quotations'}
                    description={statusLabel}
                />

                <form
                    className="space-y-4 rounded-lg border p-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        storeForm
                            .transform((data) => ({
                                supplier_id: Number(data.supplier_id),
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
                    <h2 className="font-medium">Store quotation</h2>
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
                    </div>
                    {purchaseRequest.quotable_items.map((item, index) => (
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
                                        regenerateRfq.url(purchaseRequest.id),
                                    )
                                }
                            >
                                Regenerate RFQ
                            </Button>
                            <Button variant="outline" asChild>
                                <a href={downloadRfq.url(purchaseRequest.id)}>
                                    Download RFQ
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
