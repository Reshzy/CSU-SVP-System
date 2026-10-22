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
        ->and((float) $purchaseRequest->estimated_total)->toBe(100.0)
        ->and($purchaseRequest->date_needed)->toBeNull()
        ->and($purchaseRequest->fund_cluster_code)->toBeNull()
        ->and($purchaseRequest->funding_source)->toBeNull();

    $response->assertRedirect(route('purchase-requests.show', $purchaseRequest));

    Notification::assertSentTo($this->supplyOfficer, PurchaseRequestSubmitted::class);
});

test('an unvalidated ppmp cannot be used to store a purchase request', function () {
    $this->ppmp->update(['status' => 'draft', 'validated_at' => null, 'validated_by' => null]);

    $this->actingAs($this->dean)
        ->post(route('purchase-requests.store'), [
            'pr_title' => 'Office supplies',
            'purpose' => 'For classes',
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

test('creating a purchase request with a lot header does not require a ppmp item on the lot row', function () {
    $secondCatalog = AppItem::factory()->create([
        'fiscal_year' => $this->fiscalYear,
        'item_code' => 'BP-002',
        'item_name' => 'Notebook',
        'unit_price' => 40.00,
    ]);

    $quantities = [0, 0, 0, 0];
    $quantities[$this->quarter - 1] = 20;

    $secondPpmpItem = PpmpItem::factory()->for($this->ppmp)->create([
        'app_item_id' => $secondCatalog->id,
        'q1_quantity' => $quantities[0],
        'q2_quantity' => $quantities[1],
        'q3_quantity' => $quantities[2],
        'q4_quantity' => $quantities[3],
        'total_quantity' => 20,
        'estimated_unit_cost' => 40.00,
        'estimated_total_cost' => 800.00,
    ]);

    Notification::fake();

    $this->actingAs($this->dean)->post(route('purchase-requests.store'), [
        'pr_title' => 'Lot request',
        'purpose' => 'Grouped supplies',
        'items' => [
            [
                'is_lot' => true,
                'lot_name' => 'Writing lot',
            ],
            [
                'ppmp_item_id' => $this->ppmpItem->id,
                'quantity_requested' => 2,
                'parent_lot_index' => 0,
            ],
            [
                'ppmp_item_id' => $secondPpmpItem->id,
                'quantity_requested' => 2,
                'parent_lot_index' => 0,
            ],
        ],
    ])->assertRedirect();

    $purchaseRequest = PurchaseRequest::query()->first();

    expect($purchaseRequest)->not->toBeNull()
        ->and($purchaseRequest->items()->where('is_lot', true)->count())->toBe(1)
        ->and($purchaseRequest->items()->whereNotNull('parent_lot_id')->count())->toBe(2);
});

test('system admin cannot create purchase requests', function () {
    $admin = User::factory()->create(['department_id' => $this->department->id]);
    $admin->assignRole('System Admin');

    $this->actingAs($admin)
        ->post(route('purchase-requests.store'), [
            'pr_title' => 'Blocked',
            'purpose' => 'Should fail',
            'items' => [
                [
                    'ppmp_item_id' => $this->ppmpItem->id,
                    'quantity_requested' => 1,
                ],
            ],
        ])
        ->assertForbidden();
});

test('creating a purchase request offers unused earlier-quarter quantity', function () {
    $this->travelTo(now()->setDate((int) now()->year, 5, 15));

    $this->ppmpItem->update([
        'q1_quantity' => 10,
        'q2_quantity' => 0,
        'q3_quantity' => 4,
        'q4_quantity' => 0,
        'total_quantity' => 14,
    ]);

    $this->actingAs($this->dean)
        ->get(route('purchase-requests.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('purchase-requests/create')
            ->where('availableItems.0.remaining_qty', 10)
            ->where('availableItems.0.later_remaining', 4)
            ->where('canUpdatePpmp', true)
        );
});

test('unused quantity from an earlier quarter can be requested now', function () {
    Notification::fake();
    $this->travelTo(now()->setDate((int) now()->year, 5, 15));

    $this->ppmpItem->update([
        'q1_quantity' => 10,
        'q2_quantity' => 0,
        'q3_quantity' => 0,
        'q4_quantity' => 0,
        'total_quantity' => 10,
        'estimated_total_cost' => 250.00,
    ]);

    $this->actingAs($this->dean)
        ->post(route('purchase-requests.store'), [
            'pr_title' => 'Office supplies',
            'purpose' => 'For classes',
            'items' => [
                [
                    'ppmp_item_id' => $this->ppmpItem->id,
                    'quantity_requested' => 4,
                ],
            ],
        ])
        ->assertRedirect();

    $line = PurchaseRequest::query()->first()?->items()->first();

    expect($line)->not->toBeNull()
        ->and($line->ppmp_quarter)->toBe(2)
        ->and($line->ppmp_remaining_qty_at_creation)->toBe(10);
});

test('quantity planned only for a later quarter cannot be requested', function () {
    $this->travelTo(now()->setDate((int) now()->year, 5, 15));

    $this->ppmpItem->update([
        'q1_quantity' => 0,
        'q2_quantity' => 0,
        'q3_quantity' => 10,
        'q4_quantity' => 0,
        'total_quantity' => 10,
    ]);

    $this->actingAs($this->dean)
        ->post(route('purchase-requests.store'), [
            'pr_title' => 'Office supplies',
            'purpose' => 'For classes',
            'items' => [
                [
                    'ppmp_item_id' => $this->ppmpItem->id,
                    'quantity_requested' => 1,
                ],
            ],
        ])
        ->assertSessionHasErrors([
            'items.0.quantity_requested' => 'No remaining PPMP quantity is available this quarter (including unused earlier quarters). Realign later-quarter quantities on the PPMP to request this item now.',
        ]);

    expect(PurchaseRequest::query()->count())->toBe(0);
});

test('later-quarter quantity can be requested after it is realigned', function () {
    Notification::fake();
    $this->travelTo(now()->setDate((int) now()->year, 5, 15));

    $this->ppmpItem->update([
        'q1_quantity' => 0,
        'q2_quantity' => 0,
        'q3_quantity' => 10,
        'q4_quantity' => 0,
        'total_quantity' => 10,
        'estimated_total_cost' => 250.00,
    ]);

    $this->actingAs($this->dean)
        ->post(route('ppmp.items.realign', ['ppmp' => $this->ppmp, 'item' => $this->ppmpItem]))
        ->assertRedirect();

    $this->actingAs($this->dean)
        ->post(route('purchase-requests.store'), [
            'pr_title' => 'Office supplies',
            'purpose' => 'For classes',
            'items' => [
                [
                    'ppmp_item_id' => $this->ppmpItem->id,
                    'quantity_requested' => 4,
                ],
            ],
        ])
        ->assertRedirect();

    expect(PurchaseRequest::query()->count())->toBe(1)
        ->and($this->ppmpItem->fresh()->q2_quantity)->toBe(10)
        ->and($this->ppmpItem->fresh()->q3_quantity)->toBe(0);
});

test('requested quantity cannot exceed usable leftover this quarter', function () {
    $this->travelTo(now()->setDate((int) now()->year, 5, 15));

    $this->ppmpItem->update([
        'q1_quantity' => 5,
        'q2_quantity' => 0,
        'q3_quantity' => 0,
        'q4_quantity' => 0,
        'total_quantity' => 5,
    ]);

    $this->actingAs($this->dean)
        ->post(route('purchase-requests.store'), [
            'pr_title' => 'Office supplies',
            'purpose' => 'For classes',
            'items' => [
                [
                    'ppmp_item_id' => $this->ppmpItem->id,
                    'quantity_requested' => 6,
                ],
            ],
        ])
        ->assertSessionHasErrors([
            'items.0.quantity_requested' => 'Requested quantity exceeds remaining PPMP quantity (5) available this quarter.',
        ]);

    expect(PurchaseRequest::query()->count())->toBe(0);
});
