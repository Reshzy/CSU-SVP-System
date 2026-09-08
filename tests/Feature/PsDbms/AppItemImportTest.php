<?php

use App\Models\AppItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->secretariat = User::factory()->create();
    $this->secretariat->assignRole('BAC Secretariat');
});

test('items take the category banner that precedes them', function () {
    $csv = appCseCsv([
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-001', 'name' => 'Ballpen, black', 'price' => '8.50'],
        ['category' => 'ICT EQUIPMENT'],
        ['code' => 'LPT-001', 'name' => 'Laptop', 'price' => '55,000.00'],
    ]);

    $this->artisan('app:import', ['file' => $csv, '--year' => 2025])->assertSuccessful();

    expect(AppItem::where('item_code', 'BP-001')->sole()->category)->toBe('OFFICE SUPPLIES')
        ->and(AppItem::where('item_code', 'LPT-001')->sole()->category)->toBe('ICT EQUIPMENT');
});

test('prices are read from the worksheet with the peso sign and commas stripped', function () {
    $csv = appCseCsv([
        ['category' => 'ICT EQUIPMENT'],
        ['code' => 'LPT-001', 'name' => 'Laptop', 'price' => '₱ 55,000.00'],
    ]);

    $this->artisan('app:import', ['file' => $csv, '--year' => 2025]);

    expect((float) AppItem::where('item_code', 'LPT-001')->sole()->unit_price)->toBe(55000.00);
});

test('re-importing a year updates the existing rows instead of duplicating them', function () {
    $lines = fn (string $price) => [
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-001', 'name' => 'Ballpen, black', 'price' => $price],
    ];

    $this->artisan('app:import', ['file' => appCseCsv($lines('8.50')), '--year' => 2025]);
    $this->artisan('app:import', ['file' => appCseCsv($lines('9.75')), '--year' => 2025]);

    expect(AppItem::where('item_code', 'BP-001')->count())->toBe(1)
        ->and((float) AppItem::where('item_code', 'BP-001')->sole()->unit_price)->toBe(9.75);
});

test('the same item code can exist in two fiscal years', function () {
    $lines = [
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-001', 'name' => 'Ballpen, black', 'price' => '8.50'],
    ];

    $this->artisan('app:import', ['file' => appCseCsv($lines), '--year' => 2025]);
    $this->artisan('app:import', ['file' => appCseCsv($lines), '--year' => 2026]);

    expect(AppItem::where('item_code', 'BP-001')->pluck('fiscal_year')->all())->toEqual([2025, 2026]);
});

test('software items import even though the worksheet leaves them unpriced', function () {
    $csv = appCseCsv([
        ['category' => 'SOFTWARE'],
        ['code' => 'SW-001', 'name' => 'Office suite licence', 'price' => ''],
    ]);

    $this->artisan('app:import', ['file' => $csv, '--year' => 2025]);

    $item = AppItem::where('item_code', 'SW-001')->sole();

    expect($item->category)->toBe('SOFTWARE')
        ->and($item->unit_price)->toBeNull();
});

test('part two items import even though the worksheet leaves them unpriced', function () {
    $csv = appCseCsv([
        ['category' => 'PART II - OTHER ITEMS NOT AVAILABLE AT PS-DBM'],
        ['code' => 'OTH-001', 'name' => 'Laboratory reagent', 'price' => ''],
    ]);

    $this->artisan('app:import', ['file' => $csv, '--year' => 2025]);

    $item = AppItem::where('item_code', 'OTH-001')->sole();

    expect($item->category)->toBe('PART II - OTHER ITEMS NOT AVAILABLE AT PS-DBM')
        ->and($item->unit_price)->toBeNull();
});

test('an ordinary item with no price is skipped', function () {
    $csv = appCseCsv([
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-001', 'name' => 'Ballpen, black', 'price' => ''],
        ['code' => 'BP-002', 'name' => 'Ballpen, red', 'price' => '8.50'],
    ]);

    $this->artisan('app:import', ['file' => $csv, '--year' => 2025]);

    expect(AppItem::pluck('item_code')->all())->toEqual(['BP-002']);
});

test('the import fails cleanly when the file cannot be read', function () {
    $this->artisan('app:import', ['file' => 'nope.csv'])->assertFailed();

    expect(AppItem::count())->toBe(0);
});

test('the bac secretariat can open the catalog import screen', function () {
    $this->actingAs($this->secretariat)
        ->get(route('ps-dbms.import'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('reference/ps-dbms/import'));
});

test('the bac secretariat can import the catalog through the ui', function () {
    $upload = appCseUpload([
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-001', 'name' => 'Ballpen, black', 'price' => '8.50'],
    ]);

    $this->actingAs($this->secretariat)
        ->post(route('ps-dbms.process'), ['csv_file' => $upload, 'fiscal_year' => 2025])
        ->assertRedirect(route('ps-dbms.index', ['fiscal_year' => 2025]));

    expect(AppItem::where('item_code', 'BP-001')->sole()->fiscal_year)->toBe(2025);
});

test('the catalog import rejects a file that is not a csv', function () {
    $this->actingAs($this->secretariat)
        ->post(route('ps-dbms.process'), [
            'csv_file' => UploadedFile::fake()->create('catalog.pdf', 8, 'application/pdf'),
            'fiscal_year' => 2025,
        ])
        ->assertSessionHasErrors('csv_file');

    expect(AppItem::count())->toBe(0);
});

test('the catalog import rejects a fiscal year outside the allowed range', function () {
    $upload = appCseUpload([
        ['category' => 'OFFICE SUPPLIES'],
        ['code' => 'BP-001', 'name' => 'Ballpen, black', 'price' => '8.50'],
    ]);

    $this->actingAs($this->secretariat)
        ->post(route('ps-dbms.process'), ['csv_file' => $upload, 'fiscal_year' => 1999])
        ->assertSessionHasErrors('fiscal_year');
});

test('a user without manage-ps-dbms cannot reach the catalog', function () {
    $dean = User::factory()->create();
    $dean->assignRole('Dean');

    $this->actingAs($dean)->get(route('ps-dbms.index'))->assertForbidden();
    $this->actingAs($dean)->get(route('ps-dbms.import'))->assertForbidden();
});

test('the catalog index lists the imported items for the chosen year', function () {
    AppItem::factory()->forFiscalYear(2025)->create(['item_name' => 'Ballpen, black']);
    AppItem::factory()->forFiscalYear(2026)->create();

    $this->actingAs($this->secretariat)
        ->get(route('ps-dbms.index', ['fiscal_year' => 2025]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('reference/ps-dbms/index')
            ->where('filters.fiscal_year', 2025)
            ->count('appItems.data', 1)
            ->where('appItems.data.0.item_name', 'Ballpen, black')
        );
});
