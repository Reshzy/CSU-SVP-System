import { useForm } from '@inertiajs/react';
import { createContext, useContext, useMemo, useRef } from 'react';
import {
    createDataTableColumnHelper,
    DataTable,
} from '@/components/data-table';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { CatalogItem, PlannedItem } from '@/types';

type LineDraft = {
    app_item_id: number;
    q1_quantity: number;
    q2_quantity: number;
    q3_quantity: number;
    q4_quantity: number;
    custom_unit_price: string;
};

type Quarter = 1 | 2 | 3 | 4;

type EditorContextValue = {
    getLine: (appItemId: number) => LineDraft | undefined;
    setQuantity: (appItemId: number, quarter: Quarter, value: number) => void;
    setCustomPrice: (appItemId: number, value: string) => void;
    priceError: (appItemId: number) => string | undefined;
};

type Props = {
    /** Spread from a Wayfinder `.form()` helper. */
    action: string;
    method: 'post' | 'put' | 'patch' | 'get';
    fiscalYear: number;
    appItems: CatalogItem[];
    plannedItems: PlannedItem[];
    submitLabel: string;
};

const QUARTERS: Quarter[] = [1, 2, 3, 4];

const PpmpEditorContext = createContext<EditorContextValue | null>(null);

const columnHelper = createDataTableColumnHelper<CatalogItem>();

const columns = [
    columnHelper.accessor('item_name', {
        header: 'Item',
        cell: ({ row }) => (
            <>
                <span className="font-medium">{row.original.item_name}</span>
                <span className="text-muted-foreground block font-mono text-xs">
                    {row.original.item_code} · {row.original.category}
                </span>
            </>
        ),
    }),
    columnHelper.accessor('unit_of_measure', {
        header: 'Unit',
        enableSorting: false,
        cell: ({ getValue }) => (
            <span className="text-muted-foreground text-xs">{getValue()}</span>
        ),
    }),
    columnHelper.accessor('unit_price', {
        header: 'Unit price',
        cell: ({ row }) => <UnitPriceCell item={row.original} />,
    }),
    ...QUARTERS.map((quarter) =>
        columnHelper.accessor((row) => row.id, {
            id: `q${quarter}`,
            header: `Q${quarter}`,
            enableSorting: false,
            cell: ({ row }) => (
                <QuarterCell item={row.original} quarter={quarter} />
            ),
        }),
    ),
];

function usePpmpEditor(): EditorContextValue {
    const editor = useContext(PpmpEditorContext);

    if (editor === null) {
        throw new Error('PPMP editor cells must render inside PpmpForm.');
    }

    return editor;
}

function UnitPriceCell({ item }: { item: CatalogItem }) {
    const editor = usePpmpEditor();

    if (item.unit_price !== null) {
        return (
            <span className="block text-right tabular-nums">
                {item.unit_price}
            </span>
        );
    }

    const line = editor.getLine(item.id);

    return (
        <div className="ml-auto w-28">
            <Label htmlFor={`custom-price-${item.id}`} className="sr-only">
                Unit price for {item.item_name}
            </Label>
            <Input
                id={`custom-price-${item.id}`}
                type="number"
                step="0.01"
                min="0.01"
                placeholder="Set price"
                value={line?.custom_unit_price ?? ''}
                onChange={(event) =>
                    editor.setCustomPrice(item.id, event.target.value)
                }
                className="text-right"
            />
            <InputError message={editor.priceError(item.id)} />
        </div>
    );
}

function QuarterCell({
    item,
    quarter,
}: {
    item: CatalogItem;
    quarter: Quarter;
}) {
    const editor = usePpmpEditor();
    const line = editor.getLine(item.id);
    const field = `q${quarter}_quantity` as const;

    return (
        <>
            <Label htmlFor={`q${quarter}-${item.id}`} className="sr-only">
                Q{quarter} quantity for {item.item_name}
            </Label>
            <Input
                id={`q${quarter}-${item.id}`}
                type="number"
                min={0}
                value={line?.[field] ?? 0}
                onChange={(event) =>
                    editor.setQuantity(
                        item.id,
                        quarter,
                        Number(event.target.value) || 0,
                    )
                }
                className="w-20 text-right"
            />
        </>
    );
}

