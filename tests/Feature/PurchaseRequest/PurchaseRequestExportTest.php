<?php

use App\Models\Department;
use App\Models\Position;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('exports each lot item on its own row without unit or cost', function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $department->id,
        'requester_id' => $user->id,
        'purpose' => 'For the laboratory',
        'created_at' => '2026-11-05 09:00:00',
    ]);

    PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $purchaseRequest->id,
        'ppmp_item_id' => null,
        'item_name' => 'Bond paper, A4, 70gsm',
        'unit_of_measure' => 'ream',
        'quantity_requested' => 3,
        'estimated_unit_cost' => 250,
        'estimated_total_cost' => 750,
    ]);

    $lot = PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $purchaseRequest->id,
        'ppmp_item_id' => null,
        'is_lot' => true,
        'lot_name' => 'Computer lot',
        'item_name' => 'Computer lot',
        'unit_of_measure' => 'lot',
        'quantity_requested' => 1,
        'estimated_unit_cost' => 50000,
        'estimated_total_cost' => 50000,
    ]);

    PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $purchaseRequest->id,
        'ppmp_item_id' => null,
        'parent_lot_id' => $lot->id,
        'is_lot' => false,
        'item_name' => 'Desktop computer, Core i5',
        'unit_of_measure' => 'unit',
        'quantity_requested' => 2,
        'estimated_unit_cost' => 30000,
        'estimated_total_cost' => 60000,
    ]);

    PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $purchaseRequest->id,
        'ppmp_item_id' => null,
        'parent_lot_id' => $lot->id,
        'is_lot' => false,
        'item_name' => 'Monitor, 24 inch',
        'unit_of_measure' => 'pc',
        'quantity_requested' => 2,
        'estimated_unit_cost' => 10000,
        'estimated_total_cost' => 20000,
    ]);

    PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $purchaseRequest->id,
        'ppmp_item_id' => null,
        'parent_lot_id' => $lot->id,
        'is_lot' => false,
        'item_name' => 'Keyboard',
        'unit_of_measure' => 'pc',
        'quantity_requested' => 2,
        'estimated_unit_cost' => 10000,
        'estimated_total_cost' => 20000,
    ]);

    $response = $this->actingAs($user)->get(route('purchase-requests.export', $purchaseRequest));

    $response->assertOk();
    $response->assertDownload('PR-'.$purchaseRequest->pr_number.'.xlsx');

    $binary = $response->baseResponse;
    expect($binary)->toBeInstanceOf(BinaryFileResponse::class);

    $sheet = IOFactory::load($binary->getFile()->getPathname())->getActiveSheet();

    expect($sheet->getCell('D7')->getValue())->toBe($purchaseRequest->pr_number)
        ->and($sheet->getCell('F7')->getValue())->toBe('Date: 2026-11-05')
        ->and($sheet->getCell('A11')->getValue())->toBe(1)
        ->and($sheet->getCell('B11')->getValue())->toBe('ream')
        ->and($sheet->getCell('C11')->getValue())->toBe('Bond paper, A4, 70gsm')
        ->and($sheet->getCell('E11')->getValue())->toBe(3)
        ->and((float) $sheet->getCell('F11')->getValue())->toBe(250.0)
        ->and($sheet->getCell('G11')->isFormula())->toBeTrue()
        ->and($sheet->getCell('G11')->getValue())->toBe('=F11*E11')
        ->and($sheet->getStyle('C11')->getFont()->getBold())->toBeFalse()
        ->and($sheet->getCell('A12')->getValue())->toBe(2)
        ->and($sheet->getCell('B12')->getValue())->toBe('lot')
        ->and($sheet->getCell('C12')->getValue())->toBe('Computer lot')
        ->and($sheet->getCell('E12')->getValue())->toBe(1)
        ->and((float) $sheet->getCell('F12')->getValue())->toBe(50000.0)
        ->and($sheet->getCell('G12')->getValue())->toBe('=F12*E12')
        ->and($sheet->getStyle('C12')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getCell('A13')->getValue())->toBeNull()
        ->and($sheet->getCell('B13')->getValue())->toBeNull()
        ->and($sheet->getCell('C13')->getValue())->toBe('  Desktop computer, Core i5')
        ->and($sheet->getCell('E13')->getValue())->toBeNull()
        ->and($sheet->getCell('F13')->getValue())->toBeNull()
        ->and($sheet->getCell('G13')->getValue())->toBeNull()
        ->and($sheet->getStyle('C13')->getFont()->getBold())->toBeFalse()
        ->and($sheet->getCell('A14')->getValue())->toBeNull()
        ->and($sheet->getCell('B14')->getValue())->toBeNull()
        ->and($sheet->getCell('C14')->getValue())->toBe('  Monitor, 24 inch')
        ->and($sheet->getCell('E14')->getValue())->toBeNull()
        ->and($sheet->getCell('F14')->getValue())->toBeNull()
        ->and($sheet->getCell('G14')->getValue())->toBeNull()
        ->and($sheet->getCell('A15')->getValue())->toBeNull()
        ->and($sheet->getCell('B15')->getValue())->toBeNull()
        ->and($sheet->getCell('C15')->getValue())->toBe('  Keyboard')
        ->and($sheet->getCell('E15')->getValue())->toBeNull()
        ->and($sheet->getCell('F15')->getValue())->toBeNull()
        ->and($sheet->getCell('G15')->getValue())->toBeNull()
        ->and($sheet->getStyle('C15')->getFont()->getBold())->toBeFalse()
        ->and($sheet->getCell('C16')->getValue())->toBeNull()
        ->and($sheet->getCell('C56')->getValue())->toBeNull()
        ->and($sheet->getCell('G56')->getValue())->toBeNull()
        ->and($sheet->getCell('C57')->getValue())->toBe('TOTAL')
        ->and($sheet->getCell('G57')->getValue())->toBe('=SUM(G11:G56)')
        ->and($sheet->getStyle('C55')->getAlignment()->getHorizontal())->toBe(Alignment::HORIZONTAL_LEFT)
        ->and($sheet->getStyle('C11')->getAlignment()->getWrapText())->toBeTrue()
        ->and($sheet->getStyle('C12')->getAlignment()->getWrapText())->toBeTrue();

    $values = [];

    foreach ($sheet->getRowIterator() as $row) {
        foreach ($row->getCellIterator() as $cell) {
            $value = $cell->getValue();

            if (is_string($value)) {
                $values[] = $value;
            }
        }
    }

    $printed = implode("\n", $values);

    expect($printed)->not->toContain('ALCOHOL')
        ->and($printed)->not->toContain('BATTERY');
});

