<?php

use App\Enums\PpmpStatus;
use App\Models\AppItem;
use App\Models\Department;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create();
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');

    $this->ballpen = AppItem::factory()->create([
        'item_code' => 'BP-001',
        'item_name' => 'Ballpen, black',
        'unit_price' => 10.00,
    ]);
});

test('a department holds one plan per fiscal year', function () {
    $first = Ppmp::getOrCreateForDepartment($this->department->id, 2025);
    $second = Ppmp::getOrCreateForDepartment($this->department->id, 2025);

    expect($second->id)->toBe($first->id)
        ->and($first->status)->toBe(PpmpStatus::Draft)
        ->and(Ppmp::count())->toBe(1);
});

test('the same department can hold plans for different years', function () {
    Ppmp::getOrCreateForDepartment($this->department->id, 2025);
    Ppmp::getOrCreateForDepartment($this->department->id, 2026);

    expect(Ppmp::count())->toBe(2);
});

test('saving a plan stores its lines and header total', function () {
    $this->actingAs($this->dean)
        ->post(route('ppmp.store'), [
            'fiscal_year' => 2025,
            'items' => [
                [
                    'app_item_id' => $this->ballpen->id,
                    'q1_quantity' => 5,
                    'q2_quantity' => 5,
                    'q3_quantity' => 0,
                    'q4_quantity' => 0,
                ],
            ],
        ])
        ->assertRedirect();

    $ppmp = Ppmp::sole();
    $item = $ppmp->items()->sole();

    expect($ppmp->fiscal_year)->toBe(2025)
        ->and($ppmp->department_id)->toBe($this->department->id)
        ->and($item->total_quantity)->toBe(10)
        ->and((float) $item->estimated_total_cost)->toBe(100.00)
        ->and((float) $ppmp->total_estimated_cost)->toBe(100.00);
});

test('items left at zero across every quarter are kept off the plan', function () {
    $pencil = AppItem::factory()->create(['unit_price' => 5.00]);

    $this->actingAs($this->dean)
        ->post(route('ppmp.store'), [
            'fiscal_year' => 2025,
            'items' => [
                ['app_item_id' => $this->ballpen->id, 'q1_quantity' => 5, 'q2_quantity' => 0, 'q3_quantity' => 0, 'q4_quantity' => 0],
                ['app_item_id' => $pencil->id, 'q1_quantity' => 0, 'q2_quantity' => 0, 'q3_quantity' => 0, 'q4_quantity' => 0],
            ],
        ]);

    expect(Ppmp::sole()->items()->pluck('app_item_id')->all())->toEqual([$this->ballpen->id]);
});

test('a plan with no quantity anywhere is rejected', function () {
    $this->actingAs($this->dean)
        ->post(route('ppmp.store'), [
            'fiscal_year' => 2025,
            'items' => [
                ['app_item_id' => $this->ballpen->id, 'q1_quantity' => 0, 'q2_quantity' => 0, 'q3_quantity' => 0, 'q4_quantity' => 0],
            ],
        ])
        ->assertSessionHasErrors('items');
});

test('an unpriced catalog item needs a unit price on the line', function () {
    $software = AppItem::factory()->unpriced()->create();

    $this->actingAs($this->dean)
        ->post(route('ppmp.store'), [
            'fiscal_year' => 2025,
            'items' => [
                ['app_item_id' => $software->id, 'q1_quantity' => 3, 'q2_quantity' => 0, 'q3_quantity' => 0, 'q4_quantity' => 0],
            ],
        ])
        ->assertSessionHasErrors('items.0.custom_unit_price');

    expect(PpmpItem::count())->toBe(0);
});

test('an unpriced catalog item is costed from the price entered on the line', function () {
    $software = AppItem::factory()->unpriced()->create();

    $this->actingAs($this->dean)
        ->post(route('ppmp.store'), [
            'fiscal_year' => 2025,
            'items' => [
                [
                    'app_item_id' => $software->id,
                    'q1_quantity' => 3,
                    'q2_quantity' => 0,
                    'q3_quantity' => 0,
                    'q4_quantity' => 0,
                    'custom_unit_price' => '1500.00',
                ],
            ],
        ]);

    $item = PpmpItem::sole();

    expect((float) $item->estimated_unit_cost)->toBe(1500.00)
        ->and((float) $item->estimated_total_cost)->toBe(4500.00);
});

