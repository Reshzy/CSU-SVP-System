import type { ReactTable, RowData } from '@tanstack/react-table';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { DataTableFeatures } from './features';
import { PAGE_SIZES } from './types';

type Props<TData extends RowData> = {
    table: ReactTable<DataTableFeatures, TData>;
    from: number | null;
    to: number | null;
    total: number;
    onPageChange: (pageIndex: number) => void;
    onPageSizeChange: (pageSize: number) => void;
};

export function DataTablePagination<TData extends RowData>({
    table,
    from,
    to,
    total,
    onPageChange,
    onPageSizeChange,
}: Props<TData>) {
    const pageCount = table.getPageCount();
    const pageIndex = table.state.pagination.pageIndex;
    const pageSize = table.state.pagination.pageSize;

    const pages = visiblePages(pageIndex, pageCount);

    return (
        <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-muted-foreground text-sm">
                {total === 0
                    ? 'No results'
                    : `Showing ${from ?? 0}–${to ?? 0} of ${total}`}
            </p>

            <div className="flex flex-wrap items-center gap-2">
                <div className="flex items-center gap-2">
                    <span className="text-muted-foreground text-sm">Rows</span>
                    <Select
                        value={String(pageSize)}
                        onValueChange={(value) => onPageSizeChange(Number(value))}
                    >
                        <SelectTrigger size="sm" aria-label="Rows per page">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {PAGE_SIZES.map((size) => (
                                <SelectItem key={size} value={String(size)}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        disabled={!table.getCanPreviousPage()}
                        onClick={() => onPageChange(0)}
                        aria-label="First page"
                    >
                        <ChevronsLeft className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        disabled={!table.getCanPreviousPage()}
                        onClick={() => onPageChange(pageIndex - 1)}
                        aria-label="Previous page"
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                    {pages.map((page) => (
                        <Button
                            key={page}
                            variant={page === pageIndex ? 'default' : 'outline'}
                            size="sm"
                            className="size-8 p-0"
                            onClick={() => onPageChange(page)}
                            aria-current={page === pageIndex ? 'page' : undefined}
                        >
                            {page + 1}
                        </Button>
                    ))}
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        disabled={!table.getCanNextPage()}
                        onClick={() => onPageChange(pageIndex + 1)}
                        aria-label="Next page"
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        disabled={!table.getCanLastPage()}
                        onClick={() => onPageChange(pageCount - 1)}
                        aria-label="Last page"
                    >
                        <ChevronsRight className="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}

function visiblePages(current: number, pageCount: number): number[] {
    if (pageCount <= 0) {
        return [];
    }

    const start = Math.max(0, current - 2);
    const end = Math.min(pageCount - 1, start + 4);
    const pages: number[] = [];

    for (let page = Math.max(0, end - 4); page <= end; page++) {
        pages.push(page);
    }

    return pages;
}