test('wraps a long description across rows and starts the next item after it', function () {
    [$user, $purchaseRequest] = purchaseRequestReadyForExport();
    $description = implode(' ', array_fill(0, 30, 'notebook'));

    PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $purchaseRequest->id,
        'ppmp_item_id' => null,
        'item_name' => $description,
        'unit_of_measure' => 'pc',
        'quantity_requested' => 2,
        'estimated_unit_cost' => 40,
        'estimated_total_cost' => 80,
    ]);

    PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $purchaseRequest->id,
        'ppmp_item_id' => null,
        'item_name' => 'Stapler',
        'unit_of_measure' => 'pc',
        'quantity_requested' => 1,
        'estimated_unit_cost' => 90,
        'estimated_total_cost' => 90,
    ]);

    $spreadsheet = exportedPurchaseRequestWorkbook($purchaseRequest, $user);
    $sheet = $spreadsheet->getActiveSheet();
    $lines = [];

    for ($row = 11; $row <= 55; $row++) {
        if ($row !== 11 && $sheet->getCell("A{$row}")->getValue() !== null) {
            break;
        }

        $lines[] = $sheet->getCell("C{$row}")->getValue();
    }

    $nextRow = 11 + count($lines);

    expect($spreadsheet->getSheetCount())->toBe(1)
        ->and($sheet->getTitle())->toBe('PR')
        ->and($lines)->not->toHaveCount(1)
        ->and(implode(' ', $lines))->toBe($description)
        ->and($sheet->getCell('A11')->getValue())->toBe(1)
        ->and($sheet->getCell('E11')->getValue())->toBe(2)
        ->and($sheet->getCell('A12')->getValue())->toBeNull()
        ->and($sheet->getCell('E12')->getValue())->toBeNull()
        ->and($sheet->getCell('G12')->getValue())->toBeNull()
        ->and($sheet->getCell("A{$nextRow}")->getValue())->toBe(2)
        ->and($sheet->getCell("C{$nextRow}")->getValue())->toBe('Stapler');
});

