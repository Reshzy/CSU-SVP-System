<?php

use App\Models\AppItem;
use App\Models\Department;
use App\Models\Ppmp;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create(['code' => 'CICS']);
    $this->dean = User::factory()->create(['department_id' => $this->department->id]);
    $this->dean->assignRole('Dean');

    AppItem::factory()->forFiscalYear(2025)->create([
        'item_code' => 'BP-001',
        'item_name' => 'Ballpen, black',
        'unit_price' => 10.00,
    ]);
});

test('quarterly quantities load onto the department plan', function () {
    $csv = appCseCsv([
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-001', 'name' => 'Ballpen, black', 'q1' => 5, 'q2' => 5, 'q3' => 0, 'q4' => 10],
    ]);

    $this->artisan('ppmp:import-csv', [
        'file' => $csv,
        '--year' => 2025,
        '--department' => 'CICS',
    ])->assertSuccessful();

    $item = Ppmp::sole()->items()->sole();

    expect($item->q1_quantity)->toBe(5)
        ->and($item->q4_quantity)->toBe(10)
        ->and($item->total_quantity)->toBe(20)
        ->and((float) $item->estimated_total_cost)->toBe(200.00);
});

test('the plan header total is recalculated from its lines', function () {
    AppItem::factory()->forFiscalYear(2025)->create([
        'item_code' => 'LPT-001',
        'unit_price' => 1000.00,
    ]);

    $csv = appCseCsv([
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-001', 'name' => 'Ballpen, black', 'q1' => 20],
        ['code' => 'LPT-001', 'name' => 'Laptop', 'q1' => 2],
    ]);

    $this->artisan('ppmp:import-csv', [
        'file' => $csv,
        '--year' => 2025,
        '--department' => 'CICS',
    ]);

    expect((float) Ppmp::sole()->total_estimated_cost)->toBe(2200.00);
});

test('lines whose item code is missing from that year of the catalog are skipped', function () {
    $csv = appCseCsv([
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-001', 'name' => 'Ballpen, black', 'q1' => 5],
        ['code' => 'GHOST-001', 'name' => 'Not in the catalog', 'q1' => 5],
    ]);

    $this->artisan('ppmp:import-csv', [
        'file' => $csv,
        '--year' => 2025,
        '--department' => 'CICS',
    ]);

    expect(Ppmp::sole()->items()->count())->toBe(1);
});

test('a catalog entry from a different year does not satisfy the lookup', function () {
    AppItem::factory()->forFiscalYear(2026)->create(['item_code' => 'BP-002']);

    $csv = appCseCsv([
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-002', 'name' => 'Ballpen, red', 'q1' => 5],
    ]);

    $this->artisan('ppmp:import-csv', [
        'file' => $csv,
        '--year' => 2025,
        '--department' => 'CICS',
    ]);

    expect(Ppmp::sole()->items()->count())->toBe(0);
});

test('lines with no quantity in any quarter are skipped', function () {
    $csv = appCseCsv([
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-001', 'name' => 'Ballpen, black', 'q1' => 0, 'q2' => 0, 'q3' => 0, 'q4' => 0],
    ]);

    $this->artisan('ppmp:import-csv', [
        'file' => $csv,
        '--year' => 2025,
        '--department' => 'CICS',
    ]);

    expect(Ppmp::sole()->items()->count())->toBe(0);
});

test('an unpriced catalog item is costed from the worksheet price', function () {
    AppItem::factory()->forFiscalYear(2025)->unpriced()->create(['item_code' => 'SW-001']);

    $csv = appCseCsv([
        ['category' => 'SOFTWARE'],
        ['code' => 'SW-001', 'name' => 'Office suite licence', 'q1' => 3, 'price' => '1,500.00'],
    ]);

    $this->artisan('ppmp:import-csv', [
        'file' => $csv,
        '--year' => 2025,
        '--department' => 'CICS',
    ]);

    $item = Ppmp::sole()->items()->sole();

    expect((float) $item->estimated_unit_cost)->toBe(1500.00)
        ->and((float) $item->estimated_total_cost)->toBe(4500.00);
});

test('an unpriced catalog item with no worksheet price is skipped', function () {
    AppItem::factory()->forFiscalYear(2025)->unpriced()->create(['item_code' => 'SW-001']);

    $csv = appCseCsv([
        ['category' => 'SOFTWARE'],
        ['code' => 'SW-001', 'name' => 'Office suite licence', 'q1' => 3, 'price' => ''],
    ]);

    $this->artisan('ppmp:import-csv', [
        'file' => $csv,
        '--year' => 2025,
        '--department' => 'CICS',
    ]);

    expect(Ppmp::sole()->items()->count())->toBe(0);
});

test('re-importing merges into the existing lines instead of duplicating them', function () {
    $lines = fn (int $q1) => [
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-001', 'name' => 'Ballpen, black', 'q1' => $q1],
    ];

    $this->artisan('ppmp:import-csv', [
        'file' => appCseCsv($lines(5)),
        '--year' => 2025,
        '--department' => 'CICS',
    ]);

    $this->artisan('ppmp:import-csv', [
        'file' => appCseCsv($lines(9)),
        '--year' => 2025,
        '--department' => 'CICS',
    ]);

    $items = Ppmp::sole()->items()->get();

    expect($items)->toHaveCount(1)
        ->and($items->first()->q1_quantity)->toBe(9);
});

test('importing twice reuses the one plan the department may hold for a year', function () {
    $csv = appCseCsv([
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-001', 'name' => 'Ballpen, black', 'q1' => 5],
    ]);

    $this->artisan('ppmp:import-csv', ['file' => $csv, '--year' => 2025, '--department' => 'CICS']);
    $this->artisan('ppmp:import-csv', ['file' => $csv, '--year' => 2025, '--department' => 'CICS']);

    expect(Ppmp::count())->toBe(1);
});

test('the command fails when the department cannot be resolved', function () {
    $csv = appCseCsv([['category' => 'OFFICE SUPPLIES']]);

    $this->artisan('ppmp:import-csv', ['file' => $csv, '--department' => 'NOPE'])->assertFailed();

    expect(Ppmp::count())->toBe(0);
});

test('the plan import screen names the department it will write to', function () {
    $this->actingAs($this->dean)
        ->get(route('ppmp.import'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ppmp/import')
            ->where('department.code', 'CICS')
        );
});

test('the ui import writes to the signed-in users department plan', function () {
    $upload = appCseUpload([
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-001', 'name' => 'Ballpen, black', 'q1' => 5],
    ]);

    $this->actingAs($this->dean)
        ->post(route('ppmp.import.process'), ['csv_file' => $upload, 'fiscal_year' => 2025])
        ->assertRedirect();

    $ppmp = Ppmp::sole();

    expect($ppmp->department_id)->toBe($this->department->id)
        ->and($ppmp->fiscal_year)->toBe(2025)
        ->and($ppmp->items()->count())->toBe(1);
});

test('a user with no department cannot import a plan', function () {
    $orphan = User::factory()->create(['department_id' => null]);
    $orphan->assignRole('End User');

    $this->actingAs($orphan)
        ->post(route('ppmp.import.process'), [
            'csv_file' => appCseUpload([['category' => 'OFFICE SUPPLIES']]),
            'fiscal_year' => 2025,
        ])
        ->assertForbidden();
});
