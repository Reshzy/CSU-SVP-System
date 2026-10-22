<?php

namespace App\Http\Controllers;

use App\Concerns\FlashesToasts;
use App\Http\Requests\ListQueryRequest;
use App\Http\Requests\PsDbms\ImportAppItemsRequest;
use App\Models\AppItem;
use App\Services\AppItemImporter;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The PS-DBMS reference catalog, maintained by the BAC Secretariat. Everything
 * downstream — PPMPs and the purchase requests drawn from them — points at
 * rows created here, so a fiscal year must be imported before it can be planned.
 */
class AppItemController extends Controller
{
    use FlashesToasts;

    public function index(ListQueryRequest $request): Response
    {
        $fiscalYear = (int) ($request->integer('fiscal_year') ?: date('Y'));

        $appItems = ListQuery::paginate(
            AppItem::query()->forFiscalYear($fiscalYear),
            $request,
            [
                'searchable' => ['item_code', 'item_name'],
                'sortable' => [
                    'item_code' => 'item_code',
                    'item_name' => 'item_name',
                    'category' => 'category',
                    'unit_of_measure' => 'unit_of_measure',
                    'unit_price' => 'unit_price',
                ],
                'equals' => ['category' => 'category'],
                'defaultSort' => 'category',
                'defaultDirection' => 'asc',
                'defaultPerPage' => 15,
            ],
        );

        return Inertia::render('reference/ps-dbms/index', [
            'appItems' => $appItems,
            'filters' => ListQuery::snapshot($request, [
                'fiscal_year' => $fiscalYear,
                'category' => $request->filled('category') ? $request->string('category')->value() : null,
            ]),
            'categories' => AppItem::query()
                ->forFiscalYear($fiscalYear)
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
            'fiscalYears' => AppItem::query()
                ->distinct()
                ->orderByDesc('fiscal_year')
                ->pluck('fiscal_year'),
            'stats' => [
                'total' => AppItem::query()->forFiscalYear($fiscalYear)->count(),
                'active' => AppItem::query()->forFiscalYear($fiscalYear)->active()->count(),
            ],
        ]);
    }

    public function import(): Response
    {
        return Inertia::render('reference/ps-dbms/import', [
            'fiscalYear' => (int) date('Y'),
        ]);
    }

    public function processImport(ImportAppItemsRequest $request, AppItemImporter $importer): RedirectResponse
    {
        $fiscalYear = (int) $request->validated('fiscal_year');

        $path = $request->file('csv_file')->store('imports');

        try {
            $summary = $importer->import(Storage::path($path), $fiscalYear);
        } finally {
            Storage::delete($path);
        }

        $this->toast(sprintf(
            'Imported %d new and %d updated catalog items for %d. Skipped %d unpriced rows.',
            $summary['created'],
            $summary['updated'],
            $fiscalYear,
            $summary['skipped'],
        ));

        return redirect()->route('ps-dbms.index', ['fiscal_year' => $fiscalYear]);
    }
}
