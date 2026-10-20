<?php

namespace App\Services;

use App\Models\PrItemGroup;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * @return Collection<int, array{
     *     supplier: Supplier,
     *     quotation: Quotation,
     *     items: Collection<int, array{
     *         quotation_item: QuotationItem,
     *         pr_item: PurchaseRequestItem,
     *         quantity: int,
     *         unit_price: mixed,
     *         total_price: mixed
     *     }>,
     *     total_amount: float,
     *     item_count: int
     * }>
     */
    public function getWinningItemsGroupedBySupplier(
        PurchaseRequest $purchaseRequest,
        ?PrItemGroup $itemGroup = null,
    ): Collection {
        $winningItems = QuotationItem::query()
            ->with(['quotation.supplier', 'purchaseRequestItem'])
            ->where('is_winner', true)
            ->where('is_withdrawn', false)
            ->whereHas('quotation', function ($query) use ($purchaseRequest, $itemGroup): void {
                $query->where('purchase_request_id', $purchaseRequest->id);

                if ($itemGroup) {
                    $query->where('pr_item_group_id', $itemGroup->id);
                }
            })
            ->get()
            ->filter(function (QuotationItem $quotationItem): bool {
                $prItem = $quotationItem->purchaseRequestItem;

                return $prItem !== null && ! $prItem->isLotChild();
            })
            ->values();

        return $winningItems
            ->groupBy(fn (QuotationItem $item): int => (int) $item->quotation->supplier_id)
            ->map(function (Collection $items): array {
                /** @var QuotationItem $firstItem */
                $firstItem = $items->first();
                $itemsData = $items->map(function (QuotationItem $quotationItem): array {
                    $prItem = $quotationItem->purchaseRequestItem;

                    return [
                        'quotation_item' => $quotationItem,
                        'pr_item' => $prItem,
                        'quantity' => (int) $prItem->quantity_requested,
                        'unit_price' => $quotationItem->unit_price,
                        'total_price' => $quotationItem->total_price,
                    ];
                });

                return [
                    'supplier' => $firstItem->quotation->supplier,
                    'quotation' => $firstItem->quotation,
                    'items' => $itemsData,
                    'total_amount' => (float) $itemsData->sum('total_price'),
                    'item_count' => $items->count(),
                ];
            })
            ->values();
    }

    /**
     * @param  array<int, array<string, mixed>>  $poDataArray
     * @return Collection<int, PurchaseOrder>
     */
    public function createBatchPurchaseOrders(
        PurchaseRequest $purchaseRequest,
        ?PrItemGroup $itemGroup,
        array $poDataArray,
    ): Collection {
        return DB::transaction(function () use ($purchaseRequest, $itemGroup, $poDataArray) {
            $created = collect();

            foreach ($poDataArray as $poData) {
                $purchaseOrder = PurchaseOrder::query()->create([
                    'po_number' => PurchaseOrder::generateNextPoNumber(),
                    'purchase_request_id' => $purchaseRequest->id,
                    'pr_item_group_id' => $itemGroup?->id ?? ($poData['pr_item_group_id'] ?? null),
                    'supplier_id' => $poData['supplier_id'],
                    'quotation_id' => $poData['quotation_id'] ?? null,
                    'po_date' => now(),
                    'tin' => $poData['tin'] ?? null,
                    'supplier_name_override' => $poData['supplier_name_override'] ?? null,
                    'funds_cluster' => filled($poData['funds_cluster'] ?? null) ? $poData['funds_cluster'] : null,
                    'funds_available' => isset($poData['funds_available']) && $poData['funds_available'] !== '' ? $poData['funds_available'] : null,
                    'ors_burs_no' => filled($poData['ors_burs_no'] ?? null) ? $poData['ors_burs_no'] : null,
                    'ors_burs_date' => isset($poData['ors_burs_date']) && $poData['ors_burs_date'] !== '' ? $poData['ors_burs_date'] : null,
                    'total_amount' => isset($poData['total_amount']) && $poData['total_amount'] !== '' ? $poData['total_amount'] : null,
                    'delivery_address' => $poData['delivery_address'],
                    'delivery_date_required' => $poData['delivery_date_required'],
                    'terms_and_conditions' => $poData['terms_and_conditions'],
                    'special_instructions' => $poData['special_instructions'] ?? null,
                    'status' => 'pending_approval',
                ]);

                foreach ($poData['items'] as $itemData) {
                    PurchaseOrderItem::query()->create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'purchase_request_item_id' => $itemData['purchase_request_item_id'],
                        'quotation_item_id' => $itemData['quotation_item_id'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'total_price' => $itemData['total_price'],
                    ]);
                }

                $created->push($purchaseOrder);
            }

            $purchaseRequest->unsetRelation('itemGroups');
            $purchaseRequest->load('itemGroups.purchaseOrders');

            if ($purchaseRequest->itemGroups->isNotEmpty()) {
                $purchaseRequest->syncStatusFromGroups();
            } else {
                $purchaseRequest->status = 'po_generation';
                $purchaseRequest->status_updated_at = now();
                $purchaseRequest->save();
            }

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $poData
     */
    public function createPurchaseOrder(
        PurchaseRequest $purchaseRequest,
        ?PrItemGroup $itemGroup,
        array $poData,
    ): PurchaseOrder {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = $this->createBatchPurchaseOrders($purchaseRequest, $itemGroup, [$poData])->first();

        return $purchaseOrder;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itemsPayloadForSupplier(
        PurchaseRequest $purchaseRequest,
        int $supplierId,
        ?PrItemGroup $itemGroup = null,
    ): array {
        $grouped = $this->getWinningItemsGroupedBySupplier($purchaseRequest, $itemGroup);
        $supplierGroup = $grouped->first(
            fn (array $row): bool => (int) $row['supplier']->id === $supplierId,
        );

        if ($supplierGroup === null) {
            return [];
        }

        return $supplierGroup['items']->map(fn (array $item): array => [
            'purchase_request_item_id' => $item['pr_item']->id,
            'quotation_item_id' => $item['quotation_item']->id,
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'total_price' => $item['total_price'],
        ])->all();
    }
}
