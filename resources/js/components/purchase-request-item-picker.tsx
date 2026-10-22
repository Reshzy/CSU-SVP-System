import { createContext, useContext, useMemo, useState } from 'react';
import {
    createDataTableColumnHelper,
    DataTable,
} from '@/components/data-table';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type AvailablePpmpItem = {
    id: number;
    item_code: string | null;
    item_name: string | null;
    unit_of_measure: string | null;
    item_category: string | null;
    estimated_unit_cost: string;
    remaining_qty: number;
};

export type PurchaseRequestCartLine = {
    ppmp_item_id: number;
    quantity_requested: number;
    lot_id: string | null;
};

export type PurchaseRequestCartLot = {
    id: string;
    name: string;
};

export type PurchaseRequestItemPayload = {
    is_lot: boolean;
    lot_name: string | null;
    ppmp_item_id: number | null;
    quantity_requested: number | null;
    parent_lot_index: number | null;
};

type CatalogContextValue = {
    addQuantity: (itemId: number) => string;
    setAddQuantity: (itemId: number, value: string) => void;
    addItem: (item: AvailablePpmpItem, quantity: number) => void;
    cartQuantity: (itemId: number) => number;
};

type Props = {
    availableItems: AvailablePpmpItem[];
    lines: PurchaseRequestCartLine[];
    lots: PurchaseRequestCartLot[];
    onLinesChange: (lines: PurchaseRequestCartLine[]) => void;
    onLotsChange: (lots: PurchaseRequestCartLot[]) => void;
    errors: Record<string, string>;
};

const CatalogContext = createContext<CatalogContextValue | null>(null);

const columnHelper = createDataTableColumnHelper<AvailablePpmpItem>();

