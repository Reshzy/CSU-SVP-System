<?php

namespace App\Services;

use App\Enums\PpmpStatus;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Consolidated APP: every validated department PPMP for a fiscal year
 * rolled up per catalog item. It is derived on read — there is no consolidated
 * table and no file export.
 */
class AppConsolidationService
{
    /**
     * @return Collection<int, object>
     */
    public function getConsolidatedItems(int $fiscalYear): Collection
    {
        return PpmpItem::query()
            ->select([
                'app_items.id as app_item_id',
                'app_items.category',
                'app_items.item_code',
                'app_items.item_name',
                'app_items.unit_of_measure',
                DB::raw('SUM(ppmp_items.q1_quantity) as q1_quantity'),
                DB::raw('SUM(ppmp_items.q2_quantity) as q2_quantity'),
                DB::raw('SUM(ppmp_items.q3_quantity) as q3_quantity'),
                DB::raw('SUM(ppmp_items.q4_quantity) as q4_quantity'),
                DB::raw('SUM(ppmp_items.total_quantity) as total_quantity'),
                DB::raw('SUM(ppmp_items.estimated_total_cost) as estimated_total_cost'),
                DB::raw('COUNT(DISTINCT ppmps.department_id) as department_count'),
            ])
            ->join('ppmps', 'ppmps.id', '=', 'ppmp_items.ppmp_id')
            ->join('app_items', 'app_items.id', '=', 'ppmp_items.app_item_id')
            ->where('ppmps.fiscal_year', $fiscalYear)
            ->where('ppmps.status', PpmpStatus::Validated->value)
            ->groupBy(
                'app_items.id',
                'app_items.category',
                'app_items.item_code',
                'app_items.item_name',
                'app_items.unit_of_measure',
            )
            ->orderBy('app_items.category')
            ->orderBy('app_items.item_name')
            ->get()
            ->map(fn (PpmpItem $row): object => (object) [
                'app_item_id' => (int) $row->getAttribute('app_item_id'),
                'category' => (string) $row->getAttribute('category'),
                'item_code' => (string) $row->getAttribute('item_code'),
                'item_name' => (string) $row->getAttribute('item_name'),
                'unit_of_measure' => (string) $row->getAttribute('unit_of_measure'),
                'q1_quantity' => (int) $row->getAttribute('q1_quantity'),
                'q2_quantity' => (int) $row->getAttribute('q2_quantity'),
                'q3_quantity' => (int) $row->getAttribute('q3_quantity'),
                'q4_quantity' => (int) $row->getAttribute('q4_quantity'),
                'total_quantity' => (int) $row->getAttribute('total_quantity'),
                'estimated_total_cost' => (float) $row->getAttribute('estimated_total_cost'),
                'department_count' => (int) $row->getAttribute('department_count'),
            ]);
    }

    /**
     * @return array{
     *     ppmp_count: int,
     *     department_count: int,
     *     item_count: int,
     *     total_estimated_cost: float,
     * }
     */
    public function getStats(int $fiscalYear): array
    {
        $validatedPpmps = Ppmp::query()
            ->validated()
            ->where('fiscal_year', $fiscalYear);

        $items = $this->getConsolidatedItems($fiscalYear);

        return [
            'ppmp_count' => (clone $validatedPpmps)->count(),
            'department_count' => (clone $validatedPpmps)->distinct()->count('department_id'),
            'item_count' => $items->count(),
            'total_estimated_cost' => (float) $items->sum('estimated_total_cost'),
        ];
    }
}
