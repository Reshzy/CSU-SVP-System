<?php

namespace Database\Seeders;

use App\Models\AppItem;
use Illuminate\Database\Seeder;

/**
 * A small PS-DBMS catalog for the current calendar year so departments have
 * something to plan against before the real APP-CSE worksheet is imported.
 * Rows upsert on the same `(item_code, fiscal_year)` key `app:import` uses,
 * so a later import overwrites them cleanly.
 *
 * SOFTWARE items are seeded unpriced on purpose: PS-DBM does not price them,
 * and the planning department supplies a figure on its own PPMP line.
 */
class AppItemSeeder extends Seeder
{
    /**
     * @var list<array{category: string, item_code: string, item_name: string, unit_of_measure: string, unit_price: float|null}>
     */
    public const ITEMS = [
        [
            'category' => 'OFFICE SUPPLIES',
            'item_code' => '12111503-BP-B01',
            'item_name' => 'Ballpen, black, 0.5mm',
            'unit_of_measure' => 'piece',
            'unit_price' => 8.50,
        ],
        [
            'category' => 'OFFICE SUPPLIES',
            'item_code' => '12111503-BP-R01',
            'item_name' => 'Ballpen, red, 0.5mm',
            'unit_of_measure' => 'piece',
            'unit_price' => 8.50,
        ],
        [
            'category' => 'ICT EQUIPMENT',
            'item_code' => '43211507-DCT-01',
            'item_name' => 'Desktop computer, business class',
            'unit_of_measure' => 'unit',
            'unit_price' => 42000.00,
        ],
        [
            'category' => 'ICT EQUIPMENT',
            'item_code' => '43211503-LPT-01',
            'item_name' => 'Laptop computer, business class',
            'unit_of_measure' => 'unit',
            'unit_price' => 55000.00,
        ],
        [
            'category' => 'ICT EQUIPMENT',
            'item_code' => '44101602-PR-M01',
            'item_name' => 'Printer, multifunction, inkjet',
            'unit_of_measure' => 'unit',
            'unit_price' => 12500.00,
        ],
        [
            'category' => 'SOFTWARE',
            'item_code' => '43231513-SW-OFF',
            'item_name' => 'Office productivity suite licence, annual',
            'unit_of_measure' => 'licence',
            'unit_price' => null,
        ],
        [
            'category' => 'SOFTWARE',
            'item_code' => '43233205-SW-AV1',
            'item_name' => 'Endpoint antivirus licence, annual',
            'unit_of_measure' => 'licence',
            'unit_price' => null,
        ],
    ];

    public function run(): void
    {
        $fiscalYear = (int) date('Y');

        foreach (self::ITEMS as $item) {
            AppItem::updateOrCreate(
                [
                    'item_code' => $item['item_code'],
                    'fiscal_year' => $fiscalYear,
                ],
                [
                    'category' => $item['category'],
                    'item_name' => $item['item_name'],
                    'unit_of_measure' => $item['unit_of_measure'],
                    'unit_price' => $item['unit_price'],
                    'specifications' => $item['item_name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
