<?php

use App\Models\AppItem;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Services\PpmpQuarterlyTracker;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Notification::fake();

    $this->department = Department::factory()->create();
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');

    $this->supplyOfficer = User::factory()->create();
    $this->supplyOfficer->assignRole('Supply Officer');

    $tracker = app(PpmpQuarterlyTracker::class);
    $this->quarter = $tracker->currentQuarter();
    $fiscalYear = $tracker->currentFiscalYear();

    $catalogItem = AppItem::factory()->create([
        'fiscal_year' => $fiscalYear,
        'item_name' => 'Ballpen',
        'unit_price' => 25.00,
    ]);

    $ppmp = Ppmp::factory()->forFiscalYear($fiscalYear)->validated()->create([
        'department_id' => $this->department->id,
    ]);

    $quantities = [0, 0, 0, 0];
    $quantities[$this->quarter - 1] = 10;

    $this->ppmpItem = PpmpItem::factory()->for($ppmp)->create([
        'app_item_id' => $catalogItem->id,
        'q1_quantity' => $quantities[0],
        'q2_quantity' => $quantities[1],
        'q3_quantity' => $quantities[2],
        'q4_quantity' => $quantities[3],
        'total_quantity' => 10,
        'estimated_unit_cost' => 25.00,
        'estimated_total_cost' => 250.00,
    ]);

    DepartmentBudget::factory()->create([
        'department_id' => $this->department->id,
        'fiscal_year' => $fiscalYear,
        'allocated_budget' => 100_000,
        'reserved_budget' => 0,
        'utilized_budget' => 0,
    ]);

    $this->returned = PurchaseRequest::factory()->withStatus('returned_by_supply')->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'return_remarks' => 'Specify the ballpen colour.',
        'returned_by' => $this->supplyOfficer->id,
        'returned_at' => now(),
    ]);

    PurchaseRequestItem::factory()->for($this->returned)->for($this->ppmpItem, 'ppmpItem')->create([
        'ppmp_quarter' => $this->quarter,
        'quantity_requested' => 10,
        'is_lot' => false,
        'parent_lot_id' => null,
    ]);

    $this->replacementPayload = [
        'pr_title' => 'Ballpens, black',
        'purpose' => 'For classes',
        'items' => [
            ['ppmp_item_id' => $this->ppmpItem->id, 'quantity_requested' => 10],
        ],
    ];
});

test('the requester sees the return remarks and can start a replacement', function () {
    $this->actingAs($this->dean)
        ->get(route('purchase-requests.show', $this->returned))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('canCreateReplacement', true)
            ->where('purchaseRequest.return_remarks', 'Specify the ballpen colour.')
            ->where('purchaseRequest.returned_by.name', $this->supplyOfficer->name)
        );
});

test('the replacement form is prefilled and counts the returned quantity as available', function () {
    $this->actingAs($this->dean)
        ->get(route('purchase-requests.replacement.create', $this->returned))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('purchase-requests/create')
            ->where('replacing.id', $this->returned->id)
            ->where('replacing.return_remarks', 'Specify the ballpen colour.')
            ->where('replacing.lines.0.ppmp_item_id', $this->ppmpItem->id)
            ->where('replacing.lines.0.quantity_requested', 10)
            ->where('availableItems.0.remaining_qty', 10)
            ->where('signatories.requestedBy.name', $this->dean->name)
            ->where('signatories.isRequester', true)
        );
});

test('a replacement can request the full quantity the returned request held', function () {
    $this->actingAs($this->dean)
        ->post(route('purchase-requests.replacement.store', $this->returned), $this->replacementPayload)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $replacement = PurchaseRequest::query()->where('replaces_pr_id', $this->returned->id)->first();
    $original = $this->returned->fresh();

    expect($replacement)->not->toBeNull()
        ->and($replacement->status)->toBe('supply_office_review')
        ->and($replacement->items()->first()->quantity_requested)->toBe(10)
        ->and($original->is_archived)->toBeTrue()
        ->and($original->replaced_by_pr_id)->toBe($replacement->id)
        ->and($this->ppmpItem->fresh()->getUsableQuantity($this->quarter))->toBe(0);
});

test('a regular request still cannot use quantity held by a returned request', function () {
    $this->actingAs($this->dean)
        ->post(route('purchase-requests.store'), $this->replacementPayload)
        ->assertSessionHasErrors('items.0.quantity_requested');
});

test('only the requester of an unarchived returned request may replace it', function (string $case) {
    $actor = $this->dean;

    match ($case) {
        'other user' => $actor = tap(User::factory()->create(['department_id' => $this->department->id]))->assignRole('Dean'),
        'not returned' => $this->returned->update(['status' => 'supply_office_review']),
        'archived' => $this->returned->update(['is_archived' => true]),
    };

    $this->actingAs($actor)
        ->get(route('purchase-requests.replacement.create', $this->returned))
        ->assertForbidden();

    $this->actingAs($actor)
        ->post(route('purchase-requests.replacement.store', $this->returned), $this->replacementPayload)
        ->assertForbidden();

    expect(PurchaseRequest::query()->where('replaces_pr_id', $this->returned->id)->exists())->toBeFalse();
})->with(['other user', 'not returned', 'archived']);
