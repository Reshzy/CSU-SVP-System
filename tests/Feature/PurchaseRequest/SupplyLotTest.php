<?php

use App\Enums\PurchaseRequestStatus;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');

    $this->supply = User::factory()->create();
    $this->supply->assignRole('Supply Officer');

    DepartmentBudget::factory()->for($this->department)->create([
        'fiscal_year' => now()->year,
        'allocated_budget' => 1_000_000,
    ]);

    $this->pr = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'status' => PurchaseRequestStatus::SupplyOfficeReview,
        'estimated_total' => 30,
    ]);

    $this->firstItem = PurchaseRequestItem::factory()->for($this->pr)->create([
        'is_lot' => false,
        'parent_lot_id' => null,
        'quantity_requested' => 2,
        'estimated_unit_cost' => 10,
        'estimated_total_cost' => 20,
    ]);

    $this->secondItem = PurchaseRequestItem::factory()->for($this->pr)->create([
        'is_lot' => false,
        'parent_lot_id' => null,
        'quantity_requested' => 1,
        'estimated_unit_cost' => 10,
        'estimated_total_cost' => 10,
    ]);
});

test('a lot cannot be created from a single standalone item', function () {
    $this->actingAs($this->supply)
        ->post(route('supply.purchase-requests.lots.store', $this->pr), [
            'lot_name' => 'Office kit',
            'item_ids' => [$this->firstItem->id],
        ])
        ->assertSessionHasErrors('item_ids');
});

test('a lot is created from two standalone items', function () {
    $this->actingAs($this->supply)
        ->post(route('supply.purchase-requests.lots.store', $this->pr), [
            'lot_name' => 'Office kit',
            'item_ids' => [$this->firstItem->id, $this->secondItem->id],
        ])
        ->assertRedirect();

    $header = PurchaseRequestItem::query()
        ->where('purchase_request_id', $this->pr->id)
        ->where('is_lot', true)
        ->sole();

    expect($header->lot_name)->toBe('Office kit')
        ->and($header->unit_of_measure)->toBe('lot')
        ->and($header->quantity_requested)->toBe(1)
        ->and((float) $header->estimated_total_cost)->toBe(30.0)
        ->and($this->firstItem->refresh()->parent_lot_id)->toBe($header->id)
        ->and($this->secondItem->refresh()->parent_lot_id)->toBe($header->id);
});

test('destroying a lot restores children as standalones', function () {
    $this->actingAs($this->supply)
        ->post(route('supply.purchase-requests.lots.store', $this->pr), [
            'lot_name' => 'Office kit',
            'item_ids' => [$this->firstItem->id, $this->secondItem->id],
        ])
        ->assertRedirect();

    $header = PurchaseRequestItem::query()
        ->where('purchase_request_id', $this->pr->id)
        ->where('is_lot', true)
        ->sole();

    $this->actingAs($this->supply)
        ->delete(route('supply.purchase-requests.lots.destroy', [
            'purchase_request' => $this->pr,
            'lot' => $header->id,
        ]))
        ->assertRedirect();

    expect(PurchaseRequestItem::query()->whereKey($header->id)->exists())->toBeFalse()
        ->and($this->firstItem->refresh()->parent_lot_id)->toBeNull()
        ->and($this->secondItem->refresh()->parent_lot_id)->toBeNull();
});

test('lots cannot be managed after the request leaves supply review', function () {
    $this->pr->forceFill([
        'status' => PurchaseRequestStatus::BudgetOfficeReview,
    ])->save();

    $this->actingAs($this->supply)
        ->post(route('supply.purchase-requests.lots.store', $this->pr), [
            'lot_name' => 'Office kit',
            'item_ids' => [$this->firstItem->id, $this->secondItem->id],
        ])
        ->assertForbidden();
});
