<?php

namespace App\Services;

use App\Models\AppItem;
use Illuminate\Support\Facades\DB;

/**
 * Loads an APP-CSE worksheet into the PS-DBMS catalog for one fiscal year.
 * Re-importing the same year is safe: rows upsert on `(item_code, fiscal_year)`.
 */
class AppItemImporter
{
    public function __construct(private readonly AppCsvParser $parser) {}

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function import(string $path, int $fiscalYear): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($path, $fiscalYear, &$created, &$updated, &$skipped): void {
            foreach ($this->parser->items($path) as $row) {
                if ($row['unit_price'] === null && ! AppItem::allowsMissingPrice($row['category'])) {
                    $skipped++;

                    continue;
                }

                $item = AppItem::updateOrCreate(
                    [
                        'item_code' => $row['item_code'],
                        'fiscal_year' => $fiscalYear,
                    ],
                    [
                        'category' => $row['category'],
                        'item_name' => $row['item_name'],
                        'unit_of_measure' => $row['unit_of_measure'],
                        'unit_price' => $row['unit_price'],
                        'specifications' => $row['item_name'],
                        'is_active' => true,
                    ],
                );

                $item->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }
}
