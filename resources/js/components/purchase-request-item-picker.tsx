import { Form, Link } from '@inertiajs/react';
import {
    createContext,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
    type PointerEvent as ReactPointerEvent,
} from 'react';
import { toast } from 'sonner';
import {
    createDataTableColumnHelper,
    DataTable,
} from '@/components/data-table';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { index as ppmpIndex, summary as ppmpSummary } from '@/routes/ppmp';
import { realign } from '@/routes/ppmp/items';

export type AvailablePpmpItem = {
    id: number;
    item_code: string | null;
    item_name: string | null;
    unit_of_measure: string | null;
    item_category: string | null;
    estimated_unit_cost: string;
    remaining_qty: number;
    later_remaining: number;
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
    ppmpId?: number | null;
    canUpdatePpmp?: boolean;
};

const CatalogContext = createContext<CatalogContextValue | null>(null);

type PaintDrag = {
    pointerId: number;
    startId: number;
    startX: number;
    startY: number;
    lastX: number;
    lastY: number;
    mode: 'select' | 'deselect';
    painting: boolean;
    visited: Set<number>;
    captureTarget: HTMLElement | null;
};

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
    ppmpId = null,
    canUpdatePpmp = false,
}: Props) {
    const [addQuantities, setAddQuantities] = useState<Record<number, string>>({});
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [isSelecting, setIsSelecting] = useState(false);
    const standaloneListRef = useRef<HTMLDivElement>(null);
    const paintRef = useRef<PaintDrag | null>(null);
    const suppressClickRef = useRef(false);
    const paintedIdsRef = useRef<Set<number>>(new Set());
    const standaloneIdsRef = useRef<Set<number>>(new Set());

    standaloneIdsRef.current = new Set(
        lines
            .filter((line) => line.lot_id === null)
            .map((line) => line.ppmp_item_id),
    );

    useEffect(() => {
        function lineIdFromPoint(x: number, y: number): number | null {
            const element = document.elementFromPoint(x, y);
            const row = element?.closest('[data-cart-line-id]');

            if (!(row instanceof HTMLElement)) {
                return null;
            }

            const id = Number(row.dataset.cartLineId);

            return Number.isFinite(id) ? id : null;
        }

        function applyPaint(id: number, mode: 'select' | 'deselect'): void {
            const drag = paintRef.current;

            if (drag === null || !standaloneIdsRef.current.has(id) || drag.visited.has(id)) {
                return;
            }

            drag.visited.add(id);
            paintedIdsRef.current.add(id);
            setSelectedIds((current) => {
                if (mode === 'select') {
                    return current.includes(id) ? current : [...current, id];
                }

                return current.includes(id)
                    ? current.filter((existing) => existing !== id)
                    : current;
            });
        }

        function idsAlong(x0: number, y0: number, x1: number, y1: number): number[] {
            const distance = Math.hypot(x1 - x0, y1 - y0);
            const steps = Math.max(1, Math.ceil(distance / 8));
            const ids: number[] = [];

            for (let step = 1; step <= steps; step += 1) {
                const t = step / steps;
                const id = lineIdFromPoint(
                    x0 + (x1 - x0) * t,
                    y0 + (y1 - y0) * t,
                );

                if (id !== null) {
                    ids.push(id);
                }
            }

            return ids;
        }

        function onPointerMove(event: PointerEvent): void {
            const drag = paintRef.current;

            if (drag === null || event.pointerId !== drag.pointerId) {
                return;
            }

            const distance = Math.hypot(
                event.clientX - drag.startX,
                event.clientY - drag.startY,
            );

            if (!drag.painting && distance < 4) {
                return;
            }

            if (!drag.painting) {
                drag.painting = true;
                applyPaint(drag.startId, drag.mode);

                try {
                    drag.captureTarget?.setPointerCapture(event.pointerId);
                } catch {
                    // Capture can fail once the pointer is no longer active.
                }
            }

            for (const id of idsAlong(
                drag.lastX,
                drag.lastY,
                event.clientX,
                event.clientY,
            )) {
                applyPaint(id, drag.mode);
            }

            drag.lastX = event.clientX;
            drag.lastY = event.clientY;
        }

        function finishPaint(event: PointerEvent): void {
            const drag = paintRef.current;

            if (drag === null || event.pointerId !== drag.pointerId) {
                return;
            }

            if (drag.painting) {
                suppressClickRef.current = true;
            }

            try {
                if (drag.captureTarget?.hasPointerCapture(event.pointerId)) {
                    drag.captureTarget.releasePointerCapture(event.pointerId);
                }
            } catch {
                // The list may have unmounted during the drag.
            }

            paintRef.current = null;
            document.body.style.userSelect = '';
            setIsSelecting(false);
            window.setTimeout(() => {
                paintedIdsRef.current.clear();
            }, 0);
        }

        window.addEventListener('pointermove', onPointerMove);
        window.addEventListener('pointerup', finishPaint);
        window.addEventListener('pointercancel', finishPaint);

        return () => {
            window.removeEventListener('pointermove', onPointerMove);
            window.removeEventListener('pointerup', finishPaint);
            window.removeEventListener('pointercancel', finishPaint);
            document.body.style.userSelect = '';
            paintRef.current = null;
        };
    }, []);

    const itemsById = useMemo(() => {
        return new Map(availableItems.map((item) => [item.id, item]));
    }, [availableItems]);

    const catalogItems = useMemo(
        () => availableItems.filter((item) => item.remaining_qty > 0),
        [availableItems],
    );

    const laterItems = useMemo(
        () =>
            availableItems.filter(
                (item) => item.remaining_qty === 0 && item.later_remaining > 0,
            ),
        [availableItems],
    );

    const laterColumns = useMemo(
        () => [
            columnHelper.accessor('item_name', {
                header: 'Item',
                fill: true,
                cell: ({ row }) => (
                    <>
                        <span className="font-medium">
                            {row.original.item_name ?? 'Untitled item'}
                        </span>
                        <span className="text-muted-foreground block font-mono text-xs">
                            {row.original.item_code} ·{' '}
                            {row.original.item_category}
                        </span>
                    </>
                ),
            }),
            columnHelper.accessor('later_remaining', {
                header: 'Later qty',
                cell: ({ getValue }) => (
                    <span className="tabular-nums">{getValue()}</span>
                ),
            }),
            columnHelper.display({
                id: 'realign',
                header: '',
                enableSorting: false,
                cell: ({ row }) => {
                    if (ppmpId === null || !canUpdatePpmp) {
                        return (
                            <span className="text-muted-foreground text-xs">
                                Planned for later this year
                            </span>
                        );
                    }

                    return (
                        <Form
                            {...realign.form([ppmpId, row.original.id])}
                            disableWhileProcessing
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="outline"
                                    data-test="realign-ppmp-item"
                                >
                                    {processing ? <Spinner /> : null}
                                    Bring into this quarter
                                </Button>
                            )}
                        </Form>
                    );
                },
            }),
        ],
        [canUpdatePpmp, ppmpId],
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
        if (paintedIdsRef.current.has(ppmpItemId)) {
            return;
        }

        setSelectedIds((current) => {
            if (checked) {
                return current.includes(ppmpItemId)
                    ? current
                    : [...current, ppmpItemId];
            }

            return current.filter((id) => id !== ppmpItemId);
        });
    }

    function activateLine(ppmpItemId: number, selected: boolean): void {
        if (suppressClickRef.current) {
            suppressClickRef.current = false;

            return;
        }

        toggleSelected(ppmpItemId, !selected);
    }

    function beginPaint(
        ppmpItemId: number,
        selected: boolean,
        event: ReactPointerEvent<HTMLDivElement>,
    ): void {
        if (event.pointerType !== 'mouse' || event.button !== 0) {
            return;
        }

        suppressClickRef.current = false;
        paintRef.current = {
            pointerId: event.pointerId,
            startId: ppmpItemId,
            startX: event.clientX,
            startY: event.clientY,
            lastX: event.clientX,
            lastY: event.clientY,
            mode: selected ? 'deselect' : 'select',
            painting: false,
            visited: new Set(),
            captureTarget: standaloneListRef.current,
        };
        document.body.style.userSelect = 'none';
        setIsSelecting(true);
    }

    const selectedStandalone = lines.filter(
        (line) => line.lot_id === null && selectedIds.includes(line.ppmp_item_id),
    );

    function groupAsLot(): void {
        if (selectedStandalone.length < 2) {
            toast('Select at least 2 items in this request first');

            return;
        }

        const lotId = nextLotId();
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
                    <h2 className="text-base font-medium">Available PPMP items</h2>
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
                        emptyMessage="No PPMP items have remaining quantity you can request now, including unused earlier quarters."
                        noResultsMessage="No items match the current filters."
                    />
                </div>

                {laterItems.length > 0 ? (
                    <div className="space-y-3">
                        <div className="space-y-1">
                            <h2 className="text-base font-medium">
                                Planned for later this year
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                These items have quantity only in later
                                quarters.{' '}
                                {ppmpId !== null ? (
                                    <Link
                                        href={ppmpSummary(ppmpId)}
                                        className="underline"
                                    >
                                        Open the PPMP
                                    </Link>
                                ) : (
                                    <Link
                                        href={ppmpIndex.url()}
                                        className="underline"
                                    >
                                        Open the PPMP
                                    </Link>
                                )}{' '}
                                to realign them, or bring them into this
                                quarter here.
                            </p>
                        </div>
                        <DataTable
                            tableId="pr-create-later"
                            mode="client"
                            data={laterItems}
                            columns={laterColumns}
                            searchPlaceholder="Item name or code"
                            getRowId={(row) => String(row.id)}
                            initialPinning={{ start: ['item_name'] }}
                            emptyMessage="No later-quarter items."
                            noResultsMessage="No items match the current filters."
                        />
                    </div>
                ) : null}

                <div className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div className="space-y-1">
                            <h2 className="text-base font-medium">This request</h2>
                            <p className="text-muted-foreground text-sm">
                                Select 2 or more items, then group them as a lot.
                            </p>
                        </div>
                        <span
                            className={cn(
                                'inline-flex',
                                selectedStandalone.length < 2 && 'cursor-not-allowed',
                            )}
                            onClick={() => {
                                if (selectedStandalone.length < 2) {
                                    groupAsLot();
                                }
                            }}
                        >
                            <Button
                                type="button"
                                variant="outline"
                                disabled={selectedStandalone.length < 2}
                                onClick={groupAsLot}
                                data-testid="group-as-lot"
                            >
                                {selectedStandalone.length > 0
                                    ? `Group as lot (${selectedStandalone.length})`
                                    : 'Group as lot'}
                            </Button>
                        </span>
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

                            {standaloneLines.length > 0 ? (
                                <div
                                    ref={standaloneListRef}
                                    className={cn(
                                        'space-y-3',
                                        isSelecting && 'select-none',
                                    )}
                                >
                                    {standaloneLines.map((line) => (
                                        <CartLineRow
                                            key={line.ppmp_item_id}
                                            line={line}
                                            item={itemsById.get(
                                                line.ppmp_item_id,
                                            )}
                                            selectable
                                            selected={selectedIds.includes(
                                                line.ppmp_item_id,
                                            )}
                                            onToggleSelected={toggleSelected}
                                            onActivate={() =>
                                                activateLine(
                                                    line.ppmp_item_id,
                                                    selectedIds.includes(
                                                        line.ppmp_item_id,
                                                    ),
                                                )
                                            }
                                            onPointerDown={(event) =>
                                                beginPaint(
                                                    line.ppmp_item_id,
                                                    selectedIds.includes(
                                                        line.ppmp_item_id,
                                                    ),
                                                    event,
                                                )
                                            }
                                            onQuantityChange={setLineQuantity}
                                            onRemove={removeLine}
                                        />
                                    ))}
                                </div>
                            ) : null}
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
    onActivate,
    onPointerDown,
    onQuantityChange,
    onRemove,
}: {
    line: PurchaseRequestCartLine;
    item: AvailablePpmpItem | undefined;
    selectable: boolean;
    selected: boolean;
    onToggleSelected: (ppmpItemId: number, checked: boolean) => void;
    onActivate?: () => void;
    onPointerDown?: (event: ReactPointerEvent<HTMLDivElement>) => void;
    onQuantityChange: (ppmpItemId: number, raw: string) => void;
    onRemove: (ppmpItemId: number) => void;
}) {
    const remaining = item?.remaining_qty ?? 0;
    const unitCost = Number(item?.estimated_unit_cost ?? 0);
    const lineTotal = line.quantity_requested * unitCost;

    return (
        <div
            data-cart-line-id={selectable ? line.ppmp_item_id : undefined}
            onClick={
                selectable
                    ? (event) => {
                          const target = event.target;

                          if (
                              !(target instanceof Element) ||
                              target.closest(
                                  '[data-cart-line-ignore], [data-slot="checkbox"]',
                              )
                          ) {
                              return;
                          }

                          onActivate?.();
                      }
                    : undefined
            }
            onPointerDown={
                selectable
                    ? (event) => {
                          const target = event.target;

                          if (
                              !(target instanceof Element) ||
                              target.closest('[data-cart-line-ignore]')
                          ) {
                              return;
                          }

                          onPointerDown?.(event);
                      }
                    : undefined
            }
            className={cn(
                'grid items-end gap-3 rounded-lg border p-3 md:grid-cols-[auto_1fr_8rem_8rem_auto]',
                selectable && 'cursor-pointer',
                selected && 'bg-accent ring-2 ring-ring',
            )}
        >
            {selectable ? (
                <Checkbox
                    checked={selected}
                    onCheckedChange={(checked) =>
                        onToggleSelected(line.ppmp_item_id, checked === true)
                    }
                    onClick={(event) => event.stopPropagation()}
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
            <div className="space-y-1" data-cart-line-ignore="">
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
                data-cart-line-ignore=""
                onClick={(event) => {
                    event.stopPropagation();
                    onRemove(line.ppmp_item_id);
                }}
            >
                Remove
            </Button>
        </div>
    );
}

function nextLotId(): string {
    try {
        return crypto.randomUUID?.() ?? `lot-${Date.now()}`;
    } catch {
        return `lot-${Date.now()}`;
    }
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
