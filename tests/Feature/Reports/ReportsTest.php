<?php

use App\Models\Department;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->department = Department::factory()->create(['name' => 'College of Agriculture']);
    $this->dean = User::factory()->create(['department_id' => $this->department->id, 'name' => 'Dean Martinez']);
    $this->dean->assignRole('Dean');
});

/**
 * @return list<string>
 */
function csvLines(string $csv): array
{
    return array_values(array_filter(preg_split('/\r\n|\n|\r/', $csv) ?: []));
}

function unquoteCsv(string $csv): string
{
    return str_replace('"', '', $csv);
}

test('an end user cannot view reports', function () {
    $endUser = User::factory()->create(['department_id' => $this->department->id]);
    $endUser->assignRole('End User');

    $this->actingAs($endUser)
        ->get(route('reports.pr'))
        ->assertForbidden();
});

test('a dean can open analytics', function () {
    $this->actingAs($this->dean)
        ->get(route('reports.analytics'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/analytics')
            ->has('labels', 12)
            ->has('counts', 12)
            ->has('cycle', 12)
        );
});

test('the purchase request csv uses the documented headers and rows', function () {
    PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'pr_number' => 'PR-1026-0007',
        'purpose' => 'Office supplies',
        'status' => 'supply_office_review',
        'estimated_total' => 125.5,
        'date_needed' => '2026-11-01',
    ]);

    $response = $this->actingAs($this->dean)->get(route('reports.pr.export'));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toMatch('/purchase_requests_report_\d{8}_\d{6}\.csv/');

    $csv = $response->streamedContent();
    $lines = csvLines($csv);

    expect(unquoteCsv($lines[0]))->toBe('PR Number,Created At,Requester,Department,Purpose,Date Needed,Priority,Estimated Total,Status')
        ->and($lines[1])->toContain('PR-1026-0007')
        ->and($lines[1])->toContain('Dean Martinez')
        ->and($lines[1])->toContain('College of Agriculture')
        ->and($lines[1])->toContain('Office supplies')
        ->and($lines[1])->toContain('125.50')
        ->and($lines[1])->toContain('supply_office_review');
});

test('the supplier performance csv uses the documented headers', function () {
    $supplier = Supplier::factory()->create(['business_name' => 'Lienmavel']);
    $purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
    ]);
    Quotation::factory()->for($purchaseRequest)->for($supplier)->create([
        'is_winning_bid' => true,
        'total_amount' => 400,
    ]);
    PurchaseOrder::factory()->for($purchaseRequest)->for($supplier)->create([
        'status' => 'completed',
        'total_amount' => 400,
    ]);

    $csv = $this->actingAs($this->dean)
        ->get(route('reports.suppliers.export'))
        ->assertOk()
        ->streamedContent();

    $lines = csvLines($csv);

    expect(unquoteCsv($lines[0]))->toBe('Supplier,Quotes,Awards,Win Rate %,Awarded Value,POs,Completed POs,PO Value')
        ->and($lines[1])->toContain('Lienmavel')
        ->and($lines[1])->toContain('100')
        ->and($lines[1])->toContain('400.00');
});

test('the budget csv includes a totals row', function () {
    PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'estimated_total' => 1000,
    ]);

    $csv = $this->actingAs($this->dean)
        ->get(route('reports.budget.export'))
        ->assertOk()
        ->streamedContent();

    expect(unquoteCsv($csv))->toContain('Department,PR Count,PR Total,PO Count,PO Total,Utilization %')
        ->and($csv)->toContain('Totals')
        ->and($csv)->toContain('1000.00');
});

test('custom export uses the selected columns', function () {
    PurchaseRequest::factory()->create([
        'department_id' => $this->department->id,
        'requester_id' => $this->dean->id,
        'pr_number' => 'PR-1026-0008',
    ]);

    $csv = $this->actingAs($this->dean)
        ->get(route('reports.custom.export', [
            'columns' => ['pr_number', 'status'],
        ]))
        ->assertOk()
        ->streamedContent();

    $lines = csvLines($csv);

    expect(unquoteCsv($lines[0]))->toBe('PR Number,Status')
        ->and($lines[1])->toContain('PR-1026-0008');
});
