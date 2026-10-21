import type { Header, RowData } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown, Pin, PinOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { DataTableFeatures } from './features';
import { clampColumnSize, DEFAULT_COLUMN_MIN_SIZE } from './utils';

type Props<TData extends RowData> = {
    header: Header<DataTableFeatures, TData, unknown>;
    title: string;
};

export function DataTableColumnHeader<TData extends RowData>({ header, title }: Props<TData>) {
    const column = header.column;
    const sorted = column.getIsSorted();

    if (!column.getCanSort() && !column.getCanPin() && !column.getCanHide()) {
        return <span className="block truncate">{title}</span>;
    }

    return (
        <div className="flex min-w-0 items-center gap-1">
            {column.getCanSort() ? (
                <Button
                    variant="ghost"
                    size="sm"
                    className="-ml-2 h-8 min-w-0"
                    onClick={column.getToggleSortingHandler()}
                    aria-label={`Sort by ${title}`}
                >
                    <span className="truncate">{title}</span>
                    {sorted === 'desc' ? (
                        <ArrowDown className="size-3.5" />
                    ) : sorted === 'asc' ? (
                        <ArrowUp className="size-3.5" />
                    ) : (
                        <ChevronsUpDown className="text-muted-foreground size-3.5" />
                    )}
                </Button>
            ) : (
                <span className="truncate">{title}</span>
            )}

            {(column.getCanPin() || column.getCanHide()) && (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="size-8 p-0"
                            aria-label={`${title} column options`}
                        >
                            <Pin className="size-3.5" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        {column.getCanPin() && (
                            <>
                                <DropdownMenuItem
                                    onClick={() => column.pin('start')}
                                    disabled={column.getIsPinned() === 'start'}
                                >
                                    <Pin className="size-4" />
                                    Pin start
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => column.pin('end')}
                                    disabled={column.getIsPinned() === 'end'}
                                >
                                    <Pin className="size-4 rotate-90" />
                                    Pin end
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => column.pin(false)}
                                    disabled={!column.getIsPinned()}
                                >
                                    <PinOff className="size-4" />
                                    Unpin
                                </DropdownMenuItem>
                            </>
                        )}
                        {column.getCanPin() && column.getCanHide() && (
                            <DropdownMenuSeparator />
                        )}
                        {column.getCanHide() && (
                            <DropdownMenuItem
                                onClick={() => column.toggleVisibility(false)}
                            >
                                Hide column
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            )}

            {header.column.getCanResize() && (
                <button
                    type="button"
                    aria-label={`Resize ${title} column`}
                    className={cn(
                        'absolute inset-y-0 end-0 w-1.5 cursor-col-resize touch-none bg-transparent hover:bg-primary/40',
                        header.column.getIsResizing() && 'bg-primary',
                    )}
                    onMouseDown={header.getResizeHandler()}
                    onTouchStart={header.getResizeHandler()}
                    onKeyDown={(event) => {
                        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
                            return;
                        }

                        event.preventDefault();
                        const delta = event.key === 'ArrowRight' ? 16 : -16;
                        const next = clampColumnSize(
                            column.getSize() + delta,
                            column.columnDef.minSize ?? DEFAULT_COLUMN_MIN_SIZE,
                            column.columnDef.maxSize,
                        );
                        header.getContext().table.setColumnSizing((current) => ({
                            ...current,
                            [column.id]: next,
                        }));
                    }}
                />
            )}
        </div>
    );
}
