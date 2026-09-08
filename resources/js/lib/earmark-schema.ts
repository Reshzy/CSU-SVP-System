import { z } from 'zod';

/**
 * Mirrors Budget earmark Form Request field shape only.
 */
export const earmarkExpenditureSchema = z.object({
    code: z.string().max(50).nullable(),
    description: z.string().min(1).max(255),
    amount: z.number().min(0),
});

export const earmarkSchema = z.object({
    legal_basis: z.string().min(1),
    earmark_programs_activities: z.string().min(1),
    earmark_responsibility_center: z.string().min(1),
    earmark_date_to: z.string().min(1),
    earmark_object_expenditures: z.array(earmarkExpenditureSchema).min(1),
    fund_cluster_code: z.enum(['01', '05', '06', '07']),
    fund_details: z.string().max(255).nullable().optional(),
    budget_code: z.string().max(50).nullable().optional(),
    current_step_notes: z.string().nullable().optional(),
});

export type EarmarkFormValues = z.infer<typeof earmarkSchema>;
