<?php

namespace App\Services;

use App\Models\AppItem;
use App\Models\Ppmp;
use Illuminate\Support\Facades\DB;

/**
 * Loads quarterly quantities from an APP-CSE worksheet into a department's
 * PPMP. The catalog for that fiscal year must be imported first: a line whose
 * item code is not in `app_items` is skipped rather than invented.
 *
 * Lines already on the plan are updated in place, and lines absent from the
 * worksheet are left alone — the importer merges, it does not replace.
 */
class PpmpImporter
{
    public function __construct(private readonly AppCsvParser $parser) {}

    /**
     * @return array{
     *     created: int,
     *     updated: int,
     *     skipped_missing_catalog: int,
     *     skipped_zero_quantity: int,
     *     skipped_unpriced: int,
     * }
     */
    public function import(string $path, Ppmp $ppmp): array
    {
        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped_missing_catalog' => 0,
            'skipped_zero_quantity' => 0,
            'skipped_unpriced' => 0,
        ];

        DB::transaction(function () use ($path, $ppmp, &$summary): void {
            foreach ($this->parser->items($path) as $row) {
                $totalQuantity = array_sum($row['quarters']);

                if ($totalQuantity <= 0) {
                    $summary['skipped_zero_quantity']++;

                    continue;
                }

                $appItem = AppItem::query()
                    ->where('item_code', $row['item_code'])
                    ->where('fiscal_year', $ppmp->fiscal_year)
                    ->first();

                if ($appItem === null) {
                    $summary['skipped_missing_catalog']++;

                    continue;
                }

                // The catalog price wins; the worksheet only fills in for the
                // categories PS-DBM leaves unpriced.
                $unitCost = $appItem->unit_price !== null
                    ? (float) $appItem->unit_price
                    : $row['unit_price'];

                if ($unitCost === null || $unitCost <= 0) {
                    $summary['skipped_unpriced']++;

                    continue;
                }

                $item = $ppmp->items()->updateOrCreate(
                    ['app_item_id' => $appItem->id],
                    [
                        'q1_quantity' => $row['quarters'][1],
                        'q2_quantity' => $row['quarters'][2],
                        'q3_quantity' => $row['quarters'][3],
                        'q4_quantity' => $row['quarters'][4],
                        'total_quantity' => $totalQuantity,
                        'estimated_unit_cost' => $unitCost,
                        'estimated_total_cost' => $totalQuantity * $unitCost,
                    ],
                );

                $item->wasRecentlyCreated ? $summary['created']++ : $summary['updated']++;
            }

            $ppmp->recalculateTotalCost();
        });

        return $summary;
    }
}
