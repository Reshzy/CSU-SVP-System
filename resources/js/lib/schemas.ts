import { z } from 'zod';

export const purchaseRequestItemSchema = z
    .object({
        is_lot: z.boolean().optional(),
        lot_name: z.string().max(255).optional().nullable(),
        parent_lot_index: z.number().int().min(0).optional().nullable(),
        ppmp_item_id: z.number().int().optional().nullable(),
        quantity_requested: z.number().int().min(1).optional().nullable(),
    })
    .superRefine((item, context) => {
        if (item.is_lot) {
            if (!item.lot_name || item.lot_name.trim() === '') {
                context.addIssue({
                    code: 'custom',
                    message: 'Lot name is required.',
                    path: ['lot_name'],
                });
            }

            return;
        }

        if (!item.ppmp_item_id) {
            context.addIssue({
                code: 'custom',
                message: 'Select a PPMP item.',
                path: ['ppmp_item_id'],
            });
        }

        if (!item.quantity_requested || item.quantity_requested < 1) {
            context.addIssue({
                code: 'custom',
                message: 'Quantity must be at least 1.',
                path: ['quantity_requested'],
            });
        }
    });

export const purchaseRequestCreateSchema = z.object({
    pr_title: z.string().min(1, 'Title is required.').max(255),
    purpose: z.string().min(1, 'Purpose is required.'),
    items: z.array(purchaseRequestItemSchema).min(1, 'Add at least one item.'),
});

export const earmarkSchema = z.object({
    legal_basis: z.string().min(1, 'Legal basis is required.'),
    fund_cluster_code: z.enum(['01', '05', '06', '07'], {
        error: 'Fund cluster is required.',
    }),
    earmark_programs_activities: z
        .string()
        .min(1, 'Programs / activities is required.'),
    earmark_responsibility_center: z
        .string()
        .min(1, 'Responsibility center is required.'),
    earmark_date_to: z.string().min(1, 'Earmark date is required.'),
    current_step_notes: z.string().optional().nullable(),
    earmark_object_expenditures: z
        .array(
            z.object({
                description: z.string().optional().nullable(),
                amount: z.number().optional().nullable(),
            }),
        )
        .optional(),
});

export function zodIssuesToFormErrors(error: z.ZodError): Record<string, string> {
    const errors: Record<string, string> = {};

    for (const issue of error.issues) {
        const path = issue.path.join('.');

        if (path !== '' && errors[path] === undefined) {
            errors[path] = issue.message;
        }
    }

    return errors;
}
