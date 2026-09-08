import type { Department } from '@/types/auth';

export type PurchaseRequestStatus =
    | 'draft'
    | 'submitted'
    | 'supply_office_review'
    | 'budget_office_review'
    | 'ceo_approval'
    | 'bac_evaluation'
    | 'bac_approved'
    | 'partial_po_generation'
    | 'po_generation'
    | 'po_approved'
    | 'supplier_processing'
    | 'delivered'
    | 'completed'
    | 'cancelled'
    | 'rejected'
    | 'returned_by_supply';

export type PurchaseRequest = {
    id: number;
    pr_number: string | null;
    purpose: string;
    justification: string | null;
    estimated_total: string;
    status: PurchaseRequestStatus;
    pr_quarter: number | null;
    has_ppmp: boolean;
    submitted_at: string | null;
    created_at: string;
    department?: Pick<Department, 'id' | 'name' | 'code'> | null;
    requester?: { id: number; name: string } | null;
    items?: PurchaseRequestItem[];
};

export type PurchaseRequestItem = {
    id: number;
    ppmp_item_id: number | null;
    is_lot: boolean;
    lot_name: string | null;
    parent_lot_id: number | null;
    item_code: string | null;
    item_name: string;
    detailed_specifications: string | null;
    unit_of_measure: string;
    quantity_requested: number;
    estimated_unit_cost: string;
    estimated_total_cost: string;
    item_category: string | null;
    ppmp_quarter: number | null;
};

export type PpmpLineForPr = {
    id: number;
    app_item_id: number;
    item_code: string;
    item_name: string;
    unit_of_measure: string;
    category: string;
    estimated_unit_cost: string;
    current_quarter_qty: number;
    remaining_qty: number;
    has_current_quarter_qty: boolean;
};

export type DepartmentBudgetSummary = {
    allocated_budget: number;
    utilized_budget: number;
    reserved_budget: number;
    available_budget: number;
};

export type BudgetCheckResponse = {
    success: boolean;
    data?: {
        available_budget: number;
        allocated_budget: number;
        utilized_budget: number;
        reserved_budget: number;
    };
};

export type BudgetValidateResponse = {
    success: boolean;
    data?: {
        can_reserve: boolean;
        requested_amount: number;
        available_budget: number;
        shortage: number;
    };
};
