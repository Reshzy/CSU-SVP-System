<?php

use App\Models\AoqItemDecision;
use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Supplier;
use App\Models\SupplierWithdrawal;
use App\Models\User;
use App\Services\AoqService;
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

test('withdrawing a winner promotes the next eligible bid and writes an audit row', function () {
    $winner = rankedQuote($this->purchaseRequest, $this->prItem, 70);
    $successor = rankedQuote($this->purchaseRequest, $this->prItem, 80);
    rankedQuote($this->purchaseRequest, $this->prItem, 90);

    $this->actingAs($this->secretariat);
    app(AoqService::class)->calculateWinnersAndTies($this->purchaseRequest);

    expect($winner->fresh()->is_winner)->toBeTrue();

    $this->actingAs($this->secretariat)
        ->post(route('bac.quotation-items.withdraw', $winner), [
            'withdrawal_reason' => 'Supplier cannot deliver the awarded items.',
        ])
        ->assertRedirect();

    expect($winner->fresh()->is_withdrawn)->toBeTrue()
        ->and($winner->fresh()->is_winner)->toBeFalse()
        ->and($successor->fresh()->is_winner)->toBeTrue()
        ->and(AoqItemDecision::query()->where('decision_type', 'withdrawal_succession')->where('is_active', true)->exists())->toBeTrue()
        ->and(SupplierWithdrawal::query()->where('quotation_item_id', $winner->id)->exists())->toBeTrue();
});

test('withdrawing the last eligible bidder marks the item as failed', function () {
    $only = rankedQuote($this->purchaseRequest, $this->prItem, 70);

    $this->actingAs($this->secretariat);
    app(AoqService::class)->calculateWinnersAndTies($this->purchaseRequest);

    $this->actingAs($this->secretariat)
        ->post(route('bac.quotation-items.withdraw', $only), [
            'withdrawal_reason' => 'Sole remaining supplier withdrew after award.',
        ])
        ->assertRedirect();

    expect($this->prItem->fresh()->procurement_status)->toBe('failed')
        ->and(SupplierWithdrawal::query()->where('resulted_in_failure', true)->exists())->toBeTrue();
});

function rankedQuote(PurchaseRequest $pr, PurchaseRequestItem $item, float $unitPrice): QuotationItem
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
