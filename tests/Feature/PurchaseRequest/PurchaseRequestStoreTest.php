<?php

use App\Models\AppItem;
use App\Models\Department;
use App\Models\DepartmentBudget;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Notifications\PurchaseRequestSubmitted;
use App\Services\PpmpQuarterlyTracker;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');

    $this->supplyOfficer = User::factory()->create();
    $this->supplyOfficer->assignRole('Supply Officer');

    $tracker = app(PpmpQuarterlyTracker::class);
    $this->fiscalYear = $tracker->currentFiscalYear();
    $this->quarter = $tracker->currentQuarter();

    $this->catalogItem = AppItem::factory()->create([
        'fiscal_year' => $this->fiscalYear,
        'item_code' => 'BP-001',
        'item_name' => 'Ballpen',
        'unit_price' => 25.00,
    ]);

    $this->ppmp = Ppmp::factory()->forFiscalYear($this->fiscalYear)->validated()->create([
        'department_id' => $this->department->id,
    ]);

    $quantities = [0, 0, 0, 0];
    $quantities[$this->quarter - 1] = 20;

    $this->ppmpItem = PpmpItem::factory()->for($this->ppmp)->create([
        'app_item_id' => $this->catalogItem->id,
        'q1_quantity' => $quantities[0],
        'q2_quantity' => $quantities[1],
        'q3_quantity' => $quantities[2],
        'q4_quantity' => $quantities[3],
        'total_quantity' => 20,
        'estimated_unit_cost' => 25.00,
        'estimated_total_cost' => 500.00,
    ]);

    DepartmentBudget::factory()->create([
        'department_id' => $this->department->id,
        'fiscal_year' => $this->fiscalYear,
        'allocated_budget' => 100_000,
        'reserved_budget' => 0,
        'utilized_budget' => 0,
    ]);
});

test('storing a purchase request yields supply_office_review and a PR number', function () {
    Notification::fake();

    $response = $this->actingAs($this->dean)->post(route('purchase-requests.store'), [
        'pr_title' => 'Office supplies',
        'purpose' => 'For classes',
        'justification' => 'Needed this quarter',
        'date_needed' => now()->addWeek()->toDateString(),
        'fund_cluster_code' => '01',
        'items' => [
            [
                'ppmp_item_id' => $this->ppmpItem->id,
                'quantity_requested' => 4,
            ],
        ],
    ]);

    $purchaseRequest = PurchaseRequest::query()->first();

    expect($purchaseRequest)->not->toBeNull()
        ->and($purchaseRequest->status)->toBe('supply_office_review')
        ->and($purchaseRequest->pr_number)->toMatch('/^PR-\d{4}-\d{4}$/')
        ->and((float) $purchaseRequest->estimated_total)->toBe(100.0);

    $response->assertRedirect(route('purchase-requests.show', $purchaseRequest));

    Notification::assertSentTo($this->supplyOfficer, PurchaseRequestSubmitted::class);
});

test('an unvalidated ppmp cannot be used to store a purchase request', function () {
    $this->ppmp->update(['status' => 'draft', 'validated_at' => null, 'validated_by' => null]);

    $this->actingAs($this->dean)
        ->post(route('purchase-requests.store'), [
            'pr_title' => 'Office supplies',
            'purpose' => 'For classes',
            'date_needed' => now()->addWeek()->toDateString(),
            'fund_cluster_code' => '01',
            'items' => [
                [
                    'ppmp_item_id' => $this->ppmpItem->id,
                    'quantity_requested' => 1,
                ],
            ],
        ])
        ->assertSessionHasErrors('items');

    expect(PurchaseRequest::query()->count())->toBe(0);
});

test('creating a purchase request reserves department budget', function () {
    Notification::fake();

    $this->actingAs($this->dean)->post(route('purchase-requests.store'), [
        'pr_title' => 'Office supplies',
        'purpose' => 'For classes',
        'date_needed' => now()->addWeek()->toDateString(),
        'fund_cluster_code' => '01',
        'items' => [
            [
                'ppmp_item_id' => $this->ppmpItem->id,
                'quantity_requested' => 4,
            ],
        ],
    ])->assertRedirect();

    $budget = DepartmentBudget::query()
        ->where('department_id', $this->department->id)
        ->where('fiscal_year', $this->fiscalYear)
        ->first();

    expect((float) $budget->reserved_budget)->toBe(100.0);
});

test('system admin cannot create purchase requests', function () {
    $admin = User::factory()->create(['department_id' => $this->department->id]);
    $admin->assignRole('System Admin');

    $this->actingAs($admin)
        ->post(route('purchase-requests.store'), [
            'pr_title' => 'Blocked',
            'purpose' => 'Should fail',
            'date_needed' => now()->addWeek()->toDateString(),
            'fund_cluster_code' => '01',
            'items' => [
                [
                    'ppmp_item_id' => $this->ppmpItem->id,
                    'quantity_requested' => 1,
                ],
            ],
        ])
        ->assertForbidden();
});
