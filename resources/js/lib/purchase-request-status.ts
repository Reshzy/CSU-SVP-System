import type { PurchaseRequestStatus } from '@/types';

const labels: Record<PurchaseRequestStatus, string> = {
    draft: 'Draft',
    submitted: 'Submitted',
    supply_office_review: 'Supply Office Review',
    budget_office_review: 'Budget Office Review',
    ceo_approval: 'CEO Approval',
    bac_evaluation: 'BAC Evaluation',
    bac_approved: 'BAC Approved',
    partial_po_generation: 'Partial PO Generation',
    po_generation: 'PO Generation',
    po_approved: 'PO Approved',
    supplier_processing: 'Supplier Processing',
    delivered: 'Delivered',
    completed: 'Completed',
    cancelled: 'Cancelled',
    rejected: 'Deferred',
    returned_by_supply: 'Returned by Supply Office',
};

export function purchaseRequestStatusLabel(
    status: PurchaseRequestStatus,
): string {
    return labels[status];
}
