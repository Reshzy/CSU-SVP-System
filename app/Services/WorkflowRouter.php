<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Models\WorkflowApproval;
use App\Notifications\PurchaseRequestActionRequired;
use Illuminate\Support\Facades\Notification;

class WorkflowRouter
{
    /**
     * @return array<string, int>
     */
    public function getStepOrder(): array
    {
        return [
            'supply_office_review' => 1,
            'budget_office_earmarking' => 2,
            'ceo_initial_approval' => 3,
            'bac_evaluation' => 4,
            'bac_award_recommendation' => 5,
            'ceo_final_approval' => 6,
            'po_generation' => 7,
            'po_approval' => 8,
        ];
    }

    public function createPendingForRole(PurchaseRequest $purchaseRequest, string $stepName, string $roleName): ?WorkflowApproval
    {
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
                'status' => 'pending',
                'assigned_at' => now(),
            ],
        );

        if ($approval->wasRecentlyCreated || $approval->status === 'pending') {
            Notification::send($approver, new PurchaseRequestActionRequired($purchaseRequest, $stepName));
        }

        return $approval;
    }
}
