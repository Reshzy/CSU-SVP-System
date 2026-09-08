import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { PaginationLink } from '@/types';

type Props = {
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
    className?: string;
};

export function PaginationNav({ links, from, to, total, className }: Props) {
    if (total === 0) {
        return null;
    }

    return (
        <div
            className={cn(
                'flex flex-wrap items-center justify-between gap-3',
                className,
            )}
        >
            <p className="text-muted-foreground text-sm">
                Showing {from ?? 0}–{to ?? 0} of {total}
            </p>

            {links.length > 3 && (
                <div className="flex flex-wrap gap-1">
                    {links.map((link) => (
                        <Button
                            key={link.label}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            asChild={Boolean(link.url)}
                            disabled={!link.url}
                        >
                            {link.url ? (
                                <Link
                                    href={link.url}
                                    preserveScroll
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ) : (
                                <span
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            )}
                        </Button>
                    ))}
                </div>
            )}
        </div>
    );
}
