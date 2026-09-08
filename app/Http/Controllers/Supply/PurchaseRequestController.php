<?php

namespace App\Http\Controllers\Supply;

use App\Concerns\FlashesToasts;
use App\Enums\PurchaseRequestStatus;
use App\Enums\WorkflowStepName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Supply\StoreLotRequest;
use App\Http\Requests\Supply\UpdateLotRequest;
use App\Http\Requests\Supply\UpdatePurchaseRequestStatusRequest;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Notifications\PurchaseRequestStatusUpdated;
use App\Services\PurchaseRequestActivityLogger;
use App\Services\WorkflowRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestController extends Controller
{
    use FlashesToasts;

    public function __construct(
        private WorkflowRouter $workflowRouter,
        private PurchaseRequestActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): Response
    {
        $status = PurchaseRequestStatus::tryFrom((string) $request->query('status'))
            ?? PurchaseRequestStatus::SupplyOfficeReview;

        $requests = PurchaseRequest::query()
            ->where('is_archived', false)
            ->where('status', $status)
            ->with(['department:id,name,code', 'requester:id,name'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $statusCounts = PurchaseRequest::query()
            ->where('is_archived', false)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('supply/purchase-requests/index', [
            'requests' => $requests,
            'filters' => ['status' => $status->value],
            'statusCounts' => $statusCounts,
        ]);
    }

    public function show(PurchaseRequest $purchaseRequest): Response
    {
        Gate::authorize('view', $purchaseRequest);

        $purchaseRequest->load([
            'department:id,name,code',
            'requester:id,name',
            'items' => fn ($query) => $query->orderBy('id'),
        ]);

        $standalones = $purchaseRequest->items
            ->filter(fn (PurchaseRequestItem $item): bool => ! $item->is_lot && $item->parent_lot_id === null)
            ->values()
            ->map(fn (PurchaseRequestItem $item): array => [
                'id' => $item->id,
                'item_name' => $item->item_name,
                'item_code' => $item->item_code,
                'estimated_total_cost' => $item->estimated_total_cost,
            ]);

        return Inertia::render('supply/purchase-requests/show', [
            'purchaseRequest' => $purchaseRequest,
            'canManageLots' => $purchaseRequest->canManageLots(),
            'allowedActions' => $purchaseRequest->allowedSupplyActions(),
            'standalones' => $standalones,
        ]);
    }

    public function updateStatus(
        UpdatePurchaseRequestStatusRequest $request,
        PurchaseRequest $purchaseRequest,
    ): RedirectResponse {
        $action = $request->validated('action');

        $this->assertActionAllowed($purchaseRequest, $action);

        DB::transaction(function () use ($request, $purchaseRequest, $action): void {
            $userId = $request->user()->id;

            match ($action) {
                'start_review' => $purchaseRequest->forceFill([
                    'status' => PurchaseRequestStatus::SupplyOfficeReview,
                    'current_handler_id' => $userId,
                    'status_updated_at' => now(),
                ])->save(),
                'activate' => $this->activate($purchaseRequest, $userId),
                'return' => $purchaseRequest->forceFill([
                    'status' => PurchaseRequestStatus::ReturnedBySupply,
                    'current_handler_id' => $userId,
                    'returned_by' => $userId,
                    'return_remarks' => $request->validated('remarks'),
                    'returned_at' => now(),
                    'status_updated_at' => now(),
                ])->save(),
                'reject' => $purchaseRequest->forceFill([
                    'status' => PurchaseRequestStatus::Rejected,
                    'current_handler_id' => $userId,
                    'rejected_by' => $userId,
                    'rejection_reason' => $request->validated('rejection_reason'),
                    'rejected_at' => now(),
                    'status_updated_at' => now(),
                ])->save(),
                'cancel' => $purchaseRequest->forceFill([
                    'status' => PurchaseRequestStatus::Cancelled,
                    'current_handler_id' => $userId,
                    'status_updated_at' => now(),
                ])->save(),
                default => null,
            };
        });

        $purchaseRequest->refresh();
        $purchaseRequest->loadMissing('requester');
        $purchaseRequest->requester?->notify(
            (new PurchaseRequestStatusUpdated($purchaseRequest))->afterCommit(),
        );

        $this->toast($this->statusToast($purchaseRequest, $action));

        return redirect()->route('supply.purchase-requests.show', $purchaseRequest);
    }

    public function storeLot(StoreLotRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        Gate::authorize('update', $purchaseRequest);
        abort_unless($purchaseRequest->canManageLots(), 403);

        $itemIds = array_map('intval', $request->validated('item_ids'));

        DB::transaction(function () use ($request, $purchaseRequest, $itemIds): void {
            $children = PurchaseRequestItem::query()
                ->where('purchase_request_id', $purchaseRequest->id)
                ->whereIn('id', $itemIds)
                ->get();

            $total = (float) $children->sum(
                fn (PurchaseRequestItem $item): float => (float) $item->estimated_total_cost,
            );
            $lotName = $request->validated('lot_name');

            $header = PurchaseRequestItem::query()->create([
                'purchase_request_id' => $purchaseRequest->id,
                'is_lot' => true,
                'lot_name' => $lotName,
                'item_name' => $lotName,
                'unit_of_measure' => 'lot',
                'quantity_requested' => 1,
                'estimated_unit_cost' => $total,
                'estimated_total_cost' => $total,
                'item_status' => 'pending',
                'procurement_status' => 'pending',
            ]);

            PurchaseRequestItem::query()
                ->whereIn('id', $itemIds)
                ->update(['parent_lot_id' => $header->id]);

            $purchaseRequest->refreshEstimatedTotal();
            $this->activityLogger->logUpdated(
                $purchaseRequest,
                "Lot '{$lotName}' created from {$children->count()} items",
            );
        });

        $this->toast('Lot created.');

        return back();
    }

    public function updateLot(
        UpdateLotRequest $request,
        PurchaseRequest $purchaseRequest,
        int $lot,
    ): RedirectResponse {
        Gate::authorize('update', $purchaseRequest);
        abort_unless($purchaseRequest->canManageLots(), 403);

        $header = $this->lotHeader($purchaseRequest, $lot);
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $purchaseRequest, $header): void {
            if (isset($validated['lot_name'])) {
                $header->forceFill([
                    'lot_name' => $validated['lot_name'],
                    'item_name' => $validated['lot_name'],
                ])->save();
            }

            if (isset($validated['item_ids'])) {
                $itemIds = array_map('intval', $validated['item_ids']);

                $header->lotChildren()
                    ->whereNotIn('id', $itemIds)
                    ->update(['parent_lot_id' => null]);

                PurchaseRequestItem::query()
                    ->where('purchase_request_id', $purchaseRequest->id)
                    ->whereIn('id', $itemIds)
                    ->update(['parent_lot_id' => $header->id]);

                $header->unsetRelation('lotChildren');
                $total = (float) $header->lotChildren()->get()->sum(
                    fn (PurchaseRequestItem $item): float => (float) $item->estimated_total_cost,
                );

                $header->forceFill([
                    'estimated_unit_cost' => $total,
                    'estimated_total_cost' => $total,
                ])->save();
            }

            $purchaseRequest->refreshEstimatedTotal();
            $this->activityLogger->logUpdated(
                $purchaseRequest,
                "Lot '{$header->lot_name}' updated",
            );
        });

        $this->toast('Lot updated.');

        return back();
    }

    public function destroyLot(PurchaseRequest $purchaseRequest, int $lot): RedirectResponse
    {
        Gate::authorize('update', $purchaseRequest);
        abort_unless($purchaseRequest->canManageLots(), 403);

        $header = $this->lotHeader($purchaseRequest, $lot);
        $lotName = $header->lot_name ?? $header->item_name;

        DB::transaction(function () use ($purchaseRequest, $header, $lotName): void {
            $header->lotChildren()->update(['parent_lot_id' => null]);
            $header->delete();
            $purchaseRequest->refreshEstimatedTotal();
            $this->activityLogger->logUpdated(
                $purchaseRequest,
                "Lot '{$lotName}' removed; items restored as standalones",
            );
        });

        $this->toast('Lot removed. Items are standalone again.');

        return back();
    }

    private function activate(PurchaseRequest $purchaseRequest, int $userId): void
    {
        $purchaseRequest->forceFill([
            'status' => PurchaseRequestStatus::BudgetOfficeReview,
            'current_handler_id' => $userId,
            'status_updated_at' => now(),
        ])->save();

        $this->workflowRouter->createPendingForRole(
            $purchaseRequest,
            WorkflowStepName::BudgetOfficeEarmarking->value,
            'Budget Office',
        );
    }

    private function assertActionAllowed(PurchaseRequest $purchaseRequest, string $action): void
    {
        if (! in_array($action, $purchaseRequest->allowedSupplyActions(), true)) {
            throw ValidationException::withMessages([
                'action' => 'That action is not available for the current status.',
            ]);
        }
    }

    private function lotHeader(PurchaseRequest $purchaseRequest, int $lot): PurchaseRequestItem
    {
        return $purchaseRequest->items()
            ->where('is_lot', true)
            ->whereKey($lot)
            ->firstOrFail();
    }

    private function statusToast(PurchaseRequest $purchaseRequest, string $action): string
    {
        return match ($action) {
            'start_review' => "Started review of {$purchaseRequest->pr_number}.",
            'activate' => "Activated {$purchaseRequest->pr_number} for Budget Office review.",
            'return' => "Returned {$purchaseRequest->pr_number} to the department.",
            'reject' => "Deferred {$purchaseRequest->pr_number}.",
            'cancel' => "Cancelled {$purchaseRequest->pr_number}.",
            default => "Updated {$purchaseRequest->pr_number}.",
        };
    }
}
