import {
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    pointerWithin,
    rectIntersection,
    useDndContext,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
    type Announcements,
    type CollisionDetection,
    type DragEndEvent,
    type DragStartEvent,
} from '@dnd-kit/core';
import { GripVertical, Layers } from 'lucide-react';
import { useState, type ComponentProps, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * Shared drag-and-drop for grouping purchase request items into lots. Drag
 * handles carry the items being moved; drop zones describe where they land.
 * A drop resolves to one LotDropAction, which each page maps onto the same
 * handlers its buttons already use.
 */

/**
 * Where the dragged items currently are: not yet in the request (a catalog
 * row), inside a lot, or individual items.
 */
export type LotDragOrigin = 'catalog' | 'lot' | 'ungrouped';

export type LotDragData = {
    itemIds: number[];
    label: string;
    origin: LotDragOrigin;
    /**
     * The lot the items are in now. `ungroupsIt` is true when taking them out
     * would leave it with fewer than two items, which ungroups the lot.
     */
    sourceLot?: { id: string; label: string; ungroupsIt: boolean };
};

/**
 * Build the source-lot details for an item in a lot of `lotSize` items,
 * `movingCount` of which are being dragged.
 */
export function sourceLotOf(
    id: string,
    label: string,
    lotSize: number,
    movingCount: number,
): LotDragData['sourceLot'] {
    return { id, label, ungroupsIt: lotSize - movingCount < 2 };
}

export type LotDropTarget =
    | { kind: 'lot'; lotId: string; label: string }
    | { kind: 'line'; lineId: number; label: string }
    | { kind: 'ungrouped' }
    | { kind: 'new-lot' };

type LotDropActionBase = {
    itemIds: number[];
    origin: LotDragOrigin;
    /** Name of the lot this drop ungroups because too few items stay in it. */
    ungroupsLot: string | null;
};

export type LotDropAction =
    | (LotDropActionBase & { kind: 'into-lot'; lotId: string; label: string })
    | (LotDropActionBase & { kind: 'new-lot'; label: string | null })
    | (LotDropActionBase & { kind: 'ungroup' });

/** Decide what dropping the dragged items onto a target should do, or null for a no-op. */
export function resolveLotDrop(
    drag: LotDragData,
    target: LotDropTarget,
): LotDropAction | null {
    const base: LotDropActionBase = {
        itemIds: drag.itemIds,
        origin: drag.origin,
        ungroupsLot: drag.sourceLot?.ungroupsIt ? drag.sourceLot.label : null,
    };

    switch (target.kind) {
        case 'lot':
            if (target.lotId === drag.sourceLot?.id) {
                return null;
            }

            return {
                ...base,
                kind: 'into-lot',
                lotId: target.lotId,
                label: target.label,
            };
        case 'line':
            if (drag.itemIds.includes(target.lineId)) {
                return null;
            }

            return {
                ...base,
                kind: 'new-lot',
                itemIds: [...drag.itemIds, target.lineId],
                label: target.label,
            };
        case 'ungrouped':
            return drag.origin === 'ungrouped'
                ? null
                : { ...base, kind: 'ungroup' };
        case 'new-lot':
            return { ...base, kind: 'new-lot', label: null };
    }
}

function describeMove(action: LotDropAction): string {
    const adding = action.origin === 'catalog';

    switch (action.kind) {
        case 'into-lot':
            return `${adding ? 'Add to' : 'Move into'} ${action.label}`;
        case 'new-lot':
            if (action.itemIds.length < 2) {
                return 'A new lot needs at least 2 items';
            }

            return action.label === null
                ? `New lot with ${action.itemIds.length} items`
                : `New lot with ${action.label}`;
        case 'ungroup':
            return adding ? 'Add as an individual item' : 'Take out of lot';
    }
}

/** True when the drop cannot happen, such as a new lot with only one item. */
export function isBlockedLotDrop(action: LotDropAction): boolean {
    return action.kind === 'new-lot' && action.itemIds.length < 2;
}

/** Short sentence shown on a drop zone while items hover over it. */
export function describeLotDrop(action: LotDropAction): string {
    const move = describeMove(action);

    if (action.ungroupsLot === null || isBlockedLotDrop(action)) {
        return move;
    }

    return `${move} · ungroups ${action.ungroupsLot}`;
}

const targetPriority: Record<LotDropTarget['kind'], number> = {
    line: 0,
    lot: 1,
    'new-lot': 2,
    ungrouped: 3,
};

function targetOf(data: unknown): LotDropTarget | null {
    if (typeof data === 'object' && data !== null && 'target' in data) {
        return (data as { target: LotDropTarget }).target;
    }

    return null;
}

function dragOf(data: unknown): LotDragData | null {
    if (typeof data === 'object' && data !== null && 'drag' in data) {
        return (data as { drag: LotDragData }).drag;
    }

    return null;
}

/**
 * Prefer the innermost target under the pointer (a row inside a list wins
 * over the list). Keyboard drags have no pointer, so fall back to overlap.
 */
const lotCollisionDetection: CollisionDetection = (args) => {
    const hits = pointerWithin(args);
    const collisions = hits.length > 0 ? hits : rectIntersection(args);

    return [...collisions].sort((a, b) => {
        const aTarget = targetOf(
            args.droppableContainers.find((container) => container.id === a.id)
                ?.data.current,
        );
        const bTarget = targetOf(
            args.droppableContainers.find((container) => container.id === b.id)
                ?.data.current,
        );

        return (
            (aTarget ? targetPriority[aTarget.kind] : 99) -
            (bTarget ? targetPriority[bTarget.kind] : 99)
        );
    });
};

function describeTarget(target: LotDropTarget | null): string {
    if (target === null) {
        return 'no drop area';
    }

    switch (target.kind) {
        case 'lot':
            return target.label;
        case 'line':
            return target.label;
        case 'ungrouped':
            return 'individual items';
        case 'new-lot':
            return 'new lot';
    }
}

const announcements: Announcements = {
    onDragStart: ({ active }) =>
        `Picked up ${dragOf(active.data.current)?.label ?? 'item'}. Use arrow keys to move, space to drop, escape to cancel.`,
    onDragOver: ({ active, over }) =>
        `${dragOf(active.data.current)?.label ?? 'Item'} is over ${describeTarget(targetOf(over?.data.current))}.`,
    onDragEnd: ({ active, over }) =>
        `Dropped ${dragOf(active.data.current)?.label ?? 'item'} on ${describeTarget(targetOf(over?.data.current))}.`,
    onDragCancel: ({ active }) =>
        `Cancelled moving ${dragOf(active.data.current)?.label ?? 'item'}.`,
};

/**
 * `id` must be stable and unique per page so the accessibility ids dnd-kit
 * generates match between the server render and hydration.
 */
export function LotDndContext({
    id,
    onDrop,
    children,
}: {
    id: string;
    onDrop: (action: LotDropAction) => void;
    children: ReactNode;
}) {
    const [activeDrag, setActiveDrag] = useState<LotDragData | null>(null);
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor),
    );

    function handleDragStart(event: DragStartEvent): void {
        setActiveDrag(dragOf(event.active.data.current));
    }

    function handleDragEnd(event: DragEndEvent): void {
        setActiveDrag(null);

        const drag = dragOf(event.active.data.current);
        const target = targetOf(event.over?.data.current);

        if (drag === null || target === null) {
            return;
        }

        const action = resolveLotDrop(drag, target);

        if (action !== null) {
            onDrop(action);
        }
    }

    return (
        <DndContext
            id={id}
            sensors={sensors}
            collisionDetection={lotCollisionDetection}
            accessibility={{ announcements }}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onDragCancel={() => setActiveDrag(null)}
        >
            {children}
            <DragOverlay dropAnimation={null}>
                {activeDrag !== null ? (
                    <div className="bg-background ring-primary/40 flex w-max max-w-72 cursor-grabbing items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium shadow-lg ring-2">
                        <GripVertical className="text-muted-foreground size-4 shrink-0" />
                        <span className="truncate">{activeDrag.label}</span>
                    </div>
                ) : null}
            </DragOverlay>
        </DndContext>
    );
}

