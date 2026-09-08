<?php

namespace App\Observers;

use App\Enums\PurchaseRequestStatus;
use App\Models\PurchaseRequest;
use App\Services\PpmpQuarterlyTracker;
use App\Services\PurchaseRequestActivityLogger;
use Illuminate\Support\Facades\Log;
use Throwable;

class PurchaseRequestObserver
{
    public function __construct(private PurchaseRequestActivityLogger $activityLogger) {}

    /**
     * Stamp the current PPMP quarter before the row is inserted.
     */
    public function creating(PurchaseRequest $purchaseRequest): void
    {
        if ($purchaseRequest->pr_quarter === null) {
            $purchaseRequest->pr_quarter = app(PpmpQuarterlyTracker::class)->currentQuarter();
        }
    }

    /**
     * Log creation. Non-draft rows reserve the department envelope from the
     * stored estimated total (items are not written yet) and log submission.
     */
    public function created(PurchaseRequest $purchaseRequest): void
    {
        try {
            $this->activityLogger->logCreation($purchaseRequest, $purchaseRequest->requester_id);
        } catch (Throwable $e) {
            Log::error('Failed to log PR creation activity: '.($purchaseRequest->pr_number ?? $purchaseRequest->id), [
                'error' => $e->getMessage(),
            ]);
        }

        if ($purchaseRequest->status === PurchaseRequestStatus::Draft) {
            return;
        }

        try {
            $purchaseRequest->reserveDepartmentBudget();

            if (in_array($purchaseRequest->status, [
                PurchaseRequestStatus::SupplyOfficeReview,
                PurchaseRequestStatus::Submitted,
            ], true)) {
                $this->activityLogger->logSubmission($purchaseRequest, $purchaseRequest->requester_id);
            }
        } catch (Throwable $e) {
            Log::error('Failed to reserve budget for PR: '.($purchaseRequest->pr_number ?? $purchaseRequest->id), [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(PurchaseRequest $purchaseRequest): void
    {
        if ($purchaseRequest->wasChanged('status')) {
            $oldStatus = $purchaseRequest->getOriginal('status');
            $old = $oldStatus instanceof PurchaseRequestStatus
                ? $oldStatus
                : PurchaseRequestStatus::from((string) $oldStatus);

            try {
                $this->activityLogger->logStatusChange($purchaseRequest, $old, $purchaseRequest->status);

                if ($purchaseRequest->status === PurchaseRequestStatus::ReturnedBySupply && $purchaseRequest->return_remarks) {
                    $this->activityLogger->logReturn(
                        $purchaseRequest,
                        $purchaseRequest->return_remarks,
                        $purchaseRequest->returned_by,
                    );
                }

                if ($purchaseRequest->status === PurchaseRequestStatus::Rejected && $purchaseRequest->rejection_reason) {
                    $this->activityLogger->logRejection(
                        $purchaseRequest,
                        $purchaseRequest->rejection_reason,
                        $purchaseRequest->rejected_by,
                    );
                }
            } catch (Throwable $e) {
                Log::error('Failed to log PR status change activity: '.($purchaseRequest->pr_number ?? $purchaseRequest->id), [
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                if ($old === PurchaseRequestStatus::Draft && $purchaseRequest->status === PurchaseRequestStatus::Submitted) {
                    $purchaseRequest->reserveDepartmentBudget();
                }

                if ($purchaseRequest->status === PurchaseRequestStatus::Completed) {
                    $purchaseRequest->utilizeDepartmentBudget();
                }

                if (in_array($purchaseRequest->status, [
                    PurchaseRequestStatus::Cancelled,
                    PurchaseRequestStatus::Rejected,
                    PurchaseRequestStatus::ReturnedBySupply,
                ], true) && $old !== PurchaseRequestStatus::Draft) {
                    $purchaseRequest->releaseReservedBudget();
                }
            } catch (Throwable $e) {
                Log::error('Failed to update budget for PR: '.($purchaseRequest->pr_number ?? $purchaseRequest->id), [
                    'old_status' => $old->value,
                    'new_status' => $purchaseRequest->status->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($purchaseRequest->wasChanged('current_handler_id') && $purchaseRequest->current_handler_id) {
            try {
                $this->activityLogger->logAssignment($purchaseRequest, $purchaseRequest->current_handler_id);
            } catch (Throwable $e) {
                Log::error('Failed to log PR assignment activity: '.($purchaseRequest->pr_number ?? $purchaseRequest->id), [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($purchaseRequest->wasChanged('current_step_notes') && $purchaseRequest->current_step_notes) {
            try {
                $this->activityLogger->logNotesAdded($purchaseRequest, $purchaseRequest->current_step_notes);
            } catch (Throwable $e) {
                Log::error('Failed to log PR notes activity: '.($purchaseRequest->pr_number ?? $purchaseRequest->id), [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function deleted(PurchaseRequest $purchaseRequest): void
    {
        if (in_array($purchaseRequest->status, [
            PurchaseRequestStatus::Draft,
            PurchaseRequestStatus::Completed,
            PurchaseRequestStatus::Cancelled,
            PurchaseRequestStatus::Rejected,
        ], true)) {
            return;
        }

        try {
            $purchaseRequest->releaseReservedBudget();
        } catch (Throwable $e) {
            Log::error('Failed to release budget for deleted PR: '.($purchaseRequest->pr_number ?? $purchaseRequest->id), [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
