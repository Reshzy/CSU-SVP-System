<?php

namespace App\Services;

use App\Enums\PpmpStatus;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Notifications\PurchaseRequestSubmitted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class PurchaseRequestCreator
{
    public function __construct(
        private PpmpQuarterlyTracker $tracker,
        private PurchaseRequestActivityLogger $activityLogger,
    ) {}

    public function validatedPpmpFor(User $user): ?Ppmp
    {
        return Ppmp::query()
            ->where('department_id', $user->department_id)
            ->where('fiscal_year', $this->tracker->currentFiscalYear())
            ->where('status', PpmpStatus::Validated)
            ->first();
    }

    /**
     * Props shared by the new and replacement purchase request forms.
     *
     * @return array{fiscalYear: int, quarter: int, hasValidatedPpmp: bool, ppmpId: int|null, canUpdatePpmp: bool, availableItems: Collection<int, array<string, mixed>>|list<never>}
     */
    public function formProps(User $user, ?int $excludePurchaseRequestId = null): array
    {
        $ppmp = $this->validatedPpmpFor($user);

        return [
            'fiscalYear' => $this->tracker->currentFiscalYear(),
            'quarter' => $this->tracker->currentQuarter(),
            'hasValidatedPpmp' => $ppmp !== null,
            'ppmpId' => $ppmp?->id,
            'canUpdatePpmp' => $ppmp !== null && Gate::forUser($user)->allows('update', $ppmp),
            'availableItems' => $ppmp === null ? [] : $this->availableItems($ppmp, $excludePurchaseRequestId),
        ];
    }

    /**
     * Build the PPMP lines a requester may pick from, with what is still usable this quarter.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function availableItems(Ppmp $ppmp, ?int $excludePurchaseRequestId = null): Collection
    {
        $quarter = $this->tracker->currentQuarter();

        $ppmp->loadMissing('items.appItem');

        return $ppmp->items->map(fn (PpmpItem $item): array => [
            'id' => $item->id,
            'app_item_id' => $item->app_item_id,
            'item_code' => $item->appItem?->item_code,
            'item_name' => $item->appItem?->item_name,
            'unit_of_measure' => $item->appItem?->unit_of_measure,
            'item_category' => $item->appItem?->category,
            'detailed_specifications' => $item->appItem?->specifications,
            'estimated_unit_cost' => $item->estimated_unit_cost,
            'planned_qty' => $item->getQuarterlyQuantity($quarter),
            'remaining_qty' => $item->getUsableQuantity($quarter, $excludePurchaseRequestId),
            'later_remaining' => $item->getLaterRemainingQuantity($quarter),
        ])->values();
    }

    /**
     * Create a purchase request at supply_office_review from validated item rows.
     *
     * When `$replaces` is given, the new request replaces that returned request,
     * which is archived so its PPMP quantity is released.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        User $user,
        Ppmp $ppmp,
        array $items,
        array $attributes,
        ?PurchaseRequest $replaces = null,
    ): PurchaseRequest {
        $quarter = $this->tracker->currentQuarter();
        $resolvedRows = [];
        $estimatedTotal = 0.0;

        foreach ($items as $index => $row) {
            if ((bool) ($row['is_lot'] ?? false)) {
                $resolvedRows[$index] = [
                    'is_lot' => true,
                    'lot_name' => $row['lot_name'] ?? 'Lot',
                    'quantity_requested' => 1,
                    'estimated_unit_cost' => 0,
                    'estimated_total_cost' => 0,
                    'unit_of_measure' => 'lot',
                    'parent_lot_index' => null,
                    'ppmp_item_id' => null,
                ];

                continue;
            }

            $ppmpItem = PpmpItem::query()
                ->with('appItem')
                ->whereKey($row['ppmp_item_id'])
                ->where('ppmp_id', $ppmp->id)
                ->firstOrFail();

            $qty = (int) $row['quantity_requested'];
            $submittedName = trim((string) ($row['item_name'] ?? ''));
            $submittedCost = $row['estimated_unit_cost'] ?? null;
            $unitCost = $submittedCost === null || $submittedCost === ''
                ? (float) $ppmpItem->estimated_unit_cost
                : (float) $submittedCost;
            $lineTotal = round($qty * $unitCost, 2);

            if (($row['parent_lot_index'] ?? null) === null) {
                $estimatedTotal += $lineTotal;
            }

            $resolvedRows[$index] = [
                'is_lot' => false,
                'ppmp_item_id' => $ppmpItem->id,
                'ppmp_quarter' => $quarter,
                'ppmp_planned_qty_for_quarter' => $ppmpItem->getQuarterlyQuantity($quarter),
                'ppmp_remaining_qty_at_creation' => $ppmpItem->getUsableQuantity($quarter, $replaces?->id),
                'item_code' => $ppmpItem->appItem?->item_code,
                'item_name' => $submittedName !== '' ? $submittedName : $ppmpItem->appItem?->item_name,
                'detailed_specifications' => $ppmpItem->appItem?->specifications,
                'unit_of_measure' => $ppmpItem->appItem?->unit_of_measure ?? 'pc',
                'item_category' => $ppmpItem->appItem?->category,
                'quantity_requested' => $qty,
                'estimated_unit_cost' => $unitCost,
                'estimated_total_cost' => $lineTotal,
                'parent_lot_index' => $row['parent_lot_index'] ?? null,
            ];
        }

        foreach ($resolvedRows as $index => $row) {
            if (! $row['is_lot']) {
                continue;
            }

            $childTotal = 0.0;

            foreach ($resolvedRows as $child) {
                if (($child['parent_lot_index'] ?? null) === $index) {
                    $childTotal += (float) $child['estimated_total_cost'];
                }
            }

            $resolvedRows[$index]['estimated_unit_cost'] = $childTotal;
            $resolvedRows[$index]['estimated_total_cost'] = $childTotal;
            $estimatedTotal += $childTotal;
        }

        $purchaseRequest = DB::transaction(function () use ($user, $ppmp, $attributes, $resolvedRows, $estimatedTotal, $replaces): PurchaseRequest {
            $purchaseRequest = PurchaseRequest::query()->create([
                ...$attributes,
                'department_id' => $user->department_id,
                'requester_id' => $user->id,
                'status' => 'supply_office_review',
                'estimated_total' => $estimatedTotal,
                'ppmp_reference' => "PPMP-{$ppmp->fiscal_year}",
                'submitted_at' => now(),
                'replaces_pr_id' => $replaces?->id,
            ]);

            $createdByIndex = [];

            foreach ($resolvedRows as $index => $row) {
                if (($row['parent_lot_index'] ?? null) !== null) {
                    continue;
                }

                $createdByIndex[$index] = $this->createItem($purchaseRequest, $row);
            }

            foreach ($resolvedRows as $row) {
                if (($row['parent_lot_index'] ?? null) === null) {
                    continue;
                }

                $this->createItem($purchaseRequest, [
                    ...$row,
                    'parent_lot_id' => ($createdByIndex[(int) $row['parent_lot_index']] ?? null)?->id,
                ]);
            }

            if ($replaces !== null) {
                $replaces->forceFill([
                    'replaced_by_pr_id' => $purchaseRequest->id,
                    'is_archived' => true,
                ])->saveQuietly();

                $this->activityLogger->log(
                    $purchaseRequest,
                    'replacement_created',
                    "Replacement for {$replaces->pr_number}.",
                    newValue: ['replaces_pr_id' => $replaces->id],
                );
            }

            return $purchaseRequest->fresh(['items']);
        });

        $supplyOfficers = User::role('Supply Officer')->get();

        if ($supplyOfficers->isNotEmpty()) {
            Notification::send($supplyOfficers, new PurchaseRequestSubmitted($purchaseRequest));
        }

        return $purchaseRequest;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createItem(PurchaseRequest $purchaseRequest, array $row): PurchaseRequestItem
    {
        return $purchaseRequest->items()->create([
            'ppmp_item_id' => $row['ppmp_item_id'] ?? null,
            'ppmp_quarter' => $row['ppmp_quarter'] ?? null,
            'ppmp_planned_qty_for_quarter' => $row['ppmp_planned_qty_for_quarter'] ?? null,
            'ppmp_remaining_qty_at_creation' => $row['ppmp_remaining_qty_at_creation'] ?? null,
            'is_lot' => (bool) ($row['is_lot'] ?? false),
            'lot_name' => $row['lot_name'] ?? null,
            'parent_lot_id' => $row['parent_lot_id'] ?? null,
            'item_code' => $row['item_code'] ?? null,
            'item_name' => $row['item_name'] ?? ($row['lot_name'] ?? null),
            'detailed_specifications' => $row['detailed_specifications'] ?? null,
            'unit_of_measure' => $row['unit_of_measure'] ?? 'pc',
            'quantity_requested' => $row['quantity_requested'],
            'estimated_unit_cost' => $row['estimated_unit_cost'],
            'estimated_total_cost' => $row['estimated_total_cost'],
            'item_category' => $row['item_category'] ?? null,
            'item_status' => 'pending',
            'procurement_status' => 'pending',
        ]);
    }
}
