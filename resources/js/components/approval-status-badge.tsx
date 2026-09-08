import { Badge } from '@/components/ui/badge';
import type { ApprovalStatus } from '@/types';

const variants: Record<
    ApprovalStatus,
    { label: string; variant: 'default' | 'secondary' | 'destructive' }
> = {
    pending: { label: 'Pending', variant: 'secondary' },
    approved: { label: 'Approved', variant: 'default' },
    rejected: { label: 'Rejected', variant: 'destructive' },
};

export function ApprovalStatusBadge({ status }: { status: ApprovalStatus }) {
    const { label, variant } = variants[status];

    return <Badge variant={variant}>{label}</Badge>;
}
