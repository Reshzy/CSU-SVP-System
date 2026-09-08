<?php

use App\Enums\PurchaseRequestStatus;
use App\Models\AppItem;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\PpmpQuarterlyTracker;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->tracker = app(PpmpQuarterlyTracker::class);
    $this->department = Department::factory()->create();
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');

    $this->supply = User::factory()->create();
    $this->supply->assignRole('Supply Officer');

    DepartmentBudget::factory()->for($this->department)->create([
        'fiscal_year' => $this->tracker->currentFiscalYear(),
        'allocated_budget' => 1_000_000,
    ]);

    $this->ppmp = Ppmp::factory()
        ->validated()
        ->for($this->department)
        ->forFiscalYear($this->tracker->currentFiscalYear())
        ->create();

    $this->ballpen = AppItem::factory()->create([
        'item_code' => 'BP-001',
        'item_name' => 'Ballpen, black',
        'unit_price' => 10.00,
    ]);

    $this->ppmpItem = PpmpItem::factory()
        ->for($this->ppmp)
        ->plannedEachQuarter(10)
        ->create([
            'app_item_id' => $this->ballpen->id,
            'estimated_unit_cost' => 10,
        ]);
});

test('a returned request can be replaced and the original is archived', function () {
    Notification::fake();

    $this->actingAs($this->dean)
        ->post(route('purchase-requests.store'), [
            'purpose' => 'Office supplies for the semester',
            'justification' => 'The department has no remaining stock.',
            'items' => [
                [
                    'ppmp_item_id' => $this->ppmpItem->id,
                    'item_code' => $this->ballpen->item_code,
                    'item_name' => $this->ballpen->item_name,
                    'unit_of_measure' => $this->ballpen->unit_of_measure,
                    'quantity_requested' => 2,
                    'estimated_unit_cost' => 10,
                ],
            ],
        ])
        ->assertRedirect();

    $original = PurchaseRequest::query()->sole();

    $this->actingAs($this->supply)
        ->post(route('supply.purchase-requests.status', $original), [
            'action' => 'return',
            'remarks' => 'Need a clearer specification.',
        ])
        ->assertRedirect();

    $this->actingAs($this->dean)
        ->post(route('purchase-requests.replacement.store', $original), [
            'purpose' => 'Office supplies for the semester (revised)',
            'justification' => 'Updated specifications after supply returned the request.',
            'items' => [
                [
                    'ppmp_item_id' => $this->ppmpItem->id,
                    'item_code' => $this->ballpen->item_code,
                    'item_name' => $this->ballpen->item_name,
                    'unit_of_measure' => $this->ballpen->unit_of_measure,
                    'quantity_requested' => 2,
                    'estimated_unit_cost' => 10,
                ],
            ],
        ])
        ->assertRedirect();

    $original->refresh();
    $replacement = PurchaseRequest::query()->whereKey($original->replaced_by_pr_id)->sole();

    expect($original->is_archived)->toBeTrue()
        ->and($original->replaced_by_pr_id)->toBe($replacement->id)
        ->and($replacement->status)->toBe(PurchaseRequestStatus::SupplyOfficeReview)
        ->and($replacement->replaces_pr_id)->toBe($original->id)
        ->and($replacement->purpose)->toBe('Office supplies for the semester (revised)');
});
