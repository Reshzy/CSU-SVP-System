<?php

namespace App\Http\Controllers\Budget;

use App\Concerns\FlashesToasts;
use App\Enums\PurchaseRequestStatus;
use App\Enums\WorkflowStepName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\AmendEarmarkRequest;
use App\Http\Requests\Budget\ApproveEarmarkRequest;
use App\Http\Requests\Budget\RejectEarmarkRequest;
use App\Models\PurchaseRequest;
use App\Notifications\PurchaseRequestStatusUpdated;
use App\Services\EarmarkExportService;
use App\Services\PurchaseRequestActivityLogger;
use App\Services\WorkflowRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseRequestController extends Controller
{
    use FlashesToasts;

    public function __construct(
        private WorkflowRouter $workflowRouter,
        private PurchaseRequestActivityLogger $activityLogger,
        private EarmarkExportService $earmarkExportService,
    ) {}

    public function index(Request $request): Response
    {
        $status = PurchaseRequestStatus::tryFrom((string) $request->query('status'))
            ?? PurchaseRequestStatus::BudgetOfficeReview;

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

        return Inertia::render('budget/purchase-requests/index', [
            'requests' => $requests,
            'filters' => ['status' => $status->value],
            'statusCounts' => $statusCounts,
        ]);
    }

    public function edit(PurchaseRequest $purchaseRequest): Response
    {
        Gate::authorize('view', $purchaseRequest);

        $purchaseRequest->load([
            'department:id,name,code',
            'requester:id,name',
            'items' => fn ($query) => $query->orderBy('id'),
        ]);

        return Inertia::render('budget/purchase-requests/edit', [
            'purchaseRequest' => $purchaseRequest,
            'allowedActions' => $purchaseRequest->allowedBudgetActions(),
            'fundClusters' => $this->fundClusters(),
        ]);
    }

    public function update(ApproveEarmarkRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->assertActionAllowed($purchaseRequest, 'approve');

        DB::transaction(function () use ($request, $purchaseRequest): void {
            $purchaseRequest->fillEarmarkFields($this->earmarkPayload($request));
            $purchaseRequest->ensureEarmarkId();
            $purchaseRequest->forceFill([
                'status' => PurchaseRequestStatus::CeoApproval,
                'current_handler_id' => $request->user()->id,
                'status_updated_at' => now(),
            ])->save();

            $this->activityLogger->logApproved(
                $purchaseRequest,
                "Earmark {$purchaseRequest->earmark_id} approved",
                $request->user()->id,
            );

            $this->workflowRouter->createPendingForRole(
                $purchaseRequest,
                WorkflowStepName::CeoInitialApproval->value,
                'Executive Officer',
            );
        });

        $this->notifyRequester($purchaseRequest);
        $this->toast("Earmarked {$purchaseRequest->pr_number} for CEO approval.");

        return redirect()->route('budget.purchase-requests.edit', $purchaseRequest);
    }

    public function reject(RejectEarmarkRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->assertActionAllowed($purchaseRequest, 'reject');

        DB::transaction(function () use ($request, $purchaseRequest): void {
            $purchaseRequest->forceFill([
                'status' => PurchaseRequestStatus::Rejected,
                'current_handler_id' => $request->user()->id,
                'rejected_by' => $request->user()->id,
                'rejection_reason' => $request->validated('rejection_reason'),
                'rejected_at' => now(),
                'status_updated_at' => now(),
            ])->save();
        });

        $this->notifyRequester($purchaseRequest);
        $this->toast("Deferred {$purchaseRequest->pr_number}.");

        return redirect()->route('budget.purchase-requests.edit', $purchaseRequest);
    }

    public function amend(PurchaseRequest $purchaseRequest): Response
    {
        Gate::authorize('amendEarmark', $purchaseRequest);

        $purchaseRequest->load([
            'department:id,name,code',
            'requester:id,name',
            'items' => fn ($query) => $query->orderBy('id'),
        ]);

        return Inertia::render('budget/purchase-requests/amend', [
            'purchaseRequest' => $purchaseRequest,
            'fundClusters' => $this->fundClusters(),
        ]);
    }

    public function amendEarmark(AmendEarmarkRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->assertActionAllowed($purchaseRequest, 'amend');

        $statusBefore = $purchaseRequest->status;

        DB::transaction(function () use ($request, $purchaseRequest): void {
            $purchaseRequest->fillEarmarkFields($this->earmarkPayload($request));
            $purchaseRequest->save();
            $this->activityLogger->logEarmarkAmended($purchaseRequest, $request->user()->id);
        });

        $purchaseRequest->refresh();

        abort_unless($purchaseRequest->status === $statusBefore, 500);

        $this->toast("Amended earmark {$purchaseRequest->earmark_id}.");

        return redirect()->route('budget.purchase-requests.amend', $purchaseRequest);
    }

    public function exportEarmark(PurchaseRequest $purchaseRequest): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('exportEarmark', $purchaseRequest);
        $this->assertActionAllowed($purchaseRequest, 'export');

        $purchaseRequest->loadMissing('requester:id,name');

        return $this->earmarkExportService->download($purchaseRequest);
    }

    /**
     * @return array{
     *     legal_basis: string,
     *     earmark_programs_activities: string,
     *     earmark_responsibility_center: string,
     *     earmark_date_to: string,
     *     earmark_object_expenditures: list<array{code?: string|null, description: string, amount: mixed}>,
     *     fund_cluster_code: string,
     *     fund_details?: string|null,
     *     budget_code?: string|null,
     *     current_step_notes?: string|null
     * }
     */
    private function earmarkPayload(ApproveEarmarkRequest|AmendEarmarkRequest $request): array
    {
        /** @var array{
         *     legal_basis: string,
         *     earmark_programs_activities: string,
         *     earmark_responsibility_center: string,
         *     earmark_date_to: string,
         *     earmark_object_expenditures: list<array{code?: string|null, description: string, amount: mixed}>,
         *     fund_cluster_code: string,
         *     fund_details?: string|null,
         *     budget_code?: string|null,
         *     current_step_notes?: string|null
         * } $payload
         */
        $payload = $request->safe()->only([
            'legal_basis',
            'earmark_programs_activities',
            'earmark_responsibility_center',
            'earmark_date_to',
            'earmark_object_expenditures',
            'fund_cluster_code',
            'fund_details',
            'budget_code',
            'current_step_notes',
        ]);

        return $payload;
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    private function fundClusters(): array
    {
        return collect(PurchaseRequest::FUND_CLUSTERS)
            ->map(fn (string $label, string $code): array => ['code' => $code, 'label' => $label])
            ->values()
            ->all();
    }

    private function assertActionAllowed(PurchaseRequest $purchaseRequest, string $action): void
    {
        if (! in_array($action, $purchaseRequest->allowedBudgetActions(), true)) {
            throw ValidationException::withMessages([
                'action' => 'That action is not available for the current status.',
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
