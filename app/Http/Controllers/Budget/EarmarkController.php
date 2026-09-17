<?php

namespace App\Http\Controllers\Budget;

use App\Concerns\FlashesToasts;
use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Notifications\PurchaseRequestStatusUpdated;
use App\Services\EarmarkExportService;
use App\Services\PurchaseRequestActivityLogger;
use App\Services\WorkflowRouter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EarmarkController extends Controller
{
    use FlashesToasts;

    public function index(): Response
    {
        $purchaseRequests = PurchaseRequest::query()
            ->with(['department:id,name,code', 'requester:id,name'])
            ->where('status', 'budget_office_review')
            ->where('is_archived', false)
            ->latest()
            ->paginate(20);

        return Inertia::render('budget/purchase-requests/index', [
            'purchaseRequests' => $purchaseRequests,
        ]);
    }

    public function edit(PurchaseRequest $purchaseRequest): Response
    {
        abort_unless(in_array($purchaseRequest->status, ['budget_office_review', 'ceo_approval'], true) || filled($purchaseRequest->earmark_id), 403);

        $purchaseRequest->load(['department', 'requester', 'items']);

        return Inertia::render('budget/purchase-requests/edit', [
            'purchaseRequest' => $purchaseRequest,
            'statusLabel' => $purchaseRequest->statusLabel(),
        ]);
    }

    public function update(
        Request $request,
        PurchaseRequest $purchaseRequest,
        WorkflowRouter $workflowRouter,
        PurchaseRequestActivityLogger $activityLogger,
    ): RedirectResponse {
        abort_unless($purchaseRequest->status === 'budget_office_review', 403);

        $validated = $request->validate([
            'legal_basis' => ['required', 'string'],
            'earmark_programs_activities' => ['required', 'string'],
            'earmark_responsibility_center' => ['required', 'string'],
            'earmark_date_to' => ['required', 'date'],
            'earmark_object_expenditures' => ['nullable', 'array'],
            'earmark_object_expenditures.*.description' => ['nullable', 'string'],
            'earmark_object_expenditures.*.amount' => ['nullable', 'numeric'],
            'current_step_notes' => ['nullable', 'string'],
        ]);

        $previousStatus = $purchaseRequest->status;

        $purchaseRequest->fill([
            ...$validated,
            'earmark_id' => $purchaseRequest->earmark_id ?: PurchaseRequest::generateNextEarmarkId(),
            'status' => 'ceo_approval',
        ])->save();

        $workflowRouter->createPendingForRole($purchaseRequest, 'ceo_initial_approval', 'Executive Officer');

        $activityLogger->log(
            $purchaseRequest,
            'approved',
            'Earmark approved by Budget Office.',
            oldValue: ['status' => $previousStatus],
            newValue: ['status' => $purchaseRequest->status, 'earmark_id' => $purchaseRequest->earmark_id],
        );

        $purchaseRequest->requester?->notify(new PurchaseRequestStatusUpdated($purchaseRequest, $previousStatus));

        $this->toast("Earmark {$purchaseRequest->earmark_id} approved.");

        return redirect()->route('budget.purchase-requests.index');
    }

    public function reject(
        Request $request,
        PurchaseRequest $purchaseRequest,
        PurchaseRequestActivityLogger $activityLogger,
    ): RedirectResponse {
        abort_unless($purchaseRequest->status === 'budget_office_review', 403);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string'],
        ]);

        $previousStatus = $purchaseRequest->status;

        $purchaseRequest->fill([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
        ])->save();

        $activityLogger->log(
            $purchaseRequest,
            'rejected',
            'Deferred by Budget Office.',
            oldValue: ['status' => $previousStatus],
            newValue: ['status' => 'rejected'],
        );

        $purchaseRequest->requester?->notify(new PurchaseRequestStatusUpdated($purchaseRequest, $previousStatus));

        $this->toast('Purchase request deferred.');

        return redirect()->route('budget.purchase-requests.index');
    }

    public function amend(
        Request $request,
        PurchaseRequest $purchaseRequest,
        PurchaseRequestActivityLogger $activityLogger,
    ): RedirectResponse {
        abort_unless(filled($purchaseRequest->earmark_id), 403);

        $validated = $request->validate([
            'legal_basis' => ['required', 'string'],
            'earmark_programs_activities' => ['required', 'string'],
            'earmark_responsibility_center' => ['required', 'string'],
            'earmark_date_to' => ['required', 'date'],
            'earmark_object_expenditures' => ['nullable', 'array'],
            'current_step_notes' => ['nullable', 'string'],
        ]);

        $purchaseRequest->fill($validated)->save();

        $activityLogger->log(
            $purchaseRequest,
            'earmark_amended',
            'Earmark fields amended without workflow change.',
            newValue: $validated,
        );

        $this->toast('Earmark amended.');

        return back();
    }

    public function export(PurchaseRequest $purchaseRequest, EarmarkExportService $exportService): BinaryFileResponse|RedirectResponse
    {
        abort_unless(
            $purchaseRequest->status === 'budget_office_review' || filled($purchaseRequest->earmark_id),
            403,
        );

        try {
            return $exportService->download($purchaseRequest);
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['export' => 'Unable to export earmark.']);
        }
    }
}
