<?php

use App\Models\Department;
use App\Models\PrItemGroup;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');

    $this->department = Department::factory()->create();
    $this->secretariat = User::factory()->create();
    $this->secretariat->assignRole('BAC Secretariat');

    $this->purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'status' => 'bac_evaluation',
        'procurement_method' => 'small_value_procurement',
        'resolution_number' => 'RES-0926-0001',
    ]);

    $this->itemA = PurchaseRequestItem::factory()->for($this->purchaseRequest)->create([
        'item_name' => 'Bond paper',
        'quantity_requested' => 10,
        'estimated_unit_cost' => 100,
        'estimated_total_cost' => 1000,
        'parent_lot_id' => null,
        'is_lot' => false,
    ]);

    $this->itemB = PurchaseRequestItem::factory()->for($this->purchaseRequest)->create([
        'item_name' => 'Laptop',
        'quantity_requested' => 2,
        'estimated_unit_cost' => 400,
        'estimated_total_cost' => 800,
        'parent_lot_id' => null,
        'is_lot' => false,
    ]);
});

test('min group status stays bac evaluation while one group is still pending', function () {
    [$g1, $g2] = createTwoQuotedGroups($this->purchaseRequest, $this->itemA, $this->itemB);

    $this->actingAs($this->secretariat)
        ->post(route('bac.item-groups.aoq.generate', $g1))
        ->assertRedirect();

    $this->purchaseRequest->refresh();
    $this->purchaseRequest->unsetRelation('itemGroups');

    expect($g1->fresh()->computeStatus())->toBe('aoq_generated')
        ->and($g2->fresh()->computeStatus())->toBe('pending')
        ->and($this->purchaseRequest->computeStatusFromGroups())->toBe('bac_evaluation')
        ->and($this->purchaseRequest->status)->toBe('bac_evaluation');
});

test('stored pr status becomes bac approved when every group has an aoq', function () {
    [$g1, $g2] = createTwoQuotedGroups($this->purchaseRequest, $this->itemA, $this->itemB);

    $this->actingAs($this->secretariat)
        ->post(route('bac.item-groups.aoq.generate', $g1))
        ->assertRedirect();

    $this->actingAs($this->secretariat)
        ->post(route('bac.item-groups.aoq.generate', $g2))
        ->assertRedirect();

    $this->purchaseRequest->refresh();

    expect($this->purchaseRequest->computeStatusFromGroups())->toBe('bac_approved')
        ->and($this->purchaseRequest->status)->toBe('bac_approved');
});

/**
 * @return array{0: PrItemGroup, 1: PrItemGroup}
 */
function createTwoQuotedGroups(PurchaseRequest $pr, PurchaseRequestItem $itemA, PurchaseRequestItem $itemB): array
{
    $g1 = PrItemGroup::factory()->for($pr)->create([
        'group_name' => 'Office supplies',
        'group_code' => 'G1',
        'display_order' => 1,
    ]);
    $g2 = PrItemGroup::factory()->for($pr)->create([
        'group_name' => 'IT equipment',
        'group_code' => 'G2',
        'display_order' => 2,
    ]);

    $itemA->update(['pr_item_group_id' => $g1->id]);
    $itemB->update(['pr_item_group_id' => $g2->id]);

    quoteGroupedItem($pr, $itemA, $g1->id, 90);
    quoteGroupedItem($pr, $itemB, $g2->id, 350);

    return [$g1, $g2];
}

function quoteGroupedItem(PurchaseRequest $pr, PurchaseRequestItem $item, int $groupId, float $unitPrice): QuotationItem
{
    $quotation = Quotation::factory()->for($pr)->for(Supplier::factory())->create([
        'bac_status' => 'pending_evaluation',
        'pr_item_group_id' => $groupId,
    ]);

    return QuotationItem::factory()->for($quotation)->create([
        'purchase_request_item_id' => $item->id,
        'unit_price' => $unitPrice,
        'total_price' => $unitPrice * $item->quantity_requested,
        'is_within_abc' => $unitPrice <= (float) $item->estimated_unit_cost,
    ]);
}