const columns = [
    columnHelper.accessor('item_name', {
        header: 'Item',
        fill: true,
        cell: ({ row }) => (
            <>
                <span className="font-medium">
                    {row.original.item_name ?? 'Untitled item'}
                </span>
                <span className="text-muted-foreground block font-mono text-xs">
                    {row.original.item_code} · {row.original.item_category}
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
    columnHelper.accessor('remaining_qty', {
        header: 'Remaining',
        cell: ({ getValue }) => (
            <span className="tabular-nums">{getValue()}</span>
        ),
    }),
    columnHelper.accessor('estimated_unit_cost', {
        header: 'Unit cost',
        cell: ({ getValue }) => (
            <span className="tabular-nums">{formatMoney(Number(getValue()))}</span>
        ),
    }),
    columnHelper.display({
        id: 'qty',
        header: 'Qty',
        enableSorting: false,
        cell: ({ row }) => <CatalogQtyCell item={row.original} />,
    }),
    columnHelper.display({
        id: 'add',
        header: '',
        enableSorting: false,
        cell: ({ row }) => <CatalogAddCell item={row.original} />,
    }),
];

function useCatalog(): CatalogContextValue {
    const catalog = useContext(CatalogContext);

    if (catalog === null) {
        throw new Error('Catalog cells must render inside PurchaseRequestItemPicker.');
    }

    return catalog;
}

function CatalogQtyCell({ item }: { item: AvailablePpmpItem }) {
    const catalog = useCatalog();

    return (
        <>
            <Label htmlFor={`add-qty-${item.id}`} className="sr-only">
                Quantity to add for {item.item_name}
            </Label>
            <Input
                id={`add-qty-${item.id}`}
                type="number"
                min={1}
                max={item.remaining_qty}
                value={catalog.addQuantity(item.id)}
                onChange={(event) =>
                    catalog.setAddQuantity(item.id, event.target.value)
                }
                className="w-20 text-right"
            />
        </>
    );
}

function CatalogAddCell({ item }: { item: AvailablePpmpItem }) {
    const catalog = useCatalog();
    const inCart = catalog.cartQuantity(item.id);

    return (
        <div className="flex flex-col items-end gap-1">
            <Button
                type="button"
                size="sm"
                data-testid="add-ppmp-item"
                onClick={() =>
                    catalog.addItem(item, parseQuantity(catalog.addQuantity(item.id)))
                }
            >
                Add
            </Button>
            {inCart > 0 ? (
                <span className="text-muted-foreground text-xs">
                    In request ({inCart})
                </span>
            ) : null}
        </div>
    );
}

export function cartItemsPayload(
    lots: PurchaseRequestCartLot[],
    lines: PurchaseRequestCartLine[],
): PurchaseRequestItemPayload[] {
    const items: PurchaseRequestItemPayload[] = [];

    for (const lot of lots) {
        const children = lines.filter((line) => line.lot_id === lot.id);

        if (children.length === 0) {
            continue;
        }

        const headerIndex = items.length;

        items.push({
            is_lot: true,
            lot_name: lot.name,
            ppmp_item_id: null,
            quantity_requested: null,
            parent_lot_index: null,
        });

        for (const child of children) {
            items.push({
                is_lot: false,
                lot_name: null,
                ppmp_item_id: child.ppmp_item_id,
                quantity_requested: child.quantity_requested,
                parent_lot_index: headerIndex,
            });
        }
    }

    for (const line of lines.filter((candidate) => candidate.lot_id === null)) {
        items.push({
            is_lot: false,
            lot_name: null,
            ppmp_item_id: line.ppmp_item_id,
            quantity_requested: line.quantity_requested,
            parent_lot_index: null,
        });
    }

    return items;
}

export function PurchaseRequestItemPicker({
    availableItems,
    lines,
    lots,
    onLinesChange,
    onLotsChange,
    errors,
}: Props) {
    const [addQuantities, setAddQuantities] = useState<Record<number, string>>({});
    const [selectedIds, setSelectedIds] = useState<number[]>([]);

    const itemsById = useMemo(() => {
        return new Map(availableItems.map((item) => [item.id, item]));
    }, [availableItems]);

    const catalogItems = useMemo(
        () => availableItems.filter((item) => item.remaining_qty > 0),
        [availableItems],
    );

    const categories = useMemo(
        () =>
            [...new Set(catalogItems.map((item) => item.item_category))]
                .filter((category): category is string => Boolean(category))
                .sort()
                .map((category) => ({ value: category, label: category })),
        [catalogItems],
    );

    const cartQuantityById = useMemo(() => {
        return new Map(
            lines.map((line) => [line.ppmp_item_id, line.quantity_requested]),
        );
    }, [lines]);

    function addItems(items: AvailablePpmpItem[], quantityEach: number): void {
        let next = [...lines];

        for (const item of items) {
            const increment = Math.max(1, quantityEach);
            const existing = next.find((line) => line.ppmp_item_id === item.id);

            if (existing) {
                next = next.map((line) =>
                    line.ppmp_item_id === item.id
                        ? {
                              ...line,
                              quantity_requested: Math.min(
                                  item.remaining_qty,
                                  line.quantity_requested + increment,
                              ),
                          }
                        : line,
                );
            } else if (item.remaining_qty > 0) {
                next = [
                    ...next,
                    {
                        ppmp_item_id: item.id,
                        quantity_requested: Math.min(item.remaining_qty, increment),
                        lot_id: null,
                    },
                ];
            }
        }

        onLinesChange(next);
    }

    function setLineQuantity(ppmpItemId: number, raw: string): void {
        const item = itemsById.get(ppmpItemId);

        if (!item) {
            return;
        }

        const quantity = Math.min(
            item.remaining_qty,
            Math.max(1, parseQuantity(raw)),
        );

        onLinesChange(
            lines.map((line) =>
                line.ppmp_item_id === ppmpItemId
                    ? { ...line, quantity_requested: quantity }
                    : line,
            ),
        );
    }

    function removeLine(ppmpItemId: number): void {
        const line = lines.find((candidate) => candidate.ppmp_item_id === ppmpItemId);
        const nextLines = lines.filter(
            (candidate) => candidate.ppmp_item_id !== ppmpItemId,
        );

        if (line?.lot_id) {
            const remainingInLot = nextLines.filter(
                (candidate) => candidate.lot_id === line.lot_id,
            );

            if (remainingInLot.length < 2) {
                onLinesChange(
                    nextLines.map((candidate) =>
                        candidate.lot_id === line.lot_id
                            ? { ...candidate, lot_id: null }
                            : candidate,
                    ),
                );
                onLotsChange(lots.filter((lot) => lot.id !== line.lot_id));
                setSelectedIds((current) =>
                    current.filter((id) => id !== ppmpItemId),
                );

                return;
            }
        }

        onLinesChange(nextLines);
        setSelectedIds((current) => current.filter((id) => id !== ppmpItemId));
    }

    function toggleSelected(ppmpItemId: number, checked: boolean): void {
        setSelectedIds((current) => {
            if (checked) {
                return current.includes(ppmpItemId)
                    ? current
                    : [...current, ppmpItemId];
            }

            return current.filter((id) => id !== ppmpItemId);
        });
    }

    const selectedStandalone = lines.filter(
        (line) => line.lot_id === null && selectedIds.includes(line.ppmp_item_id),
    );

    function groupAsLot(): void {
        if (selectedStandalone.length < 2) {
            return;
        }

        const lotId = crypto.randomUUID();
        const selected = new Set(
            selectedStandalone.map((line) => line.ppmp_item_id),
        );

        onLotsChange([
            ...lots,
            { id: lotId, name: `Lot ${lots.length + 1}` },
        ]);
        onLinesChange(
            lines.map((line) =>
                selected.has(line.ppmp_item_id) && line.lot_id === null
                    ? { ...line, lot_id: lotId }
                    : line,
            ),
        );
        setSelectedIds([]);
    }

    function renameLot(lotId: string, name: string): void {
        onLotsChange(
            lots.map((lot) => (lot.id === lotId ? { ...lot, name } : lot)),
        );
    }

    function ungroupLot(lotId: string): void {
        onLinesChange(
            lines.map((line) =>
                line.lot_id === lotId ? { ...line, lot_id: null } : line,
            ),
        );
        onLotsChange(lots.filter((lot) => lot.id !== lotId));
    }

    const catalog: CatalogContextValue = {
        addQuantity: (itemId) => addQuantities[itemId] ?? '1',
        setAddQuantity: (itemId, value) =>
            setAddQuantities((current) => ({ ...current, [itemId]: value })),
        addItem: (item, quantity) => addItems([item], quantity),
        cartQuantity: (itemId) => cartQuantityById.get(itemId) ?? 0,
    };

    const itemErrors = Object.entries(errors).filter(
        ([key]) => key === 'items' || key.startsWith('items.'),
    );

    const standaloneLines = lines.filter((line) => line.lot_id === null);

    return (
        <CatalogContext.Provider value={catalog}>
            <div className="space-y-6">
                <div className="space-y-3">
                    <h2 className="text-base font-medium">PPMP items this quarter</h2>
                    <DataTable
                        tableId="pr-create-catalog"
                        mode="client"
                        data={catalogItems}
                        columns={columns}
                        searchPlaceholder="Item name or code"
                        filterDefs={
                            categories.length > 0
                                ? [
                                      {
                                          id: 'item_category',
                                          label: 'Category',
                                          options: categories,
                                      },
                                  ]
                                : []
                        }
                        bulkActions={[
                            {
                                label: 'Add to request',
                                onAction: (rows) => addItems(rows, 1),
                            },
                        ]}
                        getRowId={(row) => String(row.id)}
                        initialPinning={{ start: ['item_name'] }}
                        emptyMessage="No PPMP items have remaining quantity this quarter."
                        noResultsMessage="No items match the current filters."
                    />
                </div>

                <div className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-base font-medium">This request</h2>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={selectedStandalone.length < 2}
                            onClick={groupAsLot}
                        >
                            Group as lot
                        </Button>
                    </div>

                    {lines.length === 0 ? (
                        <p className="text-muted-foreground rounded-lg border border-dashed p-4 text-sm">
                            Search the PPMP above and add items to this request.
                        </p>
                    ) : (
                        <div className="space-y-3">
                            {lots.map((lot) => {
                                const children = lines.filter(
                                    (line) => line.lot_id === lot.id,
                                );

                                if (children.length === 0) {
                                    return null;
                                }

                                return (
                                    <div
                                        key={lot.id}
                                        className="space-y-2 rounded-lg border p-3"
                                    >
                                        <div className="flex flex-wrap items-end gap-2">
                                            <div className="min-w-48 flex-1 space-y-1">
                                                <Label htmlFor={`lot-name-${lot.id}`}>
                                                    Lot name
                                                </Label>
                                                <Input
                                                    id={`lot-name-${lot.id}`}
                                                    value={lot.name}
                                                    onChange={(event) =>
                                                        renameLot(
                                                            lot.id,
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => ungroupLot(lot.id)}
                                            >
                                                Ungroup
                                            </Button>
                                        </div>
                                        <div className="space-y-2 pl-4">
                                            {children.map((line) => (
                                                <CartLineRow
                                                    key={line.ppmp_item_id}
                                                    line={line}
                                                    item={itemsById.get(line.ppmp_item_id)}
                                                    selectable={false}
                                                    selected={false}
                                                    onToggleSelected={() => undefined}
                                                    onQuantityChange={setLineQuantity}
                                                    onRemove={removeLine}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                );
                            })}

                            {standaloneLines.map((line) => (
                                <CartLineRow
                                    key={line.ppmp_item_id}
                                    line={line}
                                    item={itemsById.get(line.ppmp_item_id)}
                                    selectable
                                    selected={selectedIds.includes(line.ppmp_item_id)}
                                    onToggleSelected={toggleSelected}
                                    onQuantityChange={setLineQuantity}
                                    onRemove={removeLine}
                                />
                            ))}
                        </div>
                    )}

                    {itemErrors.map(([key, message]) => (
                        <InputError key={key} message={message} />
                    ))}
                </div>
            </div>
        </CatalogContext.Provider>
    );
}

function CartLineRow({
    line,
    item,
    selectable,
    selected,
    onToggleSelected,
    onQuantityChange,
    onRemove,
}: {
    line: PurchaseRequestCartLine;
    item: AvailablePpmpItem | undefined;
    selectable: boolean;
    selected: boolean;
    onToggleSelected: (ppmpItemId: number, checked: boolean) => void;
    onQuantityChange: (ppmpItemId: number, raw: string) => void;
    onRemove: (ppmpItemId: number) => void;
}) {
    const remaining = item?.remaining_qty ?? 0;
    const unitCost = Number(item?.estimated_unit_cost ?? 0);
    const lineTotal = line.quantity_requested * unitCost;

    return (
        <div className="grid items-end gap-3 rounded-lg border p-3 md:grid-cols-[auto_1fr_8rem_8rem_auto]">
            {selectable ? (
                <Checkbox
                    checked={selected}
                    onCheckedChange={(checked) =>
                        onToggleSelected(line.ppmp_item_id, checked === true)
                    }
                    aria-label={`Select ${item?.item_name ?? 'item'}`}
                />
            ) : (
                <span />
            )}
            <div className="min-w-0">
                <p className="font-medium">{item?.item_name ?? 'Unknown item'}</p>
                <p className="text-muted-foreground font-mono text-xs">
                    {item?.item_code} · rem {remaining}
                </p>
            </div>
            <div className="space-y-1">
                <Label htmlFor={`cart-qty-${line.ppmp_item_id}`}>Qty</Label>
                <Input
                    id={`cart-qty-${line.ppmp_item_id}`}
                    type="number"
                    min={1}
                    max={remaining}
                    value={line.quantity_requested}
                    onChange={(event) =>
                        onQuantityChange(line.ppmp_item_id, event.target.value)
                    }
                    data-testid="cart-qty"
                />
            </div>
            <p className="text-sm tabular-nums">{formatMoney(lineTotal)}</p>
            <Button
                type="button"
                variant="ghost"
                onClick={() => onRemove(line.ppmp_item_id)}
            >
                Remove
            </Button>
        </div>
    );
}

function parseQuantity(raw: string): number {
    const parsed = Number(raw);

    if (!Number.isFinite(parsed) || parsed < 1) {
        return 1;
    }

    return Math.floor(parsed);
}

function formatMoney(value: number): string {
    return value.toLocaleString(undefined, { minimumFractionDigits: 2 });
}