test('moves an item to page 2 when it does not fit the remaining rows', function () {
    [$user, $purchaseRequest] = purchaseRequestReadyForExport();

    foreach (range(1, 43) as $number) {
        PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $purchaseRequest->id,
            'ppmp_item_id' => null,
            'item_name' => 'Cable '.$number,
            'quantity_requested' => 1,
            'estimated_unit_cost' => 10,
            'estimated_total_cost' => 10,
        ]);
    }

    PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $purchaseRequest->id,
        'ppmp_item_id' => null,
        'item_name' => "overflow one\noverflow two\noverflow three\noverflow four",
        'quantity_requested' => 2,
        'estimated_unit_cost' => 15,
        'estimated_total_cost' => 30,
    ]);

    $spreadsheet = exportedPurchaseRequestWorkbook($purchaseRequest, $user);
    $pageOne = $spreadsheet->getSheet(0);
    $pageTwo = $spreadsheet->getSheet(1);

    expect($spreadsheet->getSheetCount())->toBe(2)
        ->and($pageOne->getTitle())->toBe('Page 1')
        ->and($pageOne->getCell('A53')->getValue())->toBe(43)
        ->and($pageOne->getCell('C53')->getValue())->toBe('Cable 43')
        ->and($pageOne->getCell('C54')->getValue())->toBeNull()
        ->and($pageOne->getCell('C55')->getValue())->toBeNull()
        ->and($pageOne->getCell('D7')->getValue())->toBe($purchaseRequest->pr_number)
        ->and($pageOne->getCell('B58')->getValue())->toBe('For the laboratory')
        ->and($pageTwo->getTitle())->toBe('Page 2')
        ->and($pageTwo->getCell('D7')->getValue())->toBe($purchaseRequest->pr_number)
        ->and($pageTwo->getCell('B58')->getValue())->toBe('For the laboratory')
        ->and($pageTwo->getCell('A11')->getValue())->toBe(44)
        ->and($pageTwo->getCell('C11')->getValue())->toBe('overflow one')
        ->and($pageTwo->getCell('C12')->getValue())->toBe('overflow two')
        ->and($pageTwo->getCell('C13')->getValue())->toBe('overflow three')
        ->and($pageTwo->getCell('C14')->getValue())->toBe('overflow four')
        ->and($pageTwo->getCell('A12')->getValue())->toBeNull()
        ->and($pageTwo->getCell('E11')->getValue())->toBe(2)
        ->and((float) $pageTwo->getCell('F11')->getValue())->toBe(15.0)
        ->and($pageTwo->getCell('G11')->getValue())->toBe('=F11*E11')
        ->and($pageTwo->getCell('E12')->getValue())->toBeNull()
        ->and($pageTwo->getCell('G12')->getValue())->toBeNull()
        ->and($pageTwo->getCell('C15')->getValue())->toBeNull()
        ->and($pageOne->getStyle('C55')->getAlignment()->getHorizontal())->toBe(Alignment::HORIZONTAL_LEFT)
        ->and($pageOne->getCell('C56')->getValue())->toBe('Page 1 of 2')
        ->and($pageOne->getCell('G56')->getValue())->toBe('=SUM(G11:G55)')
        ->and($pageOne->getCell('C57')->getValue())->toBe('CONTINUED ON NEXT PAGE')
        ->and($pageOne->getCell('G57')->getValue())->toBeNull()
        ->and((float) $pageOne->getCell('G56')->getCalculatedValue())->toBe(430.0)
        ->and($pageTwo->getCell('C56')->getValue())->toBe('Page 2 of 2')
        ->and($pageTwo->getCell('G56')->getValue())->toBe('=SUM(G11:G55)')
        ->and($pageTwo->getCell('C57')->getValue())->toBe('TOTAL')
        ->and($pageTwo->getCell('G57')->getValue())->toBe("='Page 1'!G56+'Page 2'!G56")
        ->and((float) $pageTwo->getCell('G56')->getCalculatedValue())->toBe(30.0)
        ->and((float) $pageTwo->getCell('G57')->getCalculatedValue())->toBe(460.0);
});