/** The items currently being dragged, or null when nothing is. */
export function useActiveLotDrag(): LotDragData | null {
    const { active } = useDndContext();

    return dragOf(active?.data.current);
}

export function LotDragHandle({
    id,
    drag,
    disabled = false,
    className,
    ...props
}: {
    id: string;
    drag: LotDragData;
    disabled?: boolean;
} & Omit<ComponentProps<'button'>, 'id'>) {
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
        id,
        data: { drag },
        disabled,
    });

    return (
        <button
            type="button"
            ref={setNodeRef}
            {...attributes}
            {...listeners}
            {...props}
            aria-label={`Drag ${drag.label}`}
            title="Drag to move into a lot"
            className={cn(
                'text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring flex size-8 shrink-0 cursor-grab touch-none items-center justify-center rounded-md outline-none focus-visible:ring-2 disabled:pointer-events-none disabled:opacity-40',
                isDragging && 'cursor-grabbing',
                className,
                'pointer-coarse:size-10',
            )}
        >
            <GripVertical className="size-4" />
        </button>
    );
}

/**
 * Register a drop target. `potential` is what dropping the active drag here
 * would do (null when nothing is dragged or the drop would change nothing);
 * `action` is the same, but only while the drag hovers this target.
 */
export function useLotDropZone(
    id: string,
    target: LotDropTarget,
    disabled = false,
) {
    const { setNodeRef, isOver, active } = useDroppable({
        id,
        data: { target },
        disabled,
    });
    const drag = dragOf(active?.data.current);
    const potential =
        drag !== null && !disabled ? resolveLotDrop(drag, target) : null;
    const action = isOver ? potential : null;

    return {
        setNodeRef,
        isOver,
        action,
        potential,
        isDragging: drag !== null,
    };
}

