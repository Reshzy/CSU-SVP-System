<?php

namespace App\Http\Controllers;

use App\Concerns\FlashesToasts;
use App\Http\Requests\PsDbms\ImportAppItemsRequest;
use App\Models\AppItem;
use App\Services\AppItemImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function index(Request $request): Response
    {
        $fiscalYear = (int) ($request->integer('fiscal_year') ?: date('Y'));

        $appItems = AppItem::query()
            ->forFiscalYear($fiscalYear)
            ->when($request->filled('category'), function ($query) use ($request): void {
                $query->where('category', $request->string('category')->value());
            })
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(fn ($q) => $q->where('item_name', 'like', $search)->orWhere('item_code', 'like', $search));
            })
            ->orderBy('category')
            ->orderBy('item_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('reference/ps-dbms/index', [
            'appItems' => $appItems,
            'filters' => [
                'fiscal_year' => $fiscalYear,
                'category' => $request->string('category')->value(),
                'search' => $request->string('search')->value(),
            ],
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
