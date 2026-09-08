import { z } from 'zod';

/**
 * Mirrors StorePurchaseRequestRequest field shape only. Remaining-qty and
 * budget rules stay on the server.
 */
export const purchaseRequestItemSchema = z.object({
    ppmp_item_id: z.number().int().nullable(),
    item_code: z.string().max(100).nullable(),
    item_name: z.string().min(1).max(255),
    detailed_specifications: z.string().nullable(),
    unit_of_measure: z.string().min(1).max(50),
    quantity_requested: z.number().int().min(1),
    estimated_unit_cost: z.number().min(0),
    is_lot: z.boolean().optional(),
    lot_name: z.string().max(255).nullable().optional(),
    parent_lot_index: z.number().int().min(0).nullable().optional(),
});

export const purchaseRequestSchema = z.object({
    purpose: z.string().min(1).max(255),
    justification: z.string().min(1),
    items: z.array(purchaseRequestItemSchema).min(1),
});

export type PurchaseRequestFormValues = z.infer<typeof purchaseRequestSchema>;
