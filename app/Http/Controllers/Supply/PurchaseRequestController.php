<?php

namespace App\Http\Controllers\Supply;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Notifications\PurchaseRequestStatusUpdated;
use App\Services\PurchaseRequestActivityLogger;
use App\Services\PurchaseRequestExportService;
use App\Services\WorkflowRouter;
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

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PurchaseRequest::class);

        $purchaseRequests = PurchaseRequest::query()
            ->with(['department:id,name,code', 'requester:id,name'])
            ->whereIn('status', ['submitted', 'supply_office_review', 'returned_by_supply'])
            ->where('is_archived', false)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('supply/purchase-requests/index', [
            'purchaseRequests' => $purchaseRequests,
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

        return Inertia::render('supply/purchase-requests/show', [
            'purchaseRequest' => $purchaseRequest,
            'statusLabel' => $purchaseRequest->statusLabel(),
            'canManageLots' => in_array($purchaseRequest->status, ['submitted', 'supply_office_review'], true),
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

        abort_unless(in_array($purchaseRequest->status, ['submitted', 'supply_office_review'], true), 403);
    }
}
