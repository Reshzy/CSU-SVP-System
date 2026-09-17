<?php

namespace App\Http\Controllers;

use App\Concerns\FlashesToasts;
use App\Enums\PpmpStatus;
use App\Http\Requests\PurchaseRequest\StorePurchaseRequestRequest;
use App\Models\Ppmp;
use App\Models\PpmpItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\User;
use App\Notifications\PurchaseRequestSubmitted;
use App\Services\PpmpQuarterlyTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestController extends Controller
{
    use FlashesToasts;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PurchaseRequest::class);

        $user = $request->user();

        $query = PurchaseRequest::query()
            ->with(['department:id,name,code', 'requester:id,name'])
            ->latest();

        if (! $user->hasAnyRole(['Supply Officer', 'Budget Office', 'Executive Officer', 'BAC Chair', 'BAC Members', 'BAC Secretariat', 'System Admin'])) {
            $query->where('department_id', $user->department_id);
        }

        return Inertia::render('purchase-requests/index', [
            'purchaseRequests' => $query->paginate(15)->withQueryString(),
            'canCreate' => Gate::allows('create', PurchaseRequest::class),
        ]);
    }

    public function create(Request $request, PpmpQuarterlyTracker $tracker): Response
    {
        Gate::authorize('create', PurchaseRequest::class);

        $user = $request->user();
        $fiscalYear = $tracker->currentFiscalYear();
        $quarter = $tracker->currentQuarter();

        $ppmp = Ppmp::query()
            ->where('department_id', $user->department_id)
            ->where('fiscal_year', $fiscalYear)
            ->where('status', PpmpStatus::Validated)
            ->with(['items.appItem'])
            ->first();

        $availableItems = $ppmp === null
            ? []
            : $ppmp->items->map(function (PpmpItem $item) use ($quarter) {
                return [
                    'id' => $item->id,
                    'app_item_id' => $item->app_item_id,
                    'item_code' => $item->appItem?->item_code,
                    'item_name' => $item->appItem?->item_name,
                    'unit_of_measure' => $item->appItem?->unit_of_measure,
                    'item_category' => $item->appItem?->category,
                    'detailed_specifications' => $item->appItem?->specifications,
                    'estimated_unit_cost' => $item->estimated_unit_cost,
                    'planned_qty' => $item->getQuarterlyQuantity($quarter),
                    'remaining_qty' => $item->getRemainingQuantity($quarter),
                ];
            })->values();

        return Inertia::render('purchase-requests/create', [
            'fiscalYear' => $fiscalYear,
            'quarter' => $quarter,
            'hasValidatedPpmp' => $ppmp !== null,
            'availableItems' => $availableItems,
            'fundClusters' => [
                ['value' => '01', 'label' => '01 - Regular Agency Fund'],
                ['value' => '05', 'label' => '05 - Off-Budgetary Funds'],
                ['value' => '06', 'label' => '06 - Internally Generated Income (IGE)'],
                ['value' => '07', 'label' => '07 - Trust Receipts'],
            ],
        ]);
    }

    public function store(StorePurchaseRequestRequest $request, PpmpQuarterlyTracker $tracker): RedirectResponse
    {
        Gate::authorize('create', PurchaseRequest::class);

        $user = $request->user();
        $quarter = $tracker->currentQuarter();
        $fiscalYear = $tracker->currentFiscalYear();

        $ppmp = Ppmp::query()
            ->where('department_id', $user->department_id)
            ->where('fiscal_year', $fiscalYear)
            ->where('status', PpmpStatus::Validated)
            ->firstOrFail();

        $payloadItems = $request->validated('items');
        $estimatedTotal = 0.0;
        $resolvedRows = [];

        foreach ($payloadItems as $index => $row) {
            $isLot = (bool) ($row['is_lot'] ?? false);

            if ($isLot) {
                $resolvedRows[$index] = [
                    'is_lot' => true,
                    'lot_name' => $row['lot_name'] ?? 'Lot',
                    'quantity_requested' => 1,
                    'estimated_unit_cost' => 0,
                    'estimated_total_cost' => 0,
                    'unit_of_measure' => 'lot',
                    'parent_lot_index' => null,
                    'ppmp_item_id' => null,
                ];

                continue;
            }

            $ppmpItem = PpmpItem::query()
                ->with('appItem')
                ->whereKey($row['ppmp_item_id'])
                ->where('ppmp_id', $ppmp->id)
                ->firstOrFail();

            $qty = (int) $row['quantity_requested'];
            $unitCost = (float) $ppmpItem->estimated_unit_cost;
            $lineTotal = $qty * $unitCost;
            $estimatedTotal += $lineTotal;

            $resolvedRows[$index] = [
                'is_lot' => false,
                'ppmp_item_id' => $ppmpItem->id,
                'ppmp_quarter' => $quarter,
                'ppmp_planned_qty_for_quarter' => $ppmpItem->getQuarterlyQuantity($quarter),
                'ppmp_remaining_qty_at_creation' => $ppmpItem->getRemainingQuantity($quarter),
                'item_code' => $ppmpItem->appItem?->item_code,
                'item_name' => $ppmpItem->appItem?->item_name,
                'detailed_specifications' => $ppmpItem->appItem?->specifications,
                'unit_of_measure' => $ppmpItem->appItem?->unit_of_measure ?? 'pc',
                'item_category' => $ppmpItem->appItem?->category,
                'quantity_requested' => $qty,
                'estimated_unit_cost' => $unitCost,
                'estimated_total_cost' => $lineTotal,
                'parent_lot_index' => $row['parent_lot_index'] ?? null,
            ];
        }

        foreach ($resolvedRows as $index => $row) {
            if (! ($row['is_lot'] ?? false)) {
                continue;
            }

            $childTotal = 0.0;

            foreach ($resolvedRows as $child) {
                if (($child['parent_lot_index'] ?? null) === $index) {
                    $childTotal += (float) $child['estimated_total_cost'];
                }
            }

            $resolvedRows[$index]['estimated_unit_cost'] = $childTotal;
            $resolvedRows[$index]['estimated_total_cost'] = $childTotal;
            $estimatedTotal += $childTotal;
        }

        $purchaseRequest = DB::transaction(function () use ($request, $user, $ppmp, $resolvedRows, $estimatedTotal) {
            $purchaseRequest = PurchaseRequest::query()->create([
                ...$request->purchaseRequestAttributes(),
                'department_id' => $user->department_id,
                'requester_id' => $user->id,
                'status' => 'supply_office_review',
                'estimated_total' => $estimatedTotal,
                'ppmp_reference' => "PPMP-{$ppmp->fiscal_year}",
                'submitted_at' => now(),
            ]);

            $createdByIndex = [];

            foreach ($resolvedRows as $index => $row) {
                if (($row['parent_lot_index'] ?? null) !== null) {
                    continue;
                }

                $createdByIndex[$index] = $this->createItem($purchaseRequest, $row);
            }

            foreach ($resolvedRows as $index => $row) {
                if (($row['parent_lot_index'] ?? null) === null) {
                    continue;
                }

                $parentIndex = (int) $row['parent_lot_index'];
                $parent = $createdByIndex[$parentIndex] ?? null;

                $this->createItem($purchaseRequest, [
                    ...$row,
                    'parent_lot_id' => $parent?->id,
                ]);
            }

            return $purchaseRequest->fresh(['items']);
        });

        $supplyOfficers = User::role('Supply Officer')->get();

        if ($supplyOfficers->isNotEmpty()) {
            Notification::send($supplyOfficers, new PurchaseRequestSubmitted($purchaseRequest));
        }

        $this->toast("Purchase request {$purchaseRequest->pr_number} submitted to Supply Office.");

        return redirect()->route('purchase-requests.show', $purchaseRequest);
    }

    public function show(PurchaseRequest $purchaseRequest): Response
    {
        Gate::authorize('view', $purchaseRequest);

        $purchaseRequest->load([
            'department:id,name,code',
            'requester:id,name,email',
            'items',
            'activities.user:id,name',
        ]);

        return Inertia::render('purchase-requests/show', [
            'purchaseRequest' => $purchaseRequest,
            'statusLabel' => $purchaseRequest->statusLabel(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createItem(PurchaseRequest $purchaseRequest, array $row): PurchaseRequestItem
    {
        return $purchaseRequest->items()->create([
            'ppmp_item_id' => $row['ppmp_item_id'] ?? null,
            'ppmp_quarter' => $row['ppmp_quarter'] ?? null,
            'ppmp_planned_qty_for_quarter' => $row['ppmp_planned_qty_for_quarter'] ?? null,
            'ppmp_remaining_qty_at_creation' => $row['ppmp_remaining_qty_at_creation'] ?? null,
            'is_lot' => (bool) ($row['is_lot'] ?? false),
            'lot_name' => $row['lot_name'] ?? null,
            'parent_lot_id' => $row['parent_lot_id'] ?? null,
            'item_code' => $row['item_code'] ?? null,
            'item_name' => $row['item_name'] ?? ($row['lot_name'] ?? null),
            'detailed_specifications' => $row['detailed_specifications'] ?? null,
            'unit_of_measure' => $row['unit_of_measure'] ?? 'pc',
            'quantity_requested' => $row['quantity_requested'],
            'estimated_unit_cost' => $row['estimated_unit_cost'],
            'estimated_total_cost' => $row['estimated_total_cost'],
            'item_category' => $row['item_category'] ?? null,
            'item_status' => 'pending',
            'procurement_status' => 'pending',
        ]);
    }
}
