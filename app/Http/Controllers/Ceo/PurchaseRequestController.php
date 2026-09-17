<?php

namespace App\Http\Controllers\Ceo;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Notifications\PurchaseRequestStatusUpdated;
use App\Services\BacResolutionService;
use App\Services\PurchaseRequestActivityLogger;
use App\Services\WorkflowRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequestController extends Controller
{
    use FlashesToasts;

    public function index(): Response
    {
        $purchaseRequests = PurchaseRequest::query()
            ->with(['department:id,name,code', 'requester:id,name'])
            ->where('status', 'ceo_approval')
            ->where('is_archived', false)
            ->latest()
            ->paginate(20);

        return Inertia::render('ceo/purchase-requests/index', [
            'purchaseRequests' => $purchaseRequests,
        ]);
    }

    public function show(PurchaseRequest $purchaseRequest): Response
    {
        abort_unless($purchaseRequest->status === 'ceo_approval' || filled($purchaseRequest->resolution_number), 403);

        $purchaseRequest->load(['department', 'requester', 'items', 'documents']);

        return Inertia::render('ceo/purchase-requests/show', [
            'purchaseRequest' => $purchaseRequest,
            'statusLabel' => $purchaseRequest->statusLabel(),
        ]);
    }

    public function update(
        Request $request,
        PurchaseRequest $purchaseRequest,
        BacResolutionService $resolutionService,
        WorkflowRouter $workflowRouter,
        PurchaseRequestActivityLogger $activityLogger,
    ): RedirectResponse {
        abort_unless($purchaseRequest->status === 'ceo_approval', 403);

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
            'rejection_reason' => ['nullable', 'string'],
        ]);

        $previousStatus = $purchaseRequest->status;

        if ($validated['decision'] === 'reject') {
            $purchaseRequest->fill([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'] ?? 'Deferred by Executive Officer',
                'rejected_by' => $request->user()->id,
                'rejected_at' => now(),
            ])->save();

            $activityLogger->log(
                $purchaseRequest,
                'rejected',
                'Deferred by Executive Officer.',
                oldValue: ['status' => $previousStatus],
                newValue: ['status' => 'rejected'],
            );
        } else {
            $purchaseRequest->fill([
                'status' => 'bac_evaluation',
                'procurement_method' => 'small_value_procurement',
                'procurement_method_set_at' => now(),
                'procurement_method_set_by' => $request->user()->id,
                'resolution_number' => $purchaseRequest->resolution_number ?: PurchaseRequest::generateNextResolutionNumber(),
                'approved_at' => now(),
            ])->save();

            $resolutionService->generateResolution($purchaseRequest);

            $workflowRouter->createPendingForRole($purchaseRequest, 'bac_evaluation', 'BAC Secretariat');

            $activityLogger->log(
                $purchaseRequest,
                'approved',
                'Approved by Executive Officer for BAC evaluation.',
                oldValue: ['status' => $previousStatus],
                newValue: [
                    'status' => $purchaseRequest->status,
                    'procurement_method' => $purchaseRequest->procurement_method,
                    'resolution_number' => $purchaseRequest->resolution_number,
                ],
            );
        }

        $purchaseRequest->requester?->notify(new PurchaseRequestStatusUpdated($purchaseRequest, $previousStatus));

        $this->toast(
            $validated['decision'] === 'approve'
                ? "Purchase request {$purchaseRequest->pr_number} sent to BAC."
                : 'Purchase request deferred.',
        );

        return redirect()->route('ceo.purchase-requests.index');
    }
}
