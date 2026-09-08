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

export type EarmarkExpenditure = {
    code?: string | null;
    description: string;
    amount: number | string;
};

export type PurchaseRequest = {
    id: number;
    pr_number: string | null;
    pr_title?: string | null;
    purpose: string;
    justification: string | null;
    estimated_total: string;
    status: PurchaseRequestStatus;
    pr_quarter: number | null;
    has_ppmp: boolean;
    submitted_at: string | null;
    created_at: string;
    return_remarks?: string | null;
    rejection_reason?: string | null;
    replaces_pr_id?: number | null;
    replaced_by_pr_id?: number | null;
    is_archived?: boolean;
    earmark_id?: string | null;
    legal_basis?: string | null;
    earmark_programs_activities?: string | null;
    earmark_responsibility_center?: string | null;
    earmark_date_to?: string | null;
    earmark_object_expenditures?: EarmarkExpenditure[] | null;
    funding_source?: string | null;
    fund_cluster_code?: string | null;
    fund_details?: string | null;
    budget_code?: string | null;
    current_step_notes?: string | null;
    procurement_method?: string | null;
    resolution_number?: string | null;
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

export type SupplyStandaloneItem = {
    id: number;
    item_name: string;
    item_code: string | null;
    estimated_total_cost: string;
};

export type SupplyAllowedAction =
    | 'start_review'
    | 'activate'
    | 'return'
    | 'reject'
    | 'cancel';

export type BudgetAllowedAction = 'approve' | 'reject' | 'export' | 'amend';

export type CeoAllowedAction = 'approve' | 'reject';

export type FundClusterOption = {
    code: string;
    label: string;
};

export type DepartmentBudgetRow = {
    id: number;
    department_id: number;
    fiscal_year: number;
    allocated_budget: string;
    utilized_budget: string;
    reserved_budget: string;
    available_budget: number;
    committed_budget: number;
    notes: string | null;
    department?: Pick<Department, 'id' | 'name' | 'code'> | null;
};

export type ReplacementFormDefaults = {
    purpose: string;
    justification: string;
    quantities: Record<number, number>;
    lotName: string;
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
