<?php

namespace App\Services;

use App\Enums\WorkflowApprovalStatus;
use App\Enums\WorkflowStepName;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Notifications\PurchaseRequestActionRequired;

class WorkflowRouter
{
    /**
     * Declared step order. Controllers only invoke wired steps; unused
     * names stay here so later slices do not invent new keys.
     *
     * @return array<string, int>
     */
    public function getStepOrder(): array
    {
        return [
            WorkflowStepName::SupplyOfficeReview->value => 1,
            WorkflowStepName::BudgetOfficeEarmarking->value => 2,
            WorkflowStepName::CeoInitialApproval->value => 3,
            WorkflowStepName::BacEvaluation->value => 4,
            WorkflowStepName::BacAwardRecommendation->value => 5,
            WorkflowStepName::CeoFinalApproval->value => 6,
            WorkflowStepName::PoGeneration->value => 7,
            WorkflowStepName::PoApproval->value => 8,
        ];
    }

    /**
     * Assign the first user with `$roleName` a pending approval for `$stepName`.
     * Returns null (and does not mail) when that role has no users.
     */
    public function createPendingForRole(
        PurchaseRequest $purchaseRequest,
        string $stepName,
        string $roleName,
    ): ?WorkflowApproval {
        $approver = User::role($roleName)->orderBy('id')->first();

        if ($approver === null) {
            return null;
        }

        $approval = WorkflowApproval::query()->firstOrCreate(
            [
                'purchase_request_id' => $purchaseRequest->id,
                'step_name' => $stepName,
            ],
            [
                'step_order' => $this->getStepOrder()[$stepName] ?? 0,
                'approver_id' => $approver->id,
                'status' => WorkflowApprovalStatus::Pending,
                'assigned_at' => now(),
            ],
        );

        if ($approval->wasRecentlyCreated) {
            $approver->notify(
                (new PurchaseRequestActionRequired($purchaseRequest, $stepName))->afterCommit(),
            );
        }

        return $approval;
    }
}
