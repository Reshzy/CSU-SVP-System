<?php

use App\Models\AoqGeneration;
use App\Models\AoqItemDecision;
use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->secretariat = User::factory()->create();
    $this->secretariat->assignRole('BAC Secretariat');

    $this->purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'status' => 'bac_evaluation',
        'procurement_method' => 'small_value_procurement',
        'resolution_number' => 'RES-0926-0001',
    ]);

    $this->prItem = PurchaseRequestItem::factory()->for($this->purchaseRequest)->create([
        'item_name' => 'Bond paper',
        'quantity_requested' => 10,
        'estimated_unit_cost' => 100,
        'estimated_total_cost' => 1000,
        'parent_lot_id' => null,
        'is_lot' => false,
    ]);
});

test('storing a quotation is rejected when the procurement method is empty', function () {
    $this->purchaseRequest->update(['procurement_method' => null]);

    $supplier = Supplier::factory()->create();

    $this->actingAs($this->secretariat)
        ->post(route('bac.quotations.store', $this->purchaseRequest), [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'purchase_request_item_id' => $this->prItem->id,
                    'unit_price' => 90,
                ],
            ],
        ])
        ->assertSessionHasErrors('procurement_method');

    expect(Quotation::query()->count())->toBe(0);
});

test('resolving a tie allows aoq generation', function () {
    $first = quoteViaHttpSetup($this->purchaseRequest, $this->prItem, 80);
    $second = quoteViaHttpSetup($this->purchaseRequest, $this->prItem, 80);

    $this->actingAs($this->secretariat)
        ->get(route('bac.quotations.aoq', $this->purchaseRequest))
        ->assertOk();

    $this->actingAs($this->secretariat)
        ->post(route('bac.quotations.aoq.resolve-tie', $this->purchaseRequest), [
            'purchase_request_item_id' => $this->prItem->id,
            'winning_quotation_item_id' => $first->id,
            'justification' => 'Lowest documented delivery lead time.',
        ])
        ->assertRedirect();

    expect(AoqItemDecision::query()->where('decision_type', 'tie_resolution')->where('is_active', true)->exists())->toBeTrue()
        ->and($first->fresh()->is_winner)->toBeTrue()
        ->and($second->fresh()->is_winner)->toBeFalse();

    $this->actingAs($this->secretariat)
        ->post(route('bac.quotations.aoq.generate', $this->purchaseRequest))
        ->assertRedirect();

    expect(AoqGeneration::query()->where('purchase_request_id', $this->purchaseRequest->id)->count())->toBe(1);
});

test('unresolved ties block aoq generation over http', function () {
    quoteViaHttpSetup($this->purchaseRequest, $this->prItem, 80);
    quoteViaHttpSetup($this->purchaseRequest, $this->prItem, 80);

    $this->actingAs($this->secretariat)
        ->from(route('bac.quotations.aoq', $this->purchaseRequest))
        ->post(route('bac.quotations.aoq.generate', $this->purchaseRequest))
        ->assertRedirect(route('bac.quotations.aoq', $this->purchaseRequest))
        ->assertSessionHasErrors('aoq');

    expect(AoqGeneration::query()->count())->toBe(0);
});

function quoteViaHttpSetup(PurchaseRequest $pr, PurchaseRequestItem $item, float $unitPrice): QuotationItem
{
    $quotation = Quotation::factory()->for($pr)->for(Supplier::factory())->create([
        'bac_status' => 'pending_evaluation',
    ]);

    return QuotationItem::factory()->for($quotation)->create([
        'purchase_request_item_id' => $item->id,
        'unit_price' => $unitPrice,
        'total_price' => $unitPrice * $item->quantity_requested,
        'is_within_abc' => $unitPrice <= (float) $item->estimated_unit_cost,
    ]);
}
