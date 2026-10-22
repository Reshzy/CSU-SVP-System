import { X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DataTableViewOptions } from './view-options';
import type { DataTableFeatures } from './features';
import type { DataTableFilterDef, TableQueryFilters } from './types';
import { asFilterString } from './utils';
import type { ReactTable, RowData } from '@tanstack/react-table';

type Props<TData extends RowData> = {
    table: ReactTable<DataTableFeatures, TData>;
    search: string;
    searchPlaceholder: string;
    onSearchChange: (value: string) => void;
    filters: TableQueryFilters;
    filterDefs: DataTableFilterDef[];
    onFilterChange: (id: string, value: string) => void;
    dateColumn?: string;
    dateLabel?: string;
    onDateChange: (key: 'date_from' | 'date_to', value: string) => void;
    onClearFilters: () => void;
    onResetLayout: () => void;
};

export function DataTableToolbar<TData extends RowData>({
    table,
    search,
    searchPlaceholder,
    onSearchChange,
    filters,
    filterDefs,
    onFilterChange,
    dateColumn,
    dateLabel = 'Date',
    onDateChange,
    onClearFilters,
    onResetLayout,
}: Props<TData>) {
    const chips = activeChips({
        filters,
        filterDefs,
        dateColumn,
        dateLabel,
        onSearchChange,
        onFilterChange,
        onDateChange,
    });

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    type="search"
                    value={search}
                    onChange={(event) => onSearchChange(event.target.value)}
                    placeholder={searchPlaceholder}
                    className="w-56"
                    aria-label="Search table"
                />

                {filterDefs.map((filter) => (
                    <Select
                        key={filter.id}
                        value={asFilterString(filters[filter.id]) || '__all__'}
                        onValueChange={(value) =>
                            onFilterChange(filter.id, value === '__all__' ? '' : value)
                        }
                    >
                        <SelectTrigger size="sm" aria-label={filter.label}>
                            <SelectValue placeholder={filter.label} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">All {filter.label.toLowerCase()}</SelectItem>
                            {filter.options.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                ))}

                {dateColumn && (
                    <div className="flex items-center gap-2">
                        <Label className="text-muted-foreground text-xs">
                            {dateLabel}
                        </Label>
                        <Input
                            type="date"
                            value={asFilterString(filters.date_from)}
                            onChange={(event) =>
                                onDateChange('date_from', event.target.value)
                            }
                            className="h-8 w-auto"
                            aria-label={`${dateLabel} from`}
                        />
                        <span className="text-muted-foreground text-xs">to</span>
                        <Input
                            type="date"
                            value={asFilterString(filters.date_to)}
                            onChange={(event) =>
                                onDateChange('date_to', event.target.value)
                            }
                            className="h-8 w-auto"
                            aria-label={`${dateLabel} to`}
                        />
                    </div>
                )}

                <DataTableViewOptions table={table} />
                <Button variant="ghost" size="sm" onClick={onResetLayout}>
                    Reset layout
                </Button>
            </div>

            {chips.length > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                    {chips.map((chip) => (
                        <Badge key={chip.id} variant="secondary" className="gap-1">
                            {chip.label}
                            <button
                                type="button"
                                className="hover:bg-background rounded-full p-0.5"
                                onClick={chip.onRemove}
                                aria-label={`Remove ${chip.label}`}
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ))}
                    <Button variant="ghost" size="sm" onClick={onClearFilters}>
                        Clear all
                    </Button>
                </div>
            )}
        </div>
    );
}

function activeChips({
    filters,
    filterDefs,
    dateColumn,
    dateLabel,
    onSearchChange,
    onFilterChange,
    onDateChange,
}: {
    filters: TableQueryFilters;
    filterDefs: DataTableFilterDef[];
    dateColumn: string | undefined;
    dateLabel: string;
    onSearchChange: (value: string) => void;
    onFilterChange: (id: string, value: string) => void;
    onDateChange: (key: 'date_from' | 'date_to', value: string) => void;
}): { id: string; label: string; onRemove: () => void }[] {
    const chips: { id: string; label: string; onRemove: () => void }[] = [];

    if (filters.search) {
        chips.push({
            id: 'search',
            label: `Search: ${filters.search}`,
            onRemove: () => onSearchChange(''),
        });
    }

    for (const filter of filterDefs) {
        const value = asFilterString(filters[filter.id]);

        if (!value) {
            continue;
        }

        const option = filter.options.find((item) => item.value === value);
        chips.push({
            id: filter.id,
            label: `${filter.label}: ${option?.label ?? value}`,
            onRemove: () => onFilterChange(filter.id, ''),
        });
    }

    if (dateColumn && (filters.date_from || filters.date_to)) {
        chips.push({
            id: 'dates',
            label: `${dateLabel}: ${filters.date_from ?? '…'} – ${filters.date_to ?? '…'}`,
            onRemove: () => {
                onDateChange('date_from', '');
                onDateChange('date_to', '');
            },
        });
    }

    return chips;
}
