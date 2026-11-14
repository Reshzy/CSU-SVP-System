import {
    Check,
    ChevronRight,
    FileText,
    GitBranch,
    Pencil,
    RotateCcw,
    Scale,
    StickyNote,
    UserPlus,
    XCircle,
} from 'lucide-react';
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

type Tone = 'neutral' | 'positive' | 'negative';

const actionMeta: Record<string, { icon: typeof Check; label: string; tone?: Tone }> = {
    created: { icon: FileText, label: 'Created' },
    submitted: { icon: FileText, label: 'Submitted' },
    status_changed: { icon: GitBranch, label: 'Status changed' },
    returned: { icon: RotateCcw, label: 'Returned', tone: 'negative' },
    rejected: { icon: XCircle, label: 'Deferred', tone: 'negative' },
    approved: { icon: Check, label: 'Approved', tone: 'positive' },
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
    supplier_withdrawal: {
        icon: RotateCcw,
        label: 'Supplier withdrawal',
        tone: 'negative',
    },
    item_groups_created: { icon: GitBranch, label: 'Item groups created' },
    item_groups_updated: { icon: GitBranch, label: 'Item groups updated' },
    earmark_amended: { icon: Pencil, label: 'Earmark amended' },
};

const toneClasses: Record<Tone, string> = {
    neutral: 'bg-muted text-muted-foreground',
    positive: 'bg-primary/10 text-primary',
    negative: 'bg-destructive/10 text-destructive',
};

/** Turns snake_case tokens such as `supply_office_review` into readable words. */
function humanizeTokens(text: string): string {
    return text.replace(/\b[a-z]+(?:_[a-z]+)+\b/g, (token) => token.replaceAll('_', ' '));
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (typeof value === 'string') {
        return humanizeTokens(value);
    }

    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return JSON.stringify(value);
}

const timestampFormat = new Intl.DateTimeFormat('en-PH', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Manila',
});

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

function ChangeDetails({
    oldValue,
    newValue,
}: {
    oldValue?: Record<string, unknown> | null;
    newValue?: Record<string, unknown> | null;
}) {
    const keys = Array.from(
        new Set([...Object.keys(oldValue ?? {}), ...Object.keys(newValue ?? {})]),
    );

    return (
        <dl className="bg-muted/50 mt-2 space-y-1.5 rounded-md border p-2.5 text-xs">
            {keys.map((key) => (
                <div key={key} className="grid gap-0.5">
                    <dt className="text-muted-foreground capitalize">{key.replaceAll('_', ' ')}</dt>
                    <dd className="flex flex-wrap items-center gap-1.5 break-words">
                        {oldValue && key in oldValue && (
                            <>
                                <span className="text-muted-foreground line-through decoration-1">
                                    {formatValue(oldValue[key])}
                                </span>
                                <span className="text-muted-foreground" aria-hidden>
                                    →
                                </span>
                            </>
                        )}
                        <span className="font-medium">{formatValue(newValue?.[key])}</span>
                    </dd>
                </div>
            ))}
        </dl>
    );
}

export function PrTimeline({ activities }: { activities: TimelineActivity[] }) {
    if (activities.length === 0) {
        return (
            <div className="text-muted-foreground rounded-lg border p-4 text-sm">
                No activity recorded yet.
            </div>
        );
    }

    return (
        <ol className="bg-card rounded-lg border p-4" aria-label="Activity timeline">
            {activities.map((activity, position) => {
                const meta = actionMeta[activity.action] ?? {
                    icon: FileText,
                    label: activity.action.replaceAll('_', ' '),
                };
                const Icon = meta.icon;
                const hasChanges = Boolean(activity.old_value || activity.new_value);
                const isLast = position === activities.length - 1;
                const timestamp = timestampFormat.format(new Date(activity.created_at));

                return (
                    <li key={activity.id} className="relative flex gap-3 pb-5 last:pb-0">
                        {!isLast && (
                            <span
                                className="bg-border absolute top-8 bottom-0 left-[0.9375rem] w-px"
                                aria-hidden
                            />
                        )}
                        <span
                            className={`relative flex size-8 shrink-0 items-center justify-center rounded-full ring-4 ring-[var(--color-card)] ${toneClasses[meta.tone ?? 'neutral']}`}
                        >
                            <Icon className="size-4" />
                        </span>
                        <div className="min-w-0 flex-1 space-y-0.5 pt-1 text-sm">
                            <div className="flex flex-wrap items-baseline justify-between gap-x-2">
                                <p className="font-medium capitalize">{meta.label}</p>
                                <time
                                    className="text-muted-foreground text-xs"
                                    dateTime={activity.created_at}
                                    title={timestamp}
                                    suppressHydrationWarning
                                >
                                    {relativeTime(activity.created_at)}
                                </time>
                            </div>
                            {activity.description && (
                                <p className="text-muted-foreground">
                                    {humanizeTokens(activity.description)}
                                </p>
                            )}
                            <p className="text-muted-foreground text-xs">
                                {activity.user?.name ?? 'System'} · {timestamp}
                            </p>
                            {hasChanges && (
                                <Collapsible>
                                    <CollapsibleTrigger className="group text-primary mt-1 inline-flex items-center gap-1 text-xs underline-offset-4 hover:underline">
                                        <ChevronRight className="size-3 transition-transform group-data-[state=open]:rotate-90" />
                                        Change details
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <ChangeDetails
                                            oldValue={activity.old_value}
                                            newValue={activity.new_value}
                                        />
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
