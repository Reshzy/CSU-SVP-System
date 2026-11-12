<?php

namespace App\Http\Controllers\Supply;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListQueryRequest;
use App\Http\Requests\Supply\UpdatePurchaseRequestDetailsRequest;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Notifications\PurchaseRequestStatusUpdated;
use App\Services\PurchaseRequestActivityLogger;
use App\Services\PurchaseRequestExportService;
use App\Services\WorkflowRouter;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseRequestController extends Controller
{
    use FlashesToasts;

    /**
     * Status actions the supply office may take, keyed by the current status.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_STATUS_ACTIONS = [
        'submitted' => ['start_review', 'return', 'reject', 'cancel'],
        'supply_office_review' => ['activate', 'return', 'reject', 'cancel'],
    ];

    /**
     * Statuses in which the supply office may edit details and manage lots.
     *
     * @var list<string>
     */
    private const EDITABLE_STATUSES = ['submitted', 'supply_office_review'];

    public function index(ListQueryRequest $request): Response
    {
        Gate::authorize('viewAny', PurchaseRequest::class);

        $queueStatuses = ['submitted', 'supply_office_review', 'returned_by_supply'];

        $query = PurchaseRequest::query()
            ->with(['department:id,name,code', 'requester:id,name'])
            ->whereIn('status', $queueStatuses)
            ->where('is_archived', false);

        return Inertia::render('supply/purchase-requests/index', [
            'purchaseRequests' => ListQuery::paginate($query, $request, [
                'searchable' => ['pr_number', 'pr_title'],
                'sortable' => [
                    'pr_number' => 'pr_number',
                    'pr_title' => 'pr_title',
                    'status' => 'status',
                    'estimated_total' => 'estimated_total',
                    'created_at' => 'created_at',
                ],
                'equals' => ['status' => 'status'],
                'dateColumn' => 'created_at',
                'defaultSort' => 'created_at',
                'defaultDirection' => 'desc',
                'defaultPerPage' => 20,
            ]),
            'filters' => ListQuery::snapshot($request, [
                'status' => $request->filled('status') ? $request->string('status')->value() : null,
            ]),
            'statusOptions' => collect($queueStatuses)
                ->map(fn (string $status): array => [
                    'value' => $status,
                    'label' => str_replace('_', ' ', $status),
                ])
                ->all(),
        ]);
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

        $isEditable = in_array($purchaseRequest->status, self::EDITABLE_STATUSES, true);

        return Inertia::render('supply/purchase-requests/show', [
            'purchaseRequest' => $purchaseRequest,
            'statusLabel' => $purchaseRequest->statusLabel(),
            'allowedActions' => self::ALLOWED_STATUS_ACTIONS[$purchaseRequest->status] ?? [],
            'canManageLots' => $isEditable,
            'canEditDetails' => $isEditable,
        ]);
    }

    public function updateStatus(
        Request $request,
        PurchaseRequest $purchaseRequest,
        WorkflowRouter $workflowRouter,
        PurchaseRequestActivityLogger $activityLogger,
    ): RedirectResponse {
        Gate::authorize('view', $purchaseRequest);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:start_review,activate,return,reject,cancel'],
            'remarks' => ['nullable', 'string'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $action = $validated['action'];
        $previousStatus = $purchaseRequest->status;

        if (! in_array($action, self::ALLOWED_STATUS_ACTIONS[$previousStatus] ?? [], true)) {
            return back()->withErrors(['action' => 'This action is not available for the current status.']);
        }

        if ($action === 'return' && blank($validated['remarks'] ?? null)) {
            return back()->withErrors(['remarks' => 'Remarks are required when returning a purchase request.']);
        }

        match ($action) {
            'start_review' => $purchaseRequest->status = 'supply_office_review',
            'activate' => $purchaseRequest->status = 'budget_office_review',
            'return' => $purchaseRequest->fill([
                'status' => 'returned_by_supply',
                'return_remarks' => $validated['remarks'],
                'returned_by' => $request->user()->id,
                'returned_at' => now(),
            ]),
            'reject' => $purchaseRequest->fill([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'] ?? $validated['remarks'] ?? 'Deferred by Supply Office',
                'rejected_by' => $request->user()->id,
                'rejected_at' => now(),
            ]),
            'cancel' => $purchaseRequest->status = 'cancelled',
        };

        $purchaseRequest->save();

        if ($action === 'activate') {
            $workflowRouter->createPendingForRole($purchaseRequest, 'budget_office_earmarking', 'Budget Office');
        }

        if ($action === 'return') {
            $activityLogger->log($purchaseRequest, 'returned', 'Returned by Supply Office.', oldValue: ['status' => $previousStatus], newValue: ['status' => $purchaseRequest->status]);
        }

        if ($action === 'reject') {
            $activityLogger->log($purchaseRequest, 'rejected', 'Deferred by Supply Office.', oldValue: ['status' => $previousStatus], newValue: ['status' => $purchaseRequest->status]);
        }

        if ($purchaseRequest->requester) {
            $purchaseRequest->requester->notify(new PurchaseRequestStatusUpdated($purchaseRequest, $previousStatus));
        }

        $this->toast("Purchase request {$purchaseRequest->pr_number} updated.");

        return redirect()->route('supply.purchase-requests.show', $purchaseRequest);
    }

    public function updateDetails(
        UpdatePurchaseRequestDetailsRequest $request,
        PurchaseRequest $purchaseRequest,
        PurchaseRequestActivityLogger $activityLogger,
    ): RedirectResponse {
        $this->ensureEditable($purchaseRequest);

        $validated = $request->validated();
        $oldValue = [];
        $newValue = [];

        DB::transaction(function () use ($purchaseRequest, $validated, &$oldValue, &$newValue) {
            foreach (['purpose', 'justification'] as $field) {
                $incoming = $validated[$field] ?? null;

                if ($purchaseRequest->{$field} !== $incoming) {
                    $oldValue[$field] = $purchaseRequest->{$field};
                    $newValue[$field] = $incoming;
                    $purchaseRequest->{$field} = $incoming;
                }
            }

            $purchaseRequest->save();

            $rows = collect($validated['items'] ?? []);
            $items = $purchaseRequest->items()
                ->whereIn('id', $rows->pluck('id'))
                ->get()
                ->keyBy('id');

            foreach ($rows as $row) {
                $item = $items->get($row['id']);
                $incoming = $row['detailed_specifications'] ?? null;

                if ($item === null || $item->detailed_specifications === $incoming) {
                    continue;
                }

                $oldValue['items'][$item->id] = $item->detailed_specifications;
                $newValue['items'][$item->id] = $incoming;
                $item->update(['detailed_specifications' => $incoming]);
            }
        });

        if ($newValue !== []) {
            $activityLogger->log(
                $purchaseRequest,
                'updated',
                'Details edited by Supply Office.',
                oldValue: $oldValue,
                newValue: $newValue,
            );
        }

        $this->toast('Purchase request details updated.');

        return back();
    }

    public function storeLot(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->ensureLotsAllowed($purchaseRequest);

        $validated = $request->validate([
            'lot_name' => ['required', 'string', 'max:255'],
            'item_ids' => ['required', 'array', 'min:2'],
            'item_ids.*' => ['integer', 'exists:purchase_request_items,id'],
        ]);

        $items = PurchaseRequestItem::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->whereIn('id', $validated['item_ids'])
            ->whereNull('parent_lot_id')
            ->where('is_lot', false)
            ->get();

        if ($items->count() < 2) {
            return back()->withErrors(['item_ids' => 'A lot requires at least two standalone items.']);
        }

        DB::transaction(function () use ($purchaseRequest, $validated, $items) {
            $total = (float) $items->sum(fn (PurchaseRequestItem $item) => (float) $item->estimated_total_cost);

            $lot = $purchaseRequest->items()->create([
                'is_lot' => true,
                'lot_name' => $validated['lot_name'],
                'item_name' => $validated['lot_name'],
                'unit_of_measure' => 'lot',
                'quantity_requested' => 1,
                'estimated_unit_cost' => $total,
                'estimated_total_cost' => $total,
                'item_status' => 'pending',
                'procurement_status' => 'pending',
            ]);

            PurchaseRequestItem::query()
                ->whereIn('id', $items->pluck('id'))
                ->update(['parent_lot_id' => $lot->id]);

            $purchaseRequest->recalculateEstimatedTotal();
        });

        $this->toast('Lot created.');

        return back();
    }

    public function updateLot(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestItem $lot): RedirectResponse
    {
        $this->ensureLotsAllowed($purchaseRequest);
        abort_unless($lot->purchase_request_id === $purchaseRequest->id && $lot->is_lot, 404);

        $validated = $request->validate([
            'lot_name' => ['required', 'string', 'max:255'],
            'item_ids' => ['required', 'array', 'min:2'],
            'item_ids.*' => ['integer', 'exists:purchase_request_items,id'],
        ]);

        $items = PurchaseRequestItem::query()
            ->where('purchase_request_id', $purchaseRequest->id)
            ->whereIn('id', $validated['item_ids'])
            ->where('is_lot', false)
            ->where(function ($query) use ($lot) {
                $query->whereNull('parent_lot_id')->orWhere('parent_lot_id', $lot->id);
            })
            ->get();

        if ($items->count() < 2) {
            return back()->withErrors(['item_ids' => 'A lot requires at least two standalone items.']);
        }

        DB::transaction(function () use ($lot, $validated, $items, $purchaseRequest) {
            PurchaseRequestItem::query()
                ->where('parent_lot_id', $lot->id)
                ->update(['parent_lot_id' => null]);

            $total = (float) $items->sum(fn (PurchaseRequestItem $item) => (float) $item->estimated_total_cost);

            $lot->update([
                'lot_name' => $validated['lot_name'],
                'item_name' => $validated['lot_name'],
                'estimated_unit_cost' => $total,
                'estimated_total_cost' => $total,
            ]);

            PurchaseRequestItem::query()
                ->whereIn('id', $items->pluck('id'))
                ->update(['parent_lot_id' => $lot->id]);

            $purchaseRequest->recalculateEstimatedTotal();
        });

        $this->toast('Lot updated.');

        return back();
    }

    public function destroyLot(PurchaseRequest $purchaseRequest, PurchaseRequestItem $lot): RedirectResponse
    {
        $this->ensureLotsAllowed($purchaseRequest);
        abort_unless($lot->purchase_request_id === $purchaseRequest->id && $lot->is_lot, 404);

        DB::transaction(function () use ($lot, $purchaseRequest) {
            PurchaseRequestItem::query()
                ->where('parent_lot_id', $lot->id)
                ->update(['parent_lot_id' => null]);

            $lot->delete();
            $purchaseRequest->recalculateEstimatedTotal();
        });

        $this->toast('Lot removed.');

        return back();
    }

    public function export(PurchaseRequest $purchaseRequest, PurchaseRequestExportService $exportService): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('view', $purchaseRequest);

        try {
            return $exportService->download($purchaseRequest);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['export' => 'Unable to export this purchase request.']);
        }
    }

    private function ensureLotsAllowed(PurchaseRequest $purchaseRequest): void
    {
        Gate::authorize('view', $purchaseRequest);

        $this->ensureEditable($purchaseRequest);
    }

    private function ensureEditable(PurchaseRequest $purchaseRequest): void
    {
        abort_unless(in_array($purchaseRequest->status, self::EDITABLE_STATUSES, true), 403);
    }
}
