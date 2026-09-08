import type { Department } from './auth';

export type PpmpStatus = 'draft' | 'validated';

export type AppItem = {
    id: number;
    fiscal_year: number;
    category: string;
    item_code: string;
    item_name: string;
    unit_of_measure: string;
    /** Null for SOFTWARE and PART II items, which PS-DBM does not price. */
    unit_price: string | null;
    specifications?: string | null;
    is_active?: boolean;
};

/** The catalog columns the PPMP editor needs to render a pickable row. */
export type CatalogItem = Pick<
    AppItem,
    | 'id'
    | 'category'
    | 'item_code'
    | 'item_name'
    | 'unit_of_measure'
    | 'unit_price'
>;

export type Ppmp = {
    id: number;
    department_id: number;
    fiscal_year: number;
    status: PpmpStatus;
    total_estimated_cost: string;
    validated_at: string | null;
    validated_by: number | null;
    items_count?: number;
    department?: Department | null;
    validator?: { id: number; name: string } | null;
};

/** A saved PPMP line as the editor consumes it. */
export type PlannedItem = {
    app_item_id: number;
    q1_quantity: number;
    q2_quantity: number;
    q3_quantity: number;
    q4_quantity: number;
    estimated_unit_cost: string;
};

/** A saved PPMP line with its catalog item and remaining-quantity figures. */
export type PpmpSummaryItem = {
    id: number;
    app_item: Pick<
        AppItem,
        'id' | 'category' | 'item_code' | 'item_name' | 'unit_of_measure'
    >;
    q1_quantity: number;
    q2_quantity: number;
    q3_quantity: number;
    q4_quantity: number;
    total_quantity: number;
    estimated_unit_cost: string;
    estimated_total_cost: string;
    remaining_quantity: number;
    remaining_this_quarter: number;
};

export type ConsolidatedAppItem = {
    app_item_id: number;
    category: string;
    item_code: string;
    item_name: string;
    unit_of_measure: string;
    q1_quantity: number;
    q2_quantity: number;
    q3_quantity: number;
    q4_quantity: number;
    total_quantity: number;
    estimated_total_cost: number;
    department_count: number;
};

export type ConsolidatedAppStats = {
    ppmp_count: number;
    department_count: number;
    item_count: number;
    total_estimated_cost: number;
};
