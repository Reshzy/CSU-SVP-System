<?php

namespace App\Enums;

/**
 * Pending-task step names stored on `workflow_approvals`. Controllers only
 * invoke the wired steps; the rest stay in the order map for parity.
 */
enum WorkflowStepName: string
{
    case SupplyOfficeReview = 'supply_office_review';
    case BudgetOfficeEarmarking = 'budget_office_earmarking';
    case CeoInitialApproval = 'ceo_initial_approval';
    case BacEvaluation = 'bac_evaluation';
    case BacAwardRecommendation = 'bac_award_recommendation';
    case CeoFinalApproval = 'ceo_final_approval';
    case PoGeneration = 'po_generation';
    case PoApproval = 'po_approval';

    public function label(): string
    {
        return match ($this) {
            self::SupplyOfficeReview => 'Supply Office Review',
            self::BudgetOfficeEarmarking => 'Budget Office Earmarking',
            self::CeoInitialApproval => 'CEO Initial Approval',
            self::BacEvaluation => 'BAC Evaluation',
            self::BacAwardRecommendation => 'BAC Award Recommendation',
            self::CeoFinalApproval => 'CEO Final Approval',
            self::PoGeneration => 'PO Generation',
            self::PoApproval => 'PO Approval',
        };
    }
}