/** Floating label that tells the user what a drop here will do. */
export function LotDropHint({
    action,
    className,
}: {
    action: LotDropAction | null;
    className?: string;
}) {
    if (action === null) {
        return null;
    }

    const blocked = isBlockedLotDrop(action);

    return (
        <span
            className={cn(
                'pointer-events-none inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium shadow-sm',
                blocked
                    ? 'bg-muted text-muted-foreground'
                    : action.ungroupsLot !== null
                      ? 'bg-amber-600 text-white dark:bg-amber-500 dark:text-amber-950'
                      : 'bg-primary text-primary-foreground',
                className,
            )}
        >
            <Layers className="size-3.5" />
            {describeLotDrop(action)}
        </span>
    );
}

/**
 * Ring styles for a drop zone: a faint dashed outline while it is a valid
 * place for the active drag, and a strong ring while hovered.
 */
export function lotDropZoneClass(
    isValidTarget: boolean,
    action: LotDropAction | null,
): string {
    if (action !== null) {
        return 'ring-primary bg-primary/[0.04] ring-2';
    }

    return isValidTarget
        ? 'outline-primary/40 outline-1 outline-offset-2 outline-dashed'
        : '';
}

/** A block-level drop target with the hover ring and hint built in. */
export function LotDropFrame({
    id,
    target,
    disabled = false,
    className,
    children,
    ...props
}: {
    id: string;
    target: LotDropTarget;
    disabled?: boolean;
} & Omit<ComponentProps<'div'>, 'id'>) {
    const { setNodeRef, action, potential } = useLotDropZone(
        id,
        target,
        disabled,
    );

    return (
        <div
            ref={setNodeRef}
            {...props}
            className={cn(
                'relative transition-shadow',
                lotDropZoneClass(potential !== null, action),
                className,
            )}
        >
            {children}
            <LotDropHint
                action={action}
                className="absolute -top-3 right-3 z-20"
            />
        </div>
    );
}

/**
 * A table row drop target. Rows cannot position an overlay hint reliably, so
 * the row hands the pending action to its cells to render a LotDropHint.
 */
export function LotDropRow({
    id,
    target,
    disabled = false,
    draggedItemId,
    outlineWhenValid = false,
    className,
    children,
    ...props
}: {
    id: string;
    target: LotDropTarget;
    disabled?: boolean;
    /** Dim the row while this item is part of the active drag. */
    draggedItemId?: number;
    /** Outline the row while it is a valid target (use on section header rows). */
    outlineWhenValid?: boolean;
    children: (action: LotDropAction | null) => ReactNode;
} & Omit<ComponentProps<'tr'>, 'id' | 'children'>) {
    const { setNodeRef, action, potential } = useLotDropZone(
        id,
        target,
        disabled,
    );
    const activeDrag = useActiveLotDrag();
    const isBeingDragged =
        draggedItemId !== undefined &&
        (activeDrag?.itemIds.includes(draggedItemId) ?? false);

    return (
        <tr
            ref={setNodeRef}
            {...props}
            className={cn(
                className,
                action !== null
                    ? 'bg-primary/[0.06] outline-primary outline-2 -outline-offset-2'
                    : potential !== null &&
                          outlineWhenValid &&
                          'outline-primary/40 outline-1 -outline-offset-2 outline-dashed',
                isBeingDragged && 'opacity-50',
            )}
        >
            {children(action)}
        </tr>
    );
}
