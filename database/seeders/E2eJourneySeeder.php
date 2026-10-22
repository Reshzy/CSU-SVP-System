<?php

namespace Database\Seeders;

use App\Models\AppItem;
use App\Models\DepartmentBudget;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PrItemGroup;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PpmpQuarterlyTracker;
use Illuminate\Database\Seeder;

class E2eJourneySeeder extends Seeder
{
    public const DEAN_EMAIL = 'maycmartinez03@csu.edu.ph';

    public const GROUPED_PR_TITLE = 'E2E grouped AOQ and batch PO';

    public function run(): void
    {
        $tracker = app(PpmpQuarterlyTracker::class);
        $fiscalYear = $tracker->currentFiscalYear();
        $quarter = $tracker->currentQuarter();

        $dean = User::query()->where('email', self::DEAN_EMAIL)->sole();
        $departmentId = (int) $dean->department_id;

        DepartmentBudget::query()->updateOrCreate(
            [
                'department_id' => $departmentId,
                'fiscal_year' => $fiscalYear,
            ],
            [
                'allocated_budget' => 1_000_000,
                'reserved_budget' => 0,
                'utilized_budget' => 0,
                'set_by' => $dean->id,
            ],
        );

        $ppmp = Ppmp::getOrCreateForDepartment($departmentId, $fiscalYear);

        if (! $ppmp->isValidated()) {
            $ppmp->markValidated($dean);
        }

        $quantities = [0, 0, 0, 0];
        $quantities[$quarter - 1] = 50;

        $catalogItems = AppItem::query()
            ->where('fiscal_year', $fiscalYear)
            ->whereNotNull('unit_price')
            ->orderBy('id')
            ->take(2)
            ->get();

        foreach ($catalogItems as $catalogItem) {
            PpmpItem::query()->updateOrCreate(
                [
                    'ppmp_id' => $ppmp->id,
                    'app_item_id' => $catalogItem->id,
                ],
                [
                    'q1_quantity' => $quantities[0],
                    'q2_quantity' => $quantities[1],
                    'q3_quantity' => $quantities[2],
                    'q4_quantity' => $quantities[3],
                    'total_quantity' => 50,
                    'estimated_unit_cost' => $catalogItem->unit_price,
                    'estimated_total_cost' => 50 * (float) $catalogItem->unit_price,
                ],
            );
        }

        $ppmp->forceFill([
            'total_estimated_cost' => $ppmp->items()->sum('estimated_total_cost'),
        ])->save();

        $this->seedGroupedPurchaseRequest($dean, $departmentId);
    }

    private function seedGroupedPurchaseRequest(User $dean, int $departmentId): void
    {
        if (PurchaseRequest::query()->where('pr_title', self::GROUPED_PR_TITLE)->exists()) {
            return;
        }

        $purchaseRequest = PurchaseRequest::factory()->create([
            'department_id' => $departmentId,
            'requester_id' => $dean->id,
            'pr_title' => self::GROUPED_PR_TITLE,
            'purpose' => 'Playwright grouped AOQ then batch PO',
            'status' => 'bac_evaluation',
            'procurement_method' => 'small_value_procurement',
            'resolution_number' => 'RES-E2E-0001',
            'estimated_total' => 1800,
        ]);

        $itemA = PurchaseRequestItem::factory()->for($purchaseRequest)->create([
            'item_name' => 'Bond paper',
            'quantity_requested' => 10,
            'estimated_unit_cost' => 100,
            'estimated_total_cost' => 1000,
            'parent_lot_id' => null,
            'is_lot' => false,
            'ppmp_item_id' => null,
        ]);

        $itemB = PurchaseRequestItem::factory()->for($purchaseRequest)->create([
            'item_name' => 'Laptop',
            'quantity_requested' => 2,
            'estimated_unit_cost' => 400,
            'estimated_total_cost' => 800,
            'parent_lot_id' => null,
            'is_lot' => false,
            'ppmp_item_id' => null,
        ]);

        $groupOne = PrItemGroup::factory()->for($purchaseRequest)->create([
            'group_name' => 'Office supplies',
            'group_code' => 'G1',
            'display_order' => 1,
        ]);
        $groupTwo = PrItemGroup::factory()->for($purchaseRequest)->create([
            'group_name' => 'IT equipment',
            'group_code' => 'G2',
            'display_order' => 2,
        ]);

        $itemA->update(['pr_item_group_id' => $groupOne->id]);
        $itemB->update(['pr_item_group_id' => $groupTwo->id]);

        $lienmavel = Supplier::query()->where('email', 'lienmavel@supplier.local')->sole();
        $me = Supplier::query()->where('email', 'me@supplier.local')->sole();

        $this->quoteGroupedItem($purchaseRequest, $itemA, $groupOne->id, $lienmavel, 90);
        $this->quoteGroupedItem($purchaseRequest, $itemB, $groupTwo->id, $me, 350);
    }

    private function quoteGroupedItem(
        PurchaseRequest $purchaseRequest,
        PurchaseRequestItem $item,
        int $groupId,
        Supplier $supplier,
        float $unitPrice,
    ): void {
        $quotation = Quotation::factory()->for($purchaseRequest)->for($supplier)->create([
            'bac_status' => 'pending_evaluation',
            'pr_item_group_id' => $groupId,
            'total_amount' => $unitPrice * $item->quantity_requested,
        ]);

        QuotationItem::factory()->for($quotation)->create([
            'purchase_request_item_id' => $item->id,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $item->quantity_requested,
            'is_within_abc' => $unitPrice <= (float) $item->estimated_unit_cost,
        ]);
    }
}