test('updating a plan replaces every line rather than adding to them', function () {
    $ppmp = Ppmp::factory()->for($this->department)->forFiscalYear(2025)->create();
    PpmpItem::factory()->for($ppmp)->create(['app_item_id' => $this->ballpen->id]);

    $pencil = AppItem::factory()->create(['unit_price' => 5.00]);

    $this->actingAs($this->dean)
        ->put(route('ppmp.update', $ppmp), [
            'items' => [
                ['app_item_id' => $pencil->id, 'q1_quantity' => 2, 'q2_quantity' => 0, 'q3_quantity' => 0, 'q4_quantity' => 0],
            ],
        ])
        ->assertRedirect(route('ppmp.summary', $ppmp));

    expect($ppmp->items()->pluck('app_item_id')->all())->toEqual([$pencil->id]);
});

test('validating a plan records who signed it off and when', function () {
    $ppmp = Ppmp::factory()->for($this->department)->create();
    PpmpItem::factory()->for($ppmp)->create();

    $this->actingAs($this->dean)
        ->post(route('ppmp.validate', $ppmp))
        ->assertRedirect(route('ppmp.summary', $ppmp));

    $ppmp->refresh();

    expect($ppmp->status)->toBe(PpmpStatus::Validated)
        ->and($ppmp->validated_by)->toBe($this->dean->id)
        ->and($ppmp->validated_at)->not->toBeNull();
});

test('an empty plan cannot be validated', function () {
    $ppmp = Ppmp::factory()->for($this->department)->create();

    $this->actingAs($this->dean)->post(route('ppmp.validate', $ppmp));

    expect($ppmp->fresh()->status)->toBe(PpmpStatus::Draft);
});

test('a plan belonging to another department is out of reach', function () {
    $otherPpmp = Ppmp::factory()->create();

    $this->actingAs($this->dean)->get(route('ppmp.summary', $otherPpmp))->assertForbidden();
    $this->actingAs($this->dean)->get(route('ppmp.edit', $otherPpmp))->assertForbidden();
    $this->actingAs($this->dean)->post(route('ppmp.validate', $otherPpmp))->assertForbidden();
});

test('the executive officer can reach any departments plan', function () {
    $executiveOfficer = User::factory()->create();
    $executiveOfficer->assignRole('Executive Officer');

    $ppmp = Ppmp::factory()->for($this->department)->create();

    $this->actingAs($executiveOfficer)->get(route('ppmp.summary', $ppmp))->assertOk();
});

test('the plan index lists only the signed-in users department', function () {
    Ppmp::factory()->for($this->department)->forFiscalYear(2025)->create();
    Ppmp::factory()->create();

    $this->actingAs($this->dean)
        ->get(route('ppmp.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ppmp/index')
            ->count('ppmps.data', 1)
            ->where('ppmps.data.0.fiscal_year', 2025)
        );
});

test('opening the planner creates this years plan when the department has none', function () {
    $this->actingAs($this->dean)
        ->get(route('ppmp.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ppmp/create'));

    expect(Ppmp::sole()->fiscal_year)->toBe((int) date('Y'));
});

test('the editor offers the whole catalog with saved quantities filled in', function () {
    $ppmp = Ppmp::factory()->for($this->department)->create();
    PpmpItem::factory()->for($ppmp)->create([
        'app_item_id' => $this->ballpen->id,
        'q1_quantity' => 7,
    ]);

    $this->actingAs($this->dean)
        ->get(route('ppmp.edit', $ppmp))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ppmp/edit')
            ->where('appItems.0.item_code', 'BP-001')
            ->where('plannedItems.0.q1_quantity', 7)
        );
});

test('the summary reports remaining quantity per line', function () {
    $ppmp = Ppmp::factory()->for($this->department)->create();
    PpmpItem::factory()->for($ppmp)->create([
        'app_item_id' => $this->ballpen->id,
        'q1_quantity' => 10,
        'q2_quantity' => 0,
        'q3_quantity' => 0,
        'q4_quantity' => 0,
        'total_quantity' => 10,
    ]);

    $this->actingAs($this->dean)
        ->get(route('ppmp.summary', $ppmp))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ppmp/summary')
            ->where('items.0.remaining_quantity', 10)
        );
});

test('guests are sent to the login screen', function () {
    $this->get(route('ppmp.index'))->assertRedirect(route('login'));
});
