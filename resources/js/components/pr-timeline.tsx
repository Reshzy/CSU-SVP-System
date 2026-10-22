import { Check, FileText, GitBranch, Pencil, RotateCcw, Scale, StickyNote, UserPlus, XCircle } from 'lucide-react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';

export type TimelineActivity = {
    id: number;
    action: string;
    description: string | null;
    old_value?: Record<string, unknown> | null;
    new_value?: Record<string, unknown> | null;
    created_at: string;
    user?: { name: string } | null;
};

const actionMeta: Record<string, { icon: typeof Check; label: string }> = {
    created: { icon: FileText, label: 'Created' },
    submitted: { icon: FileText, label: 'Submitted' },
    status_changed: { icon: GitBranch, label: 'Status changed' },
    returned: { icon: RotateCcw, label: 'Returned' },
    rejected: { icon: XCircle, label: 'Deferred' },
    approved: { icon: Check, label: 'Approved' },
    replacement_created: { icon: GitBranch, label: 'Replacement created' },
    notes_added: { icon: StickyNote, label: 'Notes added' },
    assigned: { icon: UserPlus, label: 'Assigned' },
    updated: { icon: Pencil, label: 'Updated' },
    resolution_generated: { icon: FileText, label: 'Resolution generated' },
    resolution_regenerated: { icon: FileText, label: 'Resolution regenerated' },
    rfq_generated: { icon: FileText, label: 'RFQ generated' },
    quotation_submitted: { icon: Scale, label: 'Quotation submitted' },
    quotation_evaluated: { icon: Scale, label: 'Quotation evaluated' },
    aoq_generated: { icon: Scale, label: 'AOQ generated' },
    tie_resolved: { icon: Scale, label: 'Tie resolved' },
    bac_override: { icon: Scale, label: 'BAC override' },
    supplier_withdrawal: { icon: RotateCcw, label: 'Supplier withdrawal' },
    item_groups_created: { icon: GitBranch, label: 'Item groups created' },
    item_groups_updated: { icon: GitBranch, label: 'Item groups updated' },
    earmark_amended: { icon: Pencil, label: 'Earmark amended' },
};

function relativeTime(iso: string): string {
    const then = new Date(iso).getTime();
    const delta = Math.max(0, Date.now() - then);
    const minutes = Math.floor(delta / 60000);

    if (minutes < 1) {
        return 'just now';
    }

    if (minutes < 60) {
        return `${minutes}m ago`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `${hours}h ago`;
    }

    const days = Math.floor(hours / 24);

    return `${days}d ago`;
}

export function PrTimeline({ activities }: { activities: TimelineActivity[] }) {
    if (activities.length === 0) {
        return (
            <div className="rounded-lg border p-4 text-sm text-muted-foreground">
                No activity recorded yet.
            </div>
        );
    }

    return (
        <ol className="space-y-3" aria-label="Activity timeline">
            {activities.map((activity) => {
                const meta = actionMeta[activity.action] ?? {
                    icon: FileText,
                    label: activity.action.replaceAll('_', ' '),
                };
                const Icon = meta.icon;
                const hasJson = Boolean(activity.old_value || activity.new_value);

                return (
                    <li key={activity.id} className="flex gap-3 rounded-lg border p-3">
                        <span className="bg-muted text-muted-foreground mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full">
                            <Icon className="size-4" />
                        </span>
                        <div className="min-w-0 flex-1 space-y-1 text-sm">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <p className="font-medium capitalize">{meta.label}</p>
                                <p className="text-muted-foreground text-xs">
                                    {relativeTime(activity.created_at)} ·{' '}
                                    {new Date(activity.created_at).toLocaleString()}
                                </p>
                            </div>
                            {activity.description && (
                                <p className="text-muted-foreground">{activity.description}</p>
                            )}
                            <p className="text-muted-foreground text-xs">
                                {activity.user?.name ?? 'System'}
                            </p>
                            {hasJson && (
                                <Collapsible>
                                    <CollapsibleTrigger className="text-primary text-xs underline-offset-4 hover:underline">
                                        Show change details
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <pre className="bg-muted mt-2 overflow-x-auto rounded p-2 text-xs">
                                            {JSON.stringify(
                                                {
                                                    old: activity.old_value,
                                                    new: activity.new_value,
                                                },
                                                null,
                                                2,
                                            )}
                                        </pre>
                                    </CollapsibleContent>
                                </Collapsible>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
