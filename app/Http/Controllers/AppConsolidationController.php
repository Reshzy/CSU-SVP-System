<?php

namespace App\Http\Controllers;

use App\Models\Ppmp;
use App\Services\AppConsolidationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Consolidated APP: validated department PPMPs rolled up per catalog item.
 * Read-only by design — there is no download.
 */
class AppConsolidationController extends Controller
{
    public function __invoke(Request $request, AppConsolidationService $consolidation): Response
    {
        $fiscalYear = (int) ($request->integer('fiscal_year') ?: date('Y'));

        return Inertia::render('bac/app/index', [
            'items' => $consolidation->getConsolidatedItems($fiscalYear),
            'stats' => $consolidation->getStats($fiscalYear),
            'fiscalYear' => $fiscalYear,
            'fiscalYears' => Ppmp::query()
                ->distinct()
                ->orderByDesc('fiscal_year')
                ->pluck('fiscal_year'),
        ]);
    }
}
