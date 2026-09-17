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
use App\Services\AoqService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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

function quoteFor(PurchaseRequest $pr, PurchaseRequestItem $item, float $unitPrice): QuotationItem
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

test('three quotes produce an auto winner on aoq calculation', function () {
    quoteFor($this->purchaseRequest, $this->prItem, 90);
    quoteFor($this->purchaseRequest, $this->prItem, 80);
    $winner = quoteFor($this->purchaseRequest, $this->prItem, 70);

    $result = app(AoqService::class)->calculateWinnersAndTies($this->purchaseRequest);

    expect($result['ties'])->toBeEmpty()
        ->and($result['winners'])->toContain($winner->id)
        ->and($winner->fresh()->is_winner)->toBeTrue()
        ->and(AoqItemDecision::query()->where('decision_type', 'auto')->where('is_active', true)->exists())->toBeTrue();
});

test('over-abc quotes are disqualified and ineligible for aoq', function () {
    $overAbc = quoteFor($this->purchaseRequest, $this->prItem, 150);
    $ok = quoteFor($this->purchaseRequest, $this->prItem, 90);

    $result = app(AoqService::class)->calculateWinnersAndTies($this->purchaseRequest);

    expect($overAbc->fresh()->is_within_abc)->toBeFalse()
        ->and($overAbc->fresh()->disqualification_reason)->toBe('Exceeds ABC')
        ->and($overAbc->quotation->fresh()->bac_status)->toBe('non_compliant')
        ->and($result['winners'])->toContain($ok->id)
        ->and($overAbc->fresh()->is_winner)->toBeFalse();
});

test('unresolved ties block aoq generation', function () {
    Storage::fake('local');

    quoteFor($this->purchaseRequest, $this->prItem, 80);
    quoteFor($this->purchaseRequest, $this->prItem, 80);

    $service = app(AoqService::class);
    $result = $service->calculateWinnersAndTies($this->purchaseRequest);

    expect($result['ties'])->toContain($this->prItem->id);

    expect(fn () => $service->generateAoqDocument($this->purchaseRequest))
        ->toThrow(ValidationException::class);

    expect(AoqGeneration::query()->count())->toBe(0);
});
