<?php

namespace App\Http\Controllers;

use App\Concerns\FlashesToasts;
use App\Enums\PurchaseRequestStatus;
use App\Http\Requests\PurchaseRequest\StorePurchaseRequestRequest;
use App\Models\DepartmentBudget;
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
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestController extends Controller
{
    use FlashesToasts;

    public function index(Request $request): Response
    {
        $user = $request->user();

        Gate::authorize('viewAny', PurchaseRequest::class);

        $requests = PurchaseRequest::query()
            ->where('requester_id', $user->id)
            ->where('is_archived', false)
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('purchase-requests/index', [
            'requests' => $requests,
            'canCreate' => $user->canCreatePurchaseRequests() && $user->department_id !== null,
        ]);
    }

    public function create(Request $request, PpmpQuarterlyTracker $tracker): Response
    {
        $user = $this->userWhoCanCreate($request);

        Gate::authorize('create', PurchaseRequest::class);

        $data = $this->preparePrCreationData($user, $tracker);

        if ($data['ppmp'] === null) {
            abort(403, 'Your department must have a validated PPMP before creating purchase requests.');
        }

        return Inertia::render('purchase-requests/create', $data);
    }

    public function store(StorePurchaseRequestRequest $request, PpmpQuarterlyTracker $tracker): RedirectResponse
    {
        $budgetCheck = $request->checkBudgetAvailability();

        if (! $budgetCheck['can_reserve']) {
            return back()
                ->withInput()
                ->withErrors(['budget' => $budgetCheck['error']]);
        }

        $user = $request->user();
        $validated = $request->validated();
        $totalCost = $request->calculateTotalCost();

        $purchaseRequest = DB::transaction(function () use ($validated, $user, $totalCost, $tracker): PurchaseRequest {
            $purchaseRequest = PurchaseRequest::query()->create([
                'pr_number' => PurchaseRequest::generateNextPrNumber(),
                'requester_id' => $user->id,
                'department_id' => $user->department_id,
                'purpose' => $validated['purpose'],
                'justification' => $validated['justification'],
                'estimated_total' => $totalCost,
                'status' => PurchaseRequestStatus::SupplyOfficeReview,
                'submitted_at' => now(),
                'status_updated_at' => now(),
                'has_ppmp' => true,
            ]);

            $this->createPurchaseRequestItems($purchaseRequest, $validated['items'], $tracker);
            $this->notifySupplyOffice($purchaseRequest);

            return $purchaseRequest;
        });

        $this->toast("Submitted {$purchaseRequest->pr_number}.");

        return redirect()->route('purchase-requests.show', $purchaseRequest);
    }

    public function show(PurchaseRequest $purchaseRequest): Response
    {
        Gate::authorize('view', $purchaseRequest);

        $purchaseRequest->load([
            'department:id,name,code',
            'requester:id,name',
            'items' => fn ($query) => $query->orderBy('id'),
        ]);

        return Inertia::render('purchase-requests/show', [
            'purchaseRequest' => $purchaseRequest,
        ]);
    }

    /**
     * @return array{ppmp: Ppmp, ppmpCategories: list<string>, categorizedItems: array<string, list<array<string, mixed>>>, departmentBudget: array<string, mixed>, fiscalYear: int, currentQuarter: int, quarterLabel: string}
     */
    private function preparePrCreationData(User $user, PpmpQuarterlyTracker $tracker): array
    {
        $fiscalYear = $tracker->currentFiscalYear();
        $currentQuarter = $tracker->currentQuarter();

        $ppmp = Ppmp::query()
            ->where('department_id', $user->department_id)
            ->where('fiscal_year', $fiscalYear)
            ->validated()
            ->with(['items.appItem'])
            ->first();

        $categorizedItems = [];
        $ppmpCategories = [];

        if ($ppmp !== null) {
            $grouped = $ppmp->items
                ->filter(fn (PpmpItem $item): bool => $item->appItem !== null)
                ->groupBy(fn (PpmpItem $item): string => $item->appItem->category);

            $ppmpCategories = $grouped->keys()->sort()->values()->all();

            $categorizedItems = $grouped->map(function ($items) use ($currentQuarter) {
                return $items->map(fn (PpmpItem $item): array => [
                    'id' => $item->id,
                    'app_item_id' => $item->app_item_id,
                    'item_code' => $item->appItem->item_code,
                    'item_name' => $item->appItem->item_name,
                    'unit_of_measure' => $item->appItem->unit_of_measure,
                    'category' => $item->appItem->category,
                    'estimated_unit_cost' => $item->estimated_unit_cost,
                    'current_quarter_qty' => $item->getQuarterlyQuantity($currentQuarter),
                    'remaining_qty' => $item->getRemainingQuantity($currentQuarter),
                    'has_current_quarter_qty' => $item->hasQuantityForQuarter($currentQuarter),
                ])->values()->all();
            })->all();
        }

        $budget = DepartmentBudget::getOrCreateForDepartment($user->department_id, $fiscalYear);

        return [
            'ppmp' => $ppmp,
            'ppmpCategories' => $ppmpCategories,
            'categorizedItems' => $categorizedItems,
            'departmentBudget' => [
                'allocated_budget' => (float) $budget->allocated_budget,
                'utilized_budget' => (float) $budget->utilized_budget,
                'reserved_budget' => (float) $budget->reserved_budget,
                'available_budget' => $budget->getAvailableBudget(),
            ],
            'fiscalYear' => $fiscalYear,
            'currentQuarter' => $currentQuarter,
            'quarterLabel' => $tracker->quarterLabel($currentQuarter),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function createPurchaseRequestItems(
        PurchaseRequest $purchaseRequest,
        array $items,
        PpmpQuarterlyTracker $tracker,
    ): void {
        $currentQuarter = $tracker->currentQuarter();
        $createdByIndex = [];

        foreach ($items as $index => $itemData) {
            $isLot = ! empty($itemData['is_lot']);
            $quantity = $isLot ? 1 : (int) $itemData['quantity_requested'];
            $unitCost = (float) $itemData['estimated_unit_cost'];

            $prItemData = [
                'purchase_request_id' => $purchaseRequest->id,
                'ppmp_item_id' => $itemData['ppmp_item_id'] ?? null,
                'item_code' => $itemData['item_code'] ?? null,
                'item_name' => $itemData['item_name'],
                'detailed_specifications' => $itemData['detailed_specifications'] ?? null,
                'unit_of_measure' => $isLot ? 'lot' : $itemData['unit_of_measure'],
                'quantity_requested' => $quantity,
                'estimated_unit_cost' => $unitCost,
                'estimated_total_cost' => $unitCost * $quantity,
                'ppmp_quarter' => $currentQuarter,
                'is_lot' => $isLot,
                'lot_name' => $isLot ? ($itemData['lot_name'] ?? null) : null,
                'parent_lot_id' => null,
                'item_status' => 'pending',
                'procurement_status' => 'pending',
            ];

            if (isset($itemData['parent_lot_index']) && $itemData['parent_lot_index'] !== '' && isset($createdByIndex[(int) $itemData['parent_lot_index']])) {
                $prItemData['parent_lot_id'] = $createdByIndex[(int) $itemData['parent_lot_index']]->id;
            }

            if (! $isLot && ! empty($itemData['ppmp_item_id'])) {
                $ppmpItem = PpmpItem::query()->with('appItem')->find($itemData['ppmp_item_id']);

                if ($ppmpItem !== null) {
                    $prItemData['item_category'] = $ppmpItem->appItem?->category;
                    $prItemData['ppmp_planned_qty_for_quarter'] = $ppmpItem->getQuarterlyQuantity($currentQuarter);
                    $prItemData['ppmp_remaining_qty_at_creation'] = $ppmpItem->getRemainingQuantity($currentQuarter);
                }
            }

            $createdByIndex[$index] = PurchaseRequestItem::query()->create($prItemData);
        }
    }

    private function notifySupplyOffice(PurchaseRequest $purchaseRequest): void
    {
        User::role('Supply Officer')->get()->each(
            fn (User $user) => $user->notify(new PurchaseRequestSubmitted($purchaseRequest)),
        );
    }

    private function userWhoCanCreate(Request $request): User
    {
        $user = $request->user();

        abort_unless($user->canCreatePurchaseRequests(), 403, 'System Administrators are not allowed to create purchase requests.');
        abort_if($user->department_id === null, 403, 'You must be assigned to a department to create purchase requests.');

        return $user;
    }
}
