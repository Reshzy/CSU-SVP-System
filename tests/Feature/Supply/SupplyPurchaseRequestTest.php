<?php

use App\Models\AppItem;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Notifications\PurchaseRequestActionRequired;
use App\Notifications\PurchaseRequestStatusUpdated;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->requester = User::factory()->create(['department_id' => $this->department->id]);
    $this->requester->assignRole('Dean');

    $this->supplyOfficer = User::factory()->create();
    $this->supplyOfficer->assignRole('Supply Officer');

    $this->budgetOfficer = User::factory()->create();
    $this->budgetOfficer->assignRole('Budget Office');

    DepartmentBudget::factory()->create([
        'department_id' => $this->department->id,
        'fiscal_year' => (int) now()->format('Y'),
        'allocated_budget' => 100_000,
    ]);

    $this->purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->requester->id,
        'status' => 'supply_office_review',
        'estimated_total' => 200,
    ]);

    PurchaseRequestItem::factory()->for($this->purchaseRequest)->create([
        'item_name' => 'Item A',
        'quantity_requested' => 2,
        'estimated_unit_cost' => 50,
        'estimated_total_cost' => 100,
    ]);
    PurchaseRequestItem::factory()->for($this->purchaseRequest)->create([
        'item_name' => 'Item B',
        'quantity_requested' => 2,
        'estimated_unit_cost' => 50,
        'estimated_total_cost' => 100,
    ]);
});

test('activate moves the purchase request to budget_office_review and creates a pending earmark step', function () {
    Notification::fake();

    $this->actingAs($this->supplyOfficer)
        ->post(route('supply.purchase-requests.status', $this->purchaseRequest), [
            'action' => 'activate',
        ])
        ->assertRedirect();

    $this->purchaseRequest->refresh();

    expect($this->purchaseRequest->status)->toBe('budget_office_review');

    $approval = WorkflowApproval::query()
        ->where('purchase_request_id', $this->purchaseRequest->id)
        ->where('step_name', 'budget_office_earmarking')
        ->first();

    expect($approval)->not->toBeNull()
        ->and($approval->status)->toBe('pending')
        ->and($approval->approver_id)->toBe($this->budgetOfficer->id);

    Notification::assertSentTo($this->budgetOfficer, PurchaseRequestActionRequired::class);
    Notification::assertSentTo($this->requester, PurchaseRequestStatusUpdated::class);
});

test('creating a lot requires at least two standalone items', function () {
    $item = $this->purchaseRequest->items()->first();

    $this->actingAs($this->supplyOfficer)
        ->post(route('supply.purchase-requests.lots.store', $this->purchaseRequest), [
            'lot_name' => 'Office lot',
            'item_ids' => [$item->id],
        ])
        ->assertSessionHasErrors('item_ids');

    expect($this->purchaseRequest->items()->where('is_lot', true)->count())->toBe(0);
});

test('creating a lot with two standalone items succeeds', function () {
    $ids = $this->purchaseRequest->items()->pluck('id')->all();

    $this->actingAs($this->supplyOfficer)
        ->post(route('supply.purchase-requests.lots.store', $this->purchaseRequest), [
            'lot_name' => 'Office lot',
            'item_ids' => $ids,
        ])
        ->assertRedirect();

    expect($this->purchaseRequest->items()->where('is_lot', true)->count())->toBe(1)
        ->and($this->purchaseRequest->items()->whereNotNull('parent_lot_id')->count())->toBe(2);
});

test('replacement after return archives the original purchase request', function () {
    Notification::fake();

    $this->purchaseRequest->update([
        'status' => 'returned_by_supply',
        'return_remarks' => 'Need clearer specs',
    ]);

    // Seed a validated PPMP path via store request validation — use factory items on a fresh store through replacement controller needs PPMP.
    // Simpler: manually create replacement linkage the controller would create after a successful store by hitting replacement with mocked validated path.
    // For this test we exercise the archive path by posting through a minimal setup using the same items already on the PR's department PPMP from store tests pattern.

    $fiscalYear = (int) now()->format('Y');
    $quarter = (int) ceil((int) now()->format('n') / 3);

    $ppmp = Ppmp::factory()->forFiscalYear($fiscalYear)->validated()->create([
        'department_id' => $this->department->id,
    ]);

    $quantities = [0, 0, 0, 0];
    $quantities[$quarter - 1] = 50;

    $catalog = AppItem::factory()->create(['fiscal_year' => $fiscalYear]);
    $ppmpItem = PpmpItem::factory()->for($ppmp)->create([
        'app_item_id' => $catalog->id,
        'q1_quantity' => $quantities[0],
        'q2_quantity' => $quantities[1],
        'q3_quantity' => $quantities[2],
        'q4_quantity' => $quantities[3],
        'total_quantity' => 50,
        'estimated_unit_cost' => 10,
        'estimated_total_cost' => 500,
    ]);

    $this->actingAs($this->requester)
        ->post(route('purchase-requests.replacement.store', $this->purchaseRequest), [
            'pr_title' => 'Replacement request',
            'purpose' => 'Revised specs',
            'date_needed' => now()->addWeek()->toDateString(),
            'fund_cluster_code' => '01',
            'items' => [
                [
                    'ppmp_item_id' => $ppmpItem->id,
                    'quantity_requested' => 2,
                ],
            ],
        ])
        ->assertRedirect();

    $this->purchaseRequest->refresh();
    $replacement = PurchaseRequest::query()->where('replaces_pr_id', $this->purchaseRequest->id)->first();

    expect($this->purchaseRequest->is_archived)->toBeTrue()
        ->and($replacement)->not->toBeNull()
        ->and($replacement->status)->toBe('supply_office_review')
        ->and($this->purchaseRequest->replaced_by_pr_id)->toBe($replacement->id);
});