test('continues a description taller than one page without repeating the cost', function () {
    [$user, $purchaseRequest] = purchaseRequestReadyForExport();
    $lines = [];

    for ($number = 1; $number <= 50; $number++) {
        $lines[] = sprintf('spec%02d', $number);
    }

    PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $purchaseRequest->id,
        'ppmp_item_id' => null,
        'item_name' => implode("\n", $lines),
        'quantity_requested' => 4,
        'estimated_unit_cost' => 12.5,
        'estimated_total_cost' => 50,
    ]);

    $spreadsheet = exportedPurchaseRequestWorkbook($purchaseRequest, $user);
    $pageOne = $spreadsheet->getSheet(0);
    $pageTwo = $spreadsheet->getSheet(1);

    expect($spreadsheet->getSheetCount())->toBe(2)
        ->and($pageOne->getTitle())->toBe('Page 1')
        ->and($pageOne->getCell('A11')->getValue())->toBe(1)
        ->and($pageOne->getCell('C11')->getValue())->toBe('spec01')
        ->and($pageOne->getCell('E11')->getValue())->toBe(4)
        ->and((float) $pageOne->getCell('F11')->getValue())->toBe(12.5)
        ->and($pageOne->getCell('G11')->getValue())->toBe('=F11*E11')
        ->and($pageOne->getCell('C55')->getValue())->toBe('spec45')
        ->and($pageOne->getCell('A12')->getValue())->toBeNull()
        ->and($pageOne->getCell('E12')->getValue())->toBeNull()
        ->and($pageOne->getCell('G12')->getValue())->toBeNull()
        ->and($pageTwo->getTitle())->toBe('Page 2')
        ->and($pageTwo->getCell('D7')->getValue())->toBe($purchaseRequest->pr_number)
        ->and($pageTwo->getCell('B58')->getValue())->toBe('For the laboratory')
        ->and($pageTwo->getCell('A11')->getValue())->toBeNull()
        ->and($pageTwo->getCell('C11')->getValue())->toBe('spec46')
        ->and($pageTwo->getCell('E11')->getValue())->toBeNull()
        ->and($pageTwo->getCell('F11')->getValue())->toBeNull()
        ->and($pageTwo->getCell('G11')->getValue())->toBeNull()
        ->and($pageTwo->getCell('C15')->getValue())->toBe('spec50')
        ->and($pageTwo->getCell('C16')->getValue())->toBeNull();
});

