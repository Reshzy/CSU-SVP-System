import { Link } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { DataTableRowAction } from './types';

type Props<TData> = {
    row: TData;
    actions: DataTableRowAction<TData>[];
};

export function DataTableRowActions<TData>({ row, actions }: Props<TData>) {
    if (actions.length === 0) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="size-8">
                    <MoreHorizontal className="size-4" />
                    <span className="sr-only">Open row actions</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {actions.map((action) =>
                    action.href ? (
                        <DropdownMenuItem
                            key={action.label}
                            disabled={action.disabled}
                            asChild
                        >
                            <Link href={action.href}>{action.label}</Link>
                        </DropdownMenuItem>
                    ) : (
                        <DropdownMenuItem
                            key={action.label}
                            disabled={action.disabled}
                            variant={action.variant}
                            onClick={() => action.onClick?.(row)}
                        >
                            {action.label}
                        </DropdownMenuItem>
                    ),
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
