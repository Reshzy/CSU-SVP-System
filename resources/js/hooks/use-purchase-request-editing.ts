import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { update as updateDetails } from '@/routes/supply/purchase-requests/details';
import {
    destroy as destroyLot,
    store as storeLot,
    update as updateLot,
} from '@/routes/supply/purchase-requests/lots';

export type EditablePurchaseRequestItem = {
    id: number;
    item_name: string | null;
    detailed_specifications: string | null;
    is_lot: boolean;
    lot_name: string | null;
    parent_lot_id: number | null;
};

type EditablePurchaseRequest<TItem extends EditablePurchaseRequestItem> = {
    id: number;
    purpose: string | null;
    justification: string | null;
    items: TItem[];
};

/**
 * Shared Supply Office editing state for a purchase request: purpose,
 * justification, item specifications and lot grouping. Pages render their own
 * markup and call these handlers.
 *
 * The drag-and-drop handlers update the `purchaseRequest` page prop
 * optimistically, so the page must receive the request under that prop name.
 */
export function usePurchaseRequestEditing<
    TItem extends EditablePurchaseRequestItem,
>(purchaseRequest: EditablePurchaseRequest<TItem>, canEdit: boolean) {
    const lineItems = purchaseRequest.items.filter((item) => !item.is_lot);
    const lots = purchaseRequest.items.filter((item) => item.is_lot);

    /* ---------------- Details ---------------- */

    const [editingNarrative, setEditingNarrative] = useState(false);
    const [editingSpecs, setEditingSpecs] = useState(false);

    const initialDetails = () => ({
        purpose: purchaseRequest.purpose ?? '',
        justification: purchaseRequest.justification ?? '',
        items: lineItems.map((item) => ({
            id: item.id,
            detailed_specifications: item.detailed_specifications ?? '',
        })),
    });

    const detailsForm = useForm(initialDetails());

    const specFor = (itemId: number): string =>
        detailsForm.data.items.find((row) => row.id === itemId)
            ?.detailed_specifications ?? '';

    const setSpecFor = (itemId: number, value: string) => {
        detailsForm.setData(
            'items',
            detailsForm.data.items.map((row) =>
                row.id === itemId
                    ? { ...row, detailed_specifications: value }
                    : row,
            ),
        );
    };

    const cancelDetailsEdit = () => {
        detailsForm.setData(initialDetails());
        detailsForm.clearErrors();
        setEditingNarrative(false);
        setEditingSpecs(false);
    };

    const saveDetails = () => {
        detailsForm.patch(updateDetails.url(purchaseRequest.id), {
            preserveScroll: true,
            onSuccess: () => {
                detailsForm.setDefaults();
                setEditingNarrative(false);
                setEditingSpecs(false);
            },
        });
    };

    /* ---------------- Lots ---------------- */

    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [editingLotId, setEditingLotId] = useState<number | null>(null);
    const [lotPendingRemoval, setLotPendingRemoval] = useState<TItem | null>(
        null,
    );
    const lotForm = useForm<{ lot_name: string; item_ids: number[] }>({
        lot_name: '',
        item_ids: [],
    });

    const isSelectable = (item: TItem): boolean =>
        canEdit &&
        !editingSpecs &&
        (item.parent_lot_id === null || item.parent_lot_id === editingLotId);

    const toggleSelected = (itemId: number, checked: boolean) => {
        setSelectedIds((current) =>
            checked
                ? [...current, itemId]
                : current.filter((id) => id !== itemId),
        );
    };

    const clearSelection = () => {
        setSelectedIds([]);
        setEditingLotId(null);
        lotForm.reset();
        lotForm.clearErrors();
    };

    const startEditingSpecs = () => {
        clearSelection();
        setEditingSpecs(true);
    };

    const childIdsOf = (lotId: number): number[] =>
        lineItems
            .filter((item) => item.parent_lot_id === lotId)
            .map((item) => item.id);

    const startEditingLot = (lot: TItem) => {
        setEditingLotId(lot.id);
        lotForm.setData('lot_name', lot.lot_name ?? '');
        setSelectedIds(childIdsOf(lot.id));
    };

    const submitLot = () => {
        const payload = {
            lot_name: lotForm.data.lot_name,
            item_ids: selectedIds,
        };
        const options = { preserveScroll: true, onSuccess: clearSelection };

        lotForm.transform(() => payload);

        if (editingLotId !== null) {
            lotForm.put(
                updateLot.url({
                    purchaseRequest: purchaseRequest.id,
                    lot: editingLotId,
                }),
                options,
            );

            return;
        }

        lotForm.post(storeLot.url(purchaseRequest.id), options);
    };

    /** Replace a lot's members while keeping its name; updateLot recalculates the lot total. */
    const saveLotMembers = (lot: TItem, itemIds: number[]) => {
        lotForm.transform(() => ({
            lot_name: lot.lot_name ?? '',
            item_ids: itemIds,
        }));
        lotForm.put(
            updateLot.url({ purchaseRequest: purchaseRequest.id, lot: lot.id }),
            {
                preserveScroll: true,
                onSuccess: clearSelection,
            },
        );
    };

    const addSelectedToLot = (lot: TItem) => {
        saveLotMembers(lot, [...childIdsOf(lot.id), ...selectedIds]);
    };

    const removeFromLot = (item: TItem) => {
        const lot = lots.find(
            (candidate) => candidate.id === item.parent_lot_id,
        );

        if (!lot) {
            return;
        }

        const remainingIds = childIdsOf(lot.id).filter((id) => id !== item.id);

        if (remainingIds.length < 2) {
            setLotPendingRemoval(lot);

            return;
        }

        saveLotMembers(lot, remainingIds);
    };

    /** Runs after the pending lot is ungrouped, when a drag is waiting on that confirmation. */
    const [afterLotRemoval, setAfterLotRemoval] = useState<(() => void) | null>(
        null,
    );

    const cancelLotRemoval = () => {
        setLotPendingRemoval(null);
        setAfterLotRemoval(null);
    };

    const confirmRemoveLot = () => {
        if (!lotPendingRemoval) {
            return;
        }

        const continuation = afterLotRemoval;
        const removedLotId = lotPendingRemoval.id;

        router.delete(
            destroyLot.url({
                purchaseRequest: purchaseRequest.id,
                lot: removedLotId,
            }),
            {
                preserveScroll: true,
                preserveState: true,
                optimistic: moveOptimistically(childIdsOf(removedLotId), null),
                onSuccess: () => continuation?.(),
                onFinish: cancelLotRemoval,
            },
        );
    };

    /* ---------------- Drag and drop ---------------- */

    /** Optimistically re-parent items in the `purchaseRequest` prop until the server answers. */
    const moveOptimistically =
        (itemIds: number[], lotId: number | null) =>
        (props: Record<string, unknown>): Record<string, unknown> => {
            const current =
                props.purchaseRequest as EditablePurchaseRequest<TItem>;

            return {
                purchaseRequest: {
                    ...current,
                    items: current.items.map((item) =>
                        itemIds.includes(item.id)
                            ? { ...item, parent_lot_id: lotId }
                            : item,
                    ),
                },
            };
        };

    const putLotMembers = (
        lot: TItem,
        memberIds: number[],
        moved: { itemIds: number[]; lotId: number | null },
        onSuccess?: () => void,
    ) => {
        router.put(
            updateLot.url({ purchaseRequest: purchaseRequest.id, lot: lot.id }),
            { lot_name: lot.lot_name ?? '', item_ids: memberIds },
            {
                preserveScroll: true,
                preserveState: true,
                optimistic: moveOptimistically(moved.itemIds, moved.lotId),
                onSuccess: () => {
                    setSelectedIds((current) =>
                        current.filter((id) => !moved.itemIds.includes(id)),
                    );
                    onSuccess?.();
                },
                onError: (errors) => {
                    toast.error(
                        Object.values(errors)[0] ??
                            'The lot could not be updated.',
                    );
                },
            },
        );
    };

    /**
     * Take items out of the lot they are in, then continue. The server only
     * moves standalone items, so a move between lots detaches first. When
     * that would leave the lot with fewer than two items, the user confirms
     * ungrouping it before anything happens.
     */
    const detachThen = (itemIds: number[], next: () => void) => {
        const sourceLotId =
            lineItems.find(
                (item) =>
                    itemIds.includes(item.id) && item.parent_lot_id !== null,
            )?.parent_lot_id ?? null;
        const sourceLot = lots.find((lot) => lot.id === sourceLotId);

        if (!sourceLot) {
            next();

            return;
        }

        const remainingIds = childIdsOf(sourceLot.id).filter(
            (id) => !itemIds.includes(id),
        );

        if (remainingIds.length < 2) {
            setLotPendingRemoval(sourceLot);
            setAfterLotRemoval(() => next);

            return;
        }

        putLotMembers(sourceLot, remainingIds, { itemIds, lotId: null }, next);
    };

    /** Drop result: put items into an existing lot. */
    const moveIntoLot = (itemIds: number[], lotId: number) => {
        const lot = lots.find((candidate) => candidate.id === lotId);

        if (!lot) {
            return;
        }

        const currentIds = childIdsOf(lot.id);
        const incomingIds = itemIds.filter((id) => !currentIds.includes(id));

        if (incomingIds.length === 0) {
            return;
        }

        detachThen(incomingIds, () =>
            putLotMembers(lot, [...currentIds, ...incomingIds], {
                itemIds: incomingIds,
                lotId: lot.id,
            }),
        );
    };

    /** Drop result: make items standalone again. */
    const takeOutOfLot = (itemIds: number[]) => {
        detachThen(itemIds, () => undefined);
    };

    /**
     * Drop result: select items for a new lot. Lots need a name, so this
     * opens the lot bar instead of creating the lot straight away.
     */
    const startLotWith = (itemIds: number[], onReady?: () => void) => {
        detachThen(itemIds, () => {
            setEditingLotId(null);
            lotForm.setData('lot_name', '');
            lotForm.clearErrors();
            setSelectedIds(itemIds);
            onReady?.();
        });
    };

    const canDrag = canEdit && !editingSpecs && editingLotId === null;

    const showLotBar =
        canEdit && (selectedIds.length > 0 || editingLotId !== null);
    const editingLot = lots.find((lot) => lot.id === editingLotId);

    return {
        lineItems,
        lots,
        editingNarrative,
        setEditingNarrative,
        editingSpecs,
        startEditingSpecs,
        detailsForm,
        specFor,
        setSpecFor,
        cancelDetailsEdit,
        saveDetails,
        selectedIds,
        editingLotId,
        editingLot,
        lotPendingRemoval,
        setLotPendingRemoval,
        cancelLotRemoval,
        canDrag,
        moveIntoLot,
        takeOutOfLot,
        startLotWith,
        lotForm,
        isSelectable,
        toggleSelected,
        clearSelection,
        childIdsOf,
        startEditingLot,
        submitLot,
        addSelectedToLot,
        removeFromLot,
        confirmRemoveLot,
        showLotBar,
    };
}
