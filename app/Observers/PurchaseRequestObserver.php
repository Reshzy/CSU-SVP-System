<?php

namespace App\Observers;

use App\Models\DepartmentBudget;
use App\Models\PurchaseRequest;
use App\Services\PpmpQuarterlyTracker;
use App\Services\PurchaseRequestActivityLogger;
use Illuminate\Support\Facades\Log;

class PurchaseRequestObserver
{
    public function __construct(
        private PpmpQuarterlyTracker $quarterlyTracker,
        private PurchaseRequestActivityLogger $activityLogger,
    ) {}

    public function creating(PurchaseRequest $purchaseRequest): void
    {
        if ($purchaseRequest->pr_quarter === null) {
            $purchaseRequest->pr_quarter = $this->quarterlyTracker->currentQuarter();
        }

        if (blank($purchaseRequest->pr_number)) {
            $purchaseRequest->pr_number = PurchaseRequest::generateNextPrNumber();
        }

        if ($purchaseRequest->status_updated_at === null) {
            $purchaseRequest->status_updated_at = now();
        }
    }

    public function created(PurchaseRequest $purchaseRequest): void
    {
        $this->activityLogger->log(
            $purchaseRequest,
            'created',
            "Purchase request {$purchaseRequest->pr_number} created.",
            newValue: ['status' => $purchaseRequest->status],
        );

        if ($purchaseRequest->status !== 'draft') {
            $this->reserveBudget($purchaseRequest);

            if (in_array($purchaseRequest->status, ['supply_office_review', 'submitted'], true)) {
                if ($purchaseRequest->submitted_at === null) {
                    $purchaseRequest->forceFill(['submitted_at' => now()])->saveQuietly();
                }

                $this->activityLogger->log(
                    $purchaseRequest,
                    'submitted',
                    "Purchase request {$purchaseRequest->pr_number} submitted.",
                    newValue: ['status' => $purchaseRequest->status],
                );
            }
        }
    }

    public function updating(PurchaseRequest $purchaseRequest): void
    {
        if (! $purchaseRequest->isDirty('status')) {
            return;
        }

        $oldStatus = $purchaseRequest->getOriginal('status');
        $newStatus = $purchaseRequest->status;

        $purchaseRequest->status_updated_at = now();

        $this->activityLogger->log(
            $purchaseRequest,
            'status_changed',
            "Status changed from {$oldStatus} to {$newStatus}.",
            oldValue: ['status' => $oldStatus],
            newValue: ['status' => $newStatus],
        );

        if ($oldStatus === 'draft' && in_array($newStatus, ['submitted', 'supply_office_review'], true)) {
            $this->reserveBudget($purchaseRequest);
        }

        if ($newStatus === 'completed') {
            $this->utilizeBudget($purchaseRequest);
        }

        if (
            in_array($newStatus, ['cancelled', 'rejected', 'returned_by_supply'], true)
            && $oldStatus !== 'draft'
        ) {
            $this->releaseBudget($purchaseRequest);
        }
    }

    private function reserveBudget(PurchaseRequest $purchaseRequest): void
    {
        $amount = $this->amountFor($purchaseRequest);

        if ($amount <= 0) {
            return;
        }

        $budget = DepartmentBudget::getOrCreateForDepartment(
            $purchaseRequest->department_id,
            (int) ($purchaseRequest->created_at?->year ?? now()->year),
        );

        if (! $budget->reserveBudget($amount)) {
            Log::error('Failed to reserve department budget for purchase request.', [
                'purchase_request_id' => $purchaseRequest->id,
                'pr_number' => $purchaseRequest->pr_number,
                'amount' => $amount,
                'available' => $budget->availableBudget(),
            ]);
        }
    }

    private function utilizeBudget(PurchaseRequest $purchaseRequest): void
    {
        $amount = $this->amountFor($purchaseRequest);

        if ($amount <= 0) {
            return;
        }

        $budget = DepartmentBudget::getOrCreateForDepartment(
            $purchaseRequest->department_id,
            (int) ($purchaseRequest->created_at?->year ?? now()->year),
        );

        $budget->utilizeBudget($amount);
    }

    private function releaseBudget(PurchaseRequest $purchaseRequest): void
    {
        $amount = $this->amountFor($purchaseRequest);

        if ($amount <= 0) {
            return;
        }

        $budget = DepartmentBudget::getOrCreateForDepartment(
            $purchaseRequest->department_id,
            (int) ($purchaseRequest->created_at?->year ?? now()->year),
        );

        $budget->releaseReservedBudget($amount);
    }

    private function amountFor(PurchaseRequest $purchaseRequest): float
    {
        $purchaseRequest->loadMissing('items');

        $fromItems = $purchaseRequest->calculateTotalCost();

        if ($fromItems > 0) {
            return $fromItems;
        }

        return (float) $purchaseRequest->estimated_total;
    }
}
