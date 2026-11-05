<?php

use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('exports stock numbers units and descriptions without lot items or template samples', function () {
    $this->seed(RolePermissionSeeder::class);

    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    $purchaseRequest = PurchaseRequest::factory()->create([
        'department_id' => $department->id,
        'requester_id' => $user->id,
        'purpose' => 'For the laboratory',
        'created_at' => '2026-11-05 09:00:00',
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
        'quantity_requested' => 1,
        'estimated_unit_cost' => 50000,
        'estimated_total_cost' => 50000,
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

    $response = $this->actingAs($user)->get(route('purchase-requests.export', $purchaseRequest));

    $response->assertOk();
    $response->assertDownload('PR-'.$purchaseRequest->pr_number.'.xlsx');

    $binary = $response->baseResponse;
    expect($binary)->toBeInstanceOf(BinaryFileResponse::class);

    $sheet = IOFactory::load($binary->getFile()->getPathname())->getActiveSheet();

    expect($sheet->getCell('D7')->getValue())->toBe($purchaseRequest->pr_number)
        ->and($sheet->getCell('F7')->getValue())->toBe('Date: 2026-11-05')
        ->and($sheet->getCell('A11')->getValue())->toBe(1)
        ->and($sheet->getCell('B11')->getValue())->toBe('lot')
        ->and($sheet->getCell('C11')->getValue())->toBe('Computer lot')
        ->and($sheet->getCell('E11')->getValue())->toBe(1)
        ->and((float) $sheet->getCell('F11')->getValue())->toBe(50000.0)
        ->and($sheet->getCell('G11')->isFormula())->toBeTrue()
        ->and($sheet->getCell('G11')->getValue())->toBe('=F11*E11')
        ->and($sheet->getCell('A12')->getValue())->toBe(2)
        ->and($sheet->getCell('B12')->getValue())->toBe('ream')
        ->and($sheet->getCell('C12')->getValue())->toBe('Bond paper, A4, 70gsm')
        ->and($sheet->getCell('E12')->getValue())->toBe(3)
        ->and((float) $sheet->getCell('F12')->getValue())->toBe(250.0)
        ->and($sheet->getCell('G12')->getValue())->toBe('=F12*E12')
        ->and($sheet->getCell('C13')->getValue())->toBeNull()
        ->and($sheet->getCell('C56')->getValue())->toBeNull()
        ->and($sheet->getStyle('C11')->getAlignment()->getWrapText())->toBeTrue();

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
        ->and($printed)->not->toContain('BATTERY')
        ->and($printed)->not->toContain('Desktop computer, Core i5');
});
