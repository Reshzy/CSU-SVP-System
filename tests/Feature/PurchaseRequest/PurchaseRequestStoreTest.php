<?php

use App\Enums\PurchaseRequestStatus;
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

    $this->tracker = app(PpmpQuarterlyTracker::class);
    $this->department = Department::factory()->create();
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');

    $this->budget = DepartmentBudget::factory()->for($this->department)->create([
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

/**
 * @return array<string, mixed>
 */
function prPayload(PpmpItem $item, AppItem $catalog, int $quantity = 2): array
{
    return [
        'purpose' => 'Office supplies for the semester',
        'justification' => 'The department has no remaining stock.',
        'items' => [
            [
                'ppmp_item_id' => $item->id,
                'item_code' => $catalog->item_code,
                'item_name' => $catalog->item_name,
                'unit_of_measure' => $catalog->unit_of_measure,
                'quantity_requested' => $quantity,
                'estimated_unit_cost' => 10,
            ],
        ],
    ];
}

test('a dean can open the create form when the plan is validated', function () {
    $this->actingAs($this->dean)
        ->get(route('purchase-requests.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('purchase-requests/create')
            ->has('categorizedItems')
            ->where('currentQuarter', $this->tracker->currentQuarter())
        );
});

test('a dean can list their own requests', function () {
    $this->actingAs($this->dean)
        ->get(route('purchase-requests.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('purchase-requests/index')
            ->where('canCreate', true)
        );
});

test('a dean with a validated plan submits a request at supply office review', function () {
    Notification::fake();

    $supply = User::factory()->create();
    $supply->assignRole('Supply Officer');

    $this->actingAs($this->dean)
        ->post(route('purchase-requests.store'), prPayload($this->ppmpItem, $this->ballpen))
        ->assertRedirect();

    $pr = PurchaseRequest::query()->sole();

    expect($pr->status)->toBe(PurchaseRequestStatus::SupplyOfficeReview)
        ->and($pr->pr_number)->toMatch('/^PR-\d{4}-\d{4}$/')
        ->and($pr->pr_quarter)->toBe($this->tracker->currentQuarter())
        ->and($pr->has_ppmp)->toBeTrue()
        ->and((float) $pr->estimated_total)->toBe(20.0)
        ->and($pr->activities()->pluck('action')->all())->toEqual(['created', 'submitted']);

    $this->budget->refresh();

    expect((float) $this->budget->reserved_budget)->toBe(20.0);

    Notification::assertSentTo($supply, PurchaseRequestSubmitted::class);
});

test('an unvalidated plan cannot be used to create a request', function () {
    $this->ppmp->update(['status' => 'draft', 'validated_at' => null, 'validated_by' => null]);

    $this->actingAs($this->dean)
        ->from(route('purchase-requests.create'))
        ->post(route('purchase-requests.store'), prPayload($this->ppmpItem, $this->ballpen))
        ->assertRedirect(route('purchase-requests.create'))
        ->assertSessionHasErrors('items');

    expect(PurchaseRequest::query()->count())->toBe(0);
});

test('a line with no current-quarter allocation is rejected', function () {
    $quarter = $this->tracker->currentQuarter();
    $this->ppmpItem->update([
        'q1_quantity' => $quarter === 1 ? 0 : 10,
        'q2_quantity' => $quarter === 2 ? 0 : 10,
        'q3_quantity' => $quarter === 3 ? 0 : 10,
        'q4_quantity' => $quarter === 4 ? 0 : 10,
    ]);

    $this->actingAs($this->dean)
        ->from(route('purchase-requests.create'))
        ->post(route('purchase-requests.store'), prPayload($this->ppmpItem, $this->ballpen))
        ->assertSessionHasErrors('items.0.ppmp_item_id');
});

test('requested quantity cannot exceed remaining quantity for the quarter', function () {
    $this->actingAs($this->dean)
        ->from(route('purchase-requests.create'))
        ->post(route('purchase-requests.store'), prPayload($this->ppmpItem, $this->ballpen, 11))
        ->assertSessionHasErrors('items.0.quantity_requested');
});

test('insufficient department budget blocks the request', function () {
    $this->budget->update(['allocated_budget' => 5]);

    $this->actingAs($this->dean)
        ->from(route('purchase-requests.create'))
        ->post(route('purchase-requests.store'), prPayload($this->ppmpItem, $this->ballpen))
        ->assertSessionHasErrors('budget');

    expect(PurchaseRequest::query()->count())->toBe(0);
});

test('a system admin cannot create a purchase request', function () {
    $admin = User::factory()->create(['department_id' => $this->department->id]);
    $admin->assignRole('System Admin');

    $this->actingAs($admin)
        ->post(route('purchase-requests.store'), prPayload($this->ppmpItem, $this->ballpen))
        ->assertForbidden();
});

test('no mail is sent when there is no supply officer', function () {
    Notification::fake();

    $this->actingAs($this->dean)
        ->post(route('purchase-requests.store'), prPayload($this->ppmpItem, $this->ballpen))
        ->assertRedirect();

    Notification::assertNothingSent();
});

test('all supply officers are emailed when a request is submitted', function () {
    Notification::fake();

    $first = User::factory()->create();
    $first->assignRole('Supply Officer');
    $second = User::factory()->create();
    $second->assignRole('Supply Officer');

    $this->actingAs($this->dean)
        ->post(route('purchase-requests.store'), prPayload($this->ppmpItem, $this->ballpen))
        ->assertRedirect();

    Notification::assertSentTo([$first, $second], PurchaseRequestSubmitted::class);
});

test('optional lots persist a header and children', function () {
    $pencil = AppItem::factory()->create(['item_name' => 'Pencil']);
    $pencilLine = PpmpItem::factory()
        ->for($this->ppmp)
        ->plannedEachQuarter(10)
        ->create([
            'app_item_id' => $pencil->id,
            'estimated_unit_cost' => 5,
        ]);

    $this->actingAs($this->dean)
        ->post(route('purchase-requests.store'), [
            'purpose' => 'Classroom kit',
            'justification' => 'Needed for the current quarter.',
            'items' => [
                [
                    'ppmp_item_id' => null,
                    'item_name' => 'Writing kit',
                    'unit_of_measure' => 'lot',
                    'quantity_requested' => 1,
                    'estimated_unit_cost' => 25,
                    'is_lot' => true,
                    'lot_name' => 'Writing kit',
                ],
                [
                    'ppmp_item_id' => $this->ppmpItem->id,
                    'item_code' => $this->ballpen->item_code,
                    'item_name' => $this->ballpen->item_name,
                    'unit_of_measure' => $this->ballpen->unit_of_measure,
                    'quantity_requested' => 2,
                    'estimated_unit_cost' => 10,
                    'parent_lot_index' => 0,
                ],
                [
                    'ppmp_item_id' => $pencilLine->id,
                    'item_code' => $pencil->item_code,
                    'item_name' => $pencil->item_name,
                    'unit_of_measure' => $pencil->unit_of_measure,
                    'quantity_requested' => 1,
                    'estimated_unit_cost' => 5,
                    'parent_lot_index' => 0,
                ],
            ],
        ])
        ->assertRedirect();

    $pr = PurchaseRequest::query()->with('items')->sole();
    $header = $pr->items->firstWhere('is_lot', true);
    $children = $pr->items->where('parent_lot_id', $header->id);

    expect($header)->not->toBeNull()
        ->and($header->unit_of_measure)->toBe('lot')
        ->and($header->quantity_requested)->toBe(1)
        ->and($children)->toHaveCount(2);
});
