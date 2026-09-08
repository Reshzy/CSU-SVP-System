<?php

namespace App\Http\Controllers;

use App\Concerns\FlashesToasts;
use App\Http\Requests\Ppmp\ImportPpmpRequest;
use App\Http\Requests\Ppmp\StorePpmpRequest;
use App\Models\AppItem;
use App\Models\Ppmp;
use App\Models\User;
use App\Services\PpmpImporter;
use App\Services\PpmpQuarterlyTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A department's procurement plan for a fiscal year. Plans are scoped to the
 * signed-in user's department — there is one per department per year, created
 * on first use — and a purchase request can only draw from a validated plan.
 */
class PpmpController extends Controller
{
    use FlashesToasts;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $ppmps = Ppmp::query()
            ->where('department_id', $user->department_id)
            ->withCount('items')
            ->orderByDesc('fiscal_year')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ppmp/index', [
            'ppmps' => $ppmps,
            'department' => $user->department()->first(['id', 'name', 'code']),
            'fiscalYear' => (int) date('Y'),
        ]);
    }

    public function create(Request $request, PpmpQuarterlyTracker $tracker): Response
    {
        $user = $this->userWithDepartment($request);
        $fiscalYear = (int) ($request->integer('fiscal_year') ?: $tracker->currentFiscalYear());

        $ppmp = Ppmp::getOrCreateForDepartment($user->department_id, $fiscalYear);

        Gate::authorize('update', $ppmp);

        return $this->renderEditor($ppmp, 'ppmp/create');
    }

    public function store(StorePpmpRequest $request, PpmpQuarterlyTracker $tracker): RedirectResponse
    {
        $user = $this->userWithDepartment($request);
        $fiscalYear = (int) ($request->integer('fiscal_year') ?: $tracker->currentFiscalYear());

        $ppmp = Ppmp::getOrCreateForDepartment($user->department_id, $fiscalYear);

        Gate::authorize('update', $ppmp);

        $this->replaceItems($ppmp, $request);

        $this->toast("Saved the {$fiscalYear} plan.");

        return redirect()->route('ppmp.summary', $ppmp);
    }

    public function edit(Ppmp $ppmp): Response
    {
        Gate::authorize('update', $ppmp);

        return $this->renderEditor($ppmp, 'ppmp/edit');
    }

    public function update(StorePpmpRequest $request, Ppmp $ppmp): RedirectResponse
    {
        Gate::authorize('update', $ppmp);

        $this->replaceItems($ppmp, $request);

        $this->toast("Saved the {$ppmp->fiscal_year} plan.");

        return redirect()->route('ppmp.summary', $ppmp);
    }

    /**
     * Lock the plan in as the basis for purchase requests.
     */
    public function validate(Request $request, Ppmp $ppmp): RedirectResponse
    {
        Gate::authorize('validatePlan', $ppmp);

        if ($ppmp->isValidated()) {
            $this->toast('That plan is already validated.', 'info');

            return back();
        }

        if ($ppmp->items()->doesntExist()) {
            $this->toast('Add items to the plan before validating it.', 'warning');

            return back();
        }

        $ppmp->markValidated($request->user());

        $this->toast("Validated the {$ppmp->fiscal_year} plan.");

        return redirect()->route('ppmp.summary', $ppmp);
    }

    public function summary(Ppmp $ppmp, PpmpQuarterlyTracker $tracker): Response
    {
        Gate::authorize('view', $ppmp);

        $ppmp->load(['department:id,name,code', 'validator:id,name']);
        $currentQuarter = $tracker->currentQuarter();

        return Inertia::render('ppmp/summary', [
            'ppmp' => $ppmp,
            'currentQuarter' => $currentQuarter,
            'items' => $ppmp->items()
                ->with('appItem:id,category,item_code,item_name,unit_of_measure')
                ->get()
                ->map(fn ($item): array => [
                    'id' => $item->id,
                    'app_item' => $item->appItem,
                    'q1_quantity' => $item->q1_quantity,
                    'q2_quantity' => $item->q2_quantity,
                    'q3_quantity' => $item->q3_quantity,
                    'q4_quantity' => $item->q4_quantity,
                    'total_quantity' => $item->total_quantity,
                    'estimated_unit_cost' => $item->estimated_unit_cost,
                    'estimated_total_cost' => $item->estimated_total_cost,
                    'remaining_quantity' => $item->getRemainingQuantity(),
                    'remaining_this_quarter' => $item->getRemainingQuantity($currentQuarter),
                ]),
        ]);
    }

    public function import(Request $request, PpmpQuarterlyTracker $tracker): Response
    {
        $user = $this->userWithDepartment($request);

        return Inertia::render('ppmp/import', [
            'fiscalYear' => $tracker->currentFiscalYear(),
            'department' => $user->department()->first(['id', 'name', 'code']),
        ]);
    }

    public function processImport(ImportPpmpRequest $request, PpmpImporter $importer): RedirectResponse
    {
        $user = $this->userWithDepartment($request);
        $fiscalYear = (int) $request->validated('fiscal_year');

        $ppmp = Ppmp::getOrCreateForDepartment($user->department_id, $fiscalYear);

        Gate::authorize('update', $ppmp);

        $path = $request->file('csv_file')->store('imports');

        try {
            $summary = $importer->import(Storage::path($path), $ppmp);
        } finally {
            Storage::delete($path);
        }

        $this->toast(sprintf(
            'Imported %d new and %d updated lines. Skipped %d not in the %d catalog and %d with no quantity.',
            $summary['created'],
            $summary['updated'],
            $summary['skipped_missing_catalog'],
            $fiscalYear,
            $summary['skipped_zero_quantity'],
        ));

        return redirect()->route('ppmp.summary', $ppmp);
    }

    /**
     * The plan editor is the whole active catalog for the year with the
     * department's planned quantities filled in, so a single save can add,
     * change, and drop lines at once.
     */
    private function renderEditor(Ppmp $ppmp, string $component): Response
    {
        $ppmp->load('department:id,name,code');

        return Inertia::render($component, [
            'ppmp' => $ppmp,
            'appItems' => AppItem::query()
                ->active()
                ->forFiscalYear($ppmp->fiscal_year)
                ->orderBy('category')
                ->orderBy('item_name')
                ->get(['id', 'category', 'item_code', 'item_name', 'unit_of_measure', 'unit_price']),
            'plannedItems' => $ppmp->items()
                ->get(['app_item_id', 'q1_quantity', 'q2_quantity', 'q3_quantity', 'q4_quantity', 'estimated_unit_cost']),
        ]);
    }

    /**
     * Replace every line in one pass. The editor always posts the full plan,
     * so lines it leaves out are the ones the department dropped.
     */
    private function replaceItems(Ppmp $ppmp, StorePpmpRequest $request): void
    {
        $items = $request->plannedItems();

        DB::transaction(function () use ($ppmp, $items): void {
            $ppmp->items()->delete();

            $catalogPrices = AppItem::query()
                ->whereIn('id', array_column($items, 'app_item_id'))
                ->pluck('unit_price', 'id');

            foreach ($items as $item) {
                $totalQuantity = array_sum($item['quarters']);
                $unitCost = (float) ($item['custom_unit_price'] ?? $catalogPrices[$item['app_item_id']]);

                $ppmp->items()->create([
                    'app_item_id' => $item['app_item_id'],
                    'q1_quantity' => $item['quarters'][1],
                    'q2_quantity' => $item['quarters'][2],
                    'q3_quantity' => $item['quarters'][3],
                    'q4_quantity' => $item['quarters'][4],
                    'total_quantity' => $totalQuantity,
                    'estimated_unit_cost' => $unitCost,
                    'estimated_total_cost' => $totalQuantity * $unitCost,
                ]);
            }

            $ppmp->recalculateTotalCost();
        });
    }

    /**
     * Planning is department work; an account with no department has nothing
     * to plan against.
     */
    private function userWithDepartment(Request $request): User
    {
        $user = $request->user();

        abort_if($user->department_id === null, 403, 'Your account is not attached to a department.');

        return $user;
    }
}
