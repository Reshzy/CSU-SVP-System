<?php

use App\Models\AppItem;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->secretariat = User::factory()->create();
    $this->secretariat->assignRole('BAC Secretariat');

    $this->ballpen = AppItem::factory()->forFiscalYear(2025)->create([
        'item_code' => 'BP-001',
        'item_name' => 'Ballpen, black',
    ]);
});

/**
 * Plan `$quantity` of the shared catalog item on a fresh department's plan.
 */
function planBallpen(AppItem $appItem, int $fiscalYear, int $quantity, bool $validated = true): Ppmp
{
    $ppmp = Ppmp::factory()->forFiscalYear($fiscalYear)->when($validated, fn ($factory) => $factory->validated())->create();

    PpmpItem::factory()->for($ppmp)->create([
        'app_item_id' => $appItem->id,
        'q1_quantity' => $quantity,
        'q2_quantity' => 0,
        'q3_quantity' => 0,
        'q4_quantity' => 0,
        'total_quantity' => $quantity,
        'estimated_unit_cost' => 10.00,
        'estimated_total_cost' => $quantity * 10.00,
    ]);

    return $ppmp;
}

test('quantities from validated plans are summed per catalog item', function () {
    planBallpen($this->ballpen, 2025, 10);
    planBallpen($this->ballpen, 2025, 15);

    $this->actingAs($this->secretariat)
        ->get(route('bac.app.index', ['fiscal_year' => 2025]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bac/app/index')
            ->count('items', 1)
            ->where('items.0.item_code', 'BP-001')
            ->where('items.0.total_quantity', 25)
            ->where('items.0.department_count', 2)
            ->where('items.0.estimated_total_cost', 250)
        );
});

test('draft plans are left out of the consolidation', function () {
    planBallpen($this->ballpen, 2025, 10, validated: false);

    $this->actingAs($this->secretariat)
        ->get(route('bac.app.index', ['fiscal_year' => 2025]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->count('items', 0)
            ->where('stats.ppmp_count', 0)
        );
});

test('plans from another fiscal year are left out', function () {
    planBallpen($this->ballpen, 2026, 10);

    $this->actingAs($this->secretariat)
        ->get(route('bac.app.index', ['fiscal_year' => 2025]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->count('items', 0));
});

test('the header counts validated plans and the departments behind them', function () {
    planBallpen($this->ballpen, 2025, 10);
    planBallpen($this->ballpen, 2025, 10);
    planBallpen($this->ballpen, 2025, 10, validated: false);

    $this->actingAs($this->secretariat)
        ->get(route('bac.app.index', ['fiscal_year' => 2025]))
        ->assertInertia(fn ($page) => $page
            ->where('stats.ppmp_count', 2)
            ->where('stats.department_count', 2)
            ->where('stats.item_count', 1)
            ->where('stats.total_estimated_cost', 200)
        );
});

test('a user without view-consolidated-app cannot reach it', function () {
    $dean = User::factory()->create();
    $dean->assignRole('Dean');

    $this->actingAs($dean)->get(route('bac.app.index'))->assertForbidden();
});

test('the consolidated app has no download route', function () {
    expect(Route::has('bac.app.export'))->toBeFalse()
        ->and(Route::has('bac.app.download'))->toBeFalse();
});
