import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { ApprovalStatus } from '@/types';

const statuses: ApprovalStatus[] = ['pending', 'approved', 'rejected'];

const labels: Record<ApprovalStatus, string> = {
    pending: 'Pending',
    approved: 'Approved',
    rejected: 'Rejected',
};

type Props = {
    current: ApprovalStatus;
    counts: Partial<Record<ApprovalStatus, number>>;
    /** Builds the href for a status; usually a Wayfinder route call. */
    hrefFor: (status: ApprovalStatus) => string;
};

export function ApprovalStatusTabs({ current, counts, hrefFor }: Props) {
    return (
        <div className="flex flex-wrap gap-1">
            {statuses.map((status) => (
                <Button
                    key={status}
                    variant={status === current ? 'default' : 'ghost'}
                    size="sm"
                    asChild
                >
                    <Link href={hrefFor(status)}>
                        {labels[status]}
                        <span className="text-muted-foreground ml-1.5 tabular-nums">
                            {counts[status] ?? 0}
                        </span>
                    </Link>
                </Button>
            ))}
        </div>
    );
}
