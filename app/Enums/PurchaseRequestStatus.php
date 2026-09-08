<?php

namespace App\Enums;

/**
 * Lifecycle of `purchase_requests.status`. New requests start at
 * `SupplyOfficeReview`; `Draft` and `Submitted` are legacy values the
 * observer still understands.
 */
enum PurchaseRequestStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case SupplyOfficeReview = 'supply_office_review';
    case BudgetOfficeReview = 'budget_office_review';
    case CeoApproval = 'ceo_approval';
    case BacEvaluation = 'bac_evaluation';
    case BacApproved = 'bac_approved';
    case PartialPoGeneration = 'partial_po_generation';
    case PoGeneration = 'po_generation';
    case PoApproved = 'po_approved';
    case SupplierProcessing = 'supplier_processing';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case ReturnedBySupply = 'returned_by_supply';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::SupplyOfficeReview => 'Supply Office Review',
            self::BudgetOfficeReview => 'Budget Office Review',
            self::CeoApproval => 'CEO Approval',
            self::BacEvaluation => 'BAC Evaluation',
            self::BacApproved => 'BAC Approved',
            self::PartialPoGeneration => 'Partial PO Generation',
            self::PoGeneration => 'PO Generation',
            self::PoApproved => 'PO Approved',
            self::SupplierProcessing => 'Supplier Processing',
            self::Delivered => 'Delivered',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Rejected => 'Deferred',
            self::ReturnedBySupply => 'Returned by Supply Office',
        };
    }
}
