import type { ReactTable, RowData } from '@tanstack/react-table';
import { SlidersHorizontal } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { DataTableFeatures } from './features';

type Props<TData extends RowData> = {
    table: ReactTable<DataTableFeatures, TData>;
};

export function DataTableViewOptions<TData extends RowData>({ table }: Props<TData>) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="ml-auto">
                    <SlidersHorizontal className="size-4" />
                    Columns
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {table
                    .getAllColumns()
                    .filter((column) => column.getCanHide())
                    .map((column) => (
                        <DropdownMenuCheckboxItem
                            key={column.id}
                            checked={column.getIsVisible()}
                            onCheckedChange={(value) =>
                                column.toggleVisibility(!!value)
                            }
                            className="capitalize"
                        >
                            {typeof column.columnDef.header === 'string'
                                ? column.columnDef.header
                                : column.id.replaceAll('_', ' ')}
                        </DropdownMenuCheckboxItem>
                    ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