function draftsFromCatalog(
    appItems: CatalogItem[],
    plannedItems: PlannedItem[],
): LineDraft[] {
    const planned = new Map(
        plannedItems.map((item) => [item.app_item_id, item]),
    );

    return appItems.map((appItem) => {
        const line = planned.get(appItem.id);

        return {
            app_item_id: appItem.id,
            q1_quantity: line?.q1_quantity ?? 0,
            q2_quantity: line?.q2_quantity ?? 0,
            q3_quantity: line?.q3_quantity ?? 0,
            q4_quantity: line?.q4_quantity ?? 0,
            custom_unit_price:
                appItem.unit_price === null
                    ? (line?.estimated_unit_cost ?? '')
                    : '',
        };
    });
}

export function PpmpForm({
    action,
    method,
    fiscalYear,
    appItems,
    plannedItems,
    submitLabel,
}: Props) {
    const form = useForm({
        fiscal_year: fiscalYear,
        items: draftsFromCatalog(appItems, plannedItems),
    });
    const itemsRef = useRef(form.data.items);
    itemsRef.current = form.data.items;

    const indexById = useMemo(() => {
        const map = new Map<number, number>();

        appItems.forEach((item, index) => {
            map.set(item.id, index);
        });

        return map;
    }, [appItems]);

    const categories = useMemo(
        () =>
            [...new Set(appItems.map((item) => item.category))]
                .filter(Boolean)
                .sort()
                .map((category) => ({ value: category, label: category })),
        [appItems],
    );

    const updateLine = (
        appItemId: number,
        patch: Partial<Omit<LineDraft, 'app_item_id'>>,
    ) => {
        const index = indexById.get(appItemId);

        if (index === undefined) {
            return;
        }

        const next = itemsRef.current.map((item, itemIndex) =>
            itemIndex === index ? { ...item, ...patch } : item,
        );

        itemsRef.current = next;
        form.setData('items', next);
    };

    const editor: EditorContextValue = {
        getLine: (appItemId) => {
            const index = indexById.get(appItemId);

            return index === undefined ? undefined : itemsRef.current[index];
        },
        setQuantity: (appItemId, quarter, value) => {
            updateLine(appItemId, {
                [`q${quarter}_quantity`]: value,
            });
        },
        setCustomPrice: (appItemId, value) => {
            updateLine(appItemId, { custom_unit_price: value });
        },
        priceError: (appItemId) => {
            const index = indexById.get(appItemId);

            if (index === undefined) {
                return undefined;
            }

            return form.errors[`items.${index}.custom_unit_price`];
        },
    };

    const rowErrors = Object.entries(form.errors)
        .filter(([key]) => key.startsWith('items.') && key !== 'items')
        .map(([, message]) => message);

    if (appItems.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                The PS-DBMS catalog for {fiscalYear} is empty. Import it before
                planning against it.
            </p>
        );
    }

    const submit = () => {
        form.transform((data) => ({
            fiscal_year: data.fiscal_year,
            items: data.items
                .filter(
                    (item) =>
                        Number(item.q1_quantity) +
                            Number(item.q2_quantity) +
                            Number(item.q3_quantity) +
                            Number(item.q4_quantity) >
                        0,
                )
                .map((item) => ({
                    app_item_id: item.app_item_id,
                    q1_quantity: Number(item.q1_quantity),
                    q2_quantity: Number(item.q2_quantity),
                    q3_quantity: Number(item.q3_quantity),
                    q4_quantity: Number(item.q4_quantity),
                    ...(item.custom_unit_price !== ''
                        ? { custom_unit_price: item.custom_unit_price }
                        : {}),
                })),
        }));
        form.submit(method, action);
    };

    return (
        <PpmpEditorContext.Provider value={editor}>
            <div className="space-y-4">
                <p className="text-muted-foreground text-sm">
                    Leave an item at zero to keep it off the plan.
                </p>

                <InputError message={form.errors.items} />
                {rowErrors.map((message, index) => (
                    <InputError key={`${message}-${index}`} message={message} />
                ))}

                <DataTable
                    tableId="ppmp-editor"
                    mode="client"
                    data={appItems}
                    columns={columns}
                    searchPlaceholder="Item name or code"
                    filterDefs={
                        categories.length > 0
                            ? [
                                  {
                                      id: 'category',
                                      label: 'Category',
                                      options: categories,
                                  },
                              ]
                            : []
                    }
                    initialPinning={{ start: ['item_name'] }}
                    emptyMessage={`No catalog items for ${fiscalYear}.`}
                    noResultsMessage="No items match the current filters."
                />

                <Button
                    type="button"
                    data-test="save-ppmp-button"
                    disabled={form.processing}
                    onClick={() => submit()}
                >
                    {form.processing && <Spinner />}
                    {submitLabel}
                </Button>
            </div>
        </PpmpEditorContext.Provider>
    );
}