test('indents every wrapped line of a lot child and keeps the lot name bold', function () {
    [$user, $purchaseRequest] = purchaseRequestReadyForExport();
    $childName = implode(' ', array_fill(0, 20, 'keyboard'));

    $lot = PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $purchaseRequest->id,
        'ppmp_item_id' => null,
        'is_lot' => true,
        'lot_name' => "Computer\nlot",
        'item_name' => "Computer\nlot",
        'unit_of_measure' => 'lot',
        'quantity_requested' => 1,
        'estimated_unit_cost' => 100,
        'estimated_total_cost' => 100,
    ]);

    PurchaseRequestItem::factory()->create([
        'purchase_request_id' => $purchaseRequest->id,
        'ppmp_item_id' => null,
        'parent_lot_id' => $lot->id,
        'item_name' => $childName,
        'unit_of_measure' => 'pc',
        'quantity_requested' => 1,
        'estimated_unit_cost' => 20,
        'estimated_total_cost' => 20,
    ]);

    $sheet = exportedPurchaseRequestWorkbook($purchaseRequest, $user)->getActiveSheet();
    $childLines = [];

    for ($row = 13; $row <= 55; $row++) {
        $value = $sheet->getCell("C{$row}")->getValue();

        if ($value === null || $value === '') {
            break;
        }

        $childLines[] = $value;

        expect($sheet->getStyle("C{$row}")->getFont()->getBold())->toBeFalse()
            ->and($sheet->getCell("A{$row}")->getValue())->toBeNull()
            ->and($sheet->getCell("E{$row}")->getValue())->toBeNull();
    }

    expect($sheet->getCell('C11')->getValue())->toBe('Computer')
        ->and($sheet->getStyle('C11')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getCell('A11')->getValue())->toBe(1)
        ->and($sheet->getCell('E11')->getValue())->toBe(1)
        ->and($sheet->getCell('C12')->getValue())->toBe('lot')
        ->and($sheet->getStyle('C12')->getFont()->getBold())->toBeTrue()
        ->and($sheet->getCell('A12')->getValue())->toBeNull()
        ->and($sheet->getCell('E12')->getValue())->toBeNull()
        ->and($sheet->getCell('G12')->getValue())->toBeNull()
        ->and($childLines)->not->toHaveCount(1)
        ->and(implode(' ', array_map(fn (string $line): string => substr($line, 2), $childLines)))->toBe($childName);

    foreach ($childLines as $line) {
        expect($line)->toStartWith('  ');
    }
});

/**
 * @return array{0: User, 1: PurchaseRequest}
 */
function purchaseRequestReadyForExport(): array
{
    test()->seed(RolePermissionSeeder::class);

    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $department->id,
        'requester_id' => $user->id,
        'purpose' => 'For the laboratory',
        'created_at' => '2026-11-05 09:00:00',
    ]);

    return [$user, $purchaseRequest];
}

function exportedPurchaseRequestWorkbook(PurchaseRequest $purchaseRequest, User $user): Spreadsheet
{
    $response = test()->actingAs($user)->get(route('purchase-requests.export', $purchaseRequest));
    $response->assertOk();

    return IOFactory::load($response->baseResponse->getFile()->getPathname());
}

test('prints the requester designation, falling back to their position', function (?string $designation, string $expected) {
    [$user, $purchaseRequest] = purchaseRequestReadyForExport();
    $user->update([
        'name' => 'Dr. Manny S. Alipio',
        'position_id' => Position::factory()->create(['name' => 'College Dean'])->id,
        'designation' => $designation,
    ]);

    $sheet = exportedPurchaseRequestWorkbook($purchaseRequest, $user)->getActiveSheet();

    expect($sheet->getCell('B66')->getValue())->toBe('Dr. Manny S. Alipio')
        ->and($sheet->getCell('B67')->getValue())->toBe($expected);
})->with([
    'own designation' => ['Dean, CICS', 'Dean, CICS'],
    'position fallback' => [null, 'College Dean'],
]);

test('signs as the active executive officer with the campus title by default', function () {
    [$user, $purchaseRequest] = purchaseRequestReadyForExport();

    User::factory()->create(['name' => 'Former Officer', 'is_active' => false])->assignRole('Executive Officer');
    User::factory()->create(['name' => 'Rodel Francisco T. Alegado'])->assignRole('Executive Officer');

    $sheet = exportedPurchaseRequestWorkbook($purchaseRequest, $user)->getActiveSheet();

    expect($sheet->getCell('E66')->getValue())->toBe('Rodel Francisco T. Alegado')
        ->and($sheet->getCell('E67')->getValue())->toBe('Campus Executive Officer');
});

test('uses the executive officer designation when set', function () {
    [$user, $purchaseRequest] = purchaseRequestReadyForExport();

    User::factory()->create(['designation' => 'Campus Executive Officer II'])->assignRole('Executive Officer');

    $sheet = exportedPurchaseRequestWorkbook($purchaseRequest, $user)->getActiveSheet();

    expect($sheet->getCell('E67')->getValue())->toBe('Campus Executive Officer II');
});
