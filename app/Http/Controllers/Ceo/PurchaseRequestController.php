<?php

namespace App\Http\Controllers\Ceo;

use App\Concerns\FlashesToasts;
use App\Enums\PurchaseRequestStatus;
use App\Enums\WorkflowStepName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ceo\DecidePurchaseRequestRequest;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Notifications\PurchaseRequestStatusUpdated;
use App\Services\BacResolutionService;
use App\Services\PurchaseRequestActivityLogger;
use App\Services\WorkflowRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PurchaseRequestController extends Controller
{
    use FlashesToasts;

    public function __construct(
        private WorkflowRouter $workflowRouter,
        private PurchaseRequestActivityLogger $activityLogger,
        private BacResolutionService $bacResolutionService,
    ) {}

    public function index(Request $request): Response
    {
        $status = PurchaseRequestStatus::tryFrom((string) $request->query('status'))
            ?? PurchaseRequestStatus::CeoApproval;

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

        return Inertia::render('ceo/purchase-requests/index', [
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

        return Inertia::render('ceo/purchase-requests/show', [
            'purchaseRequest' => $purchaseRequest,
            'allowedActions' => $purchaseRequest->allowedCeoActions(),
        ]);
    }

    public function update(DecidePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $decision = $request->validated('decision');
        $this->assertActionAllowed($purchaseRequest, $decision);

        DB::transaction(function () use ($request, $purchaseRequest, $decision): void {
            $userId = $request->user()->id;

            if ($decision === 'approve') {
                $this->approve($purchaseRequest, $userId);
            } else {
                $purchaseRequest->forceFill([
                    'status' => PurchaseRequestStatus::Rejected,
                    'current_handler_id' => $userId,
                    'rejected_by' => $userId,
                    'rejection_reason' => $request->validated('rejection_reason'),
                    'rejected_at' => now(),
                    'status_updated_at' => now(),
                ])->save();
            }
        });

        if ($decision === 'approve') {
            try {
                $actor = $request->user();

                if ($actor instanceof User) {
                    $this->bacResolutionService->generateResolution($purchaseRequest, $actor);
                }
            } catch (Throwable $e) {
                Log::error('Failed to generate BAC resolution for PR: '.($purchaseRequest->pr_number ?? $purchaseRequest->id), [
                    'error' => $e->getMessage(),
                ]);
            }

            $this->workflowRouter->createPendingForRole(
                $purchaseRequest,
                WorkflowStepName::BacEvaluation->value,
                'BAC Secretariat',
            );
        }

        $this->notifyRequester($purchaseRequest);

        $this->toast($decision === 'approve'
            ? "Approved {$purchaseRequest->pr_number} for BAC evaluation."
            : "Deferred {$purchaseRequest->pr_number}.");

        return redirect()->route('ceo.purchase-requests.show', $purchaseRequest);
    }

    private function approve(PurchaseRequest $purchaseRequest, int $userId): void
    {
        $purchaseRequest->forceFill([
            'status' => PurchaseRequestStatus::BacEvaluation,
            'procurement_method' => 'small_value_procurement',
            'procurement_method_set_at' => now(),
            'procurement_method_set_by' => $userId,
            'resolution_number' => $purchaseRequest->resolution_number ?: PurchaseRequest::generateNextResolutionNumber(),
            'current_handler_id' => $userId,
            'approved_at' => now(),
            'status_updated_at' => now(),
        ])->save();

        $this->activityLogger->logApproved(
            $purchaseRequest,
            'Approved for BAC evaluation with Small Value Procurement',
            $userId,
        );
    }

    private function assertActionAllowed(PurchaseRequest $purchaseRequest, string $action): void
    {
        if (! in_array($action, $purchaseRequest->allowedCeoActions(), true)) {
            throw ValidationException::withMessages([
                'decision' => 'That action is not available for the current status.',
            ]);
        }
    }

    private function notifyRequester(PurchaseRequest $purchaseRequest): void
    {
        $purchaseRequest->refresh();
        $purchaseRequest->loadMissing('requester');
        $purchaseRequest->requester?->notify(
            (new PurchaseRequestStatusUpdated($purchaseRequest))->afterCommit(),
        );
    }
}
