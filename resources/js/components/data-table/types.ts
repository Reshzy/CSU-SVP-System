import type { ColumnDef, RowData } from '@tanstack/react-table';
import type { DataTableFeatures } from './features';

export type TableQueryValue = string | number | boolean | null | undefined;

export type TableQueryFilters = {
    search?: string | null;
    sort?: string | null;
    direction?: 'asc' | 'desc' | null;
    per_page?: number | null;
    page?: number | null;
    date_from?: string | null;
    date_to?: string | null;
} & Record<string, TableQueryValue>;

export type DataTableFilterOption = {
    label: string;
    value: string;
};

export type DataTableFilterDef = {
    id: string;
    label: string;
    options: DataTableFilterOption[];
};

export type DataTableRowAction<TData> = {
    label: string;
    href?: string;
    onClick?: (row: TData) => void;
    variant?: 'default' | 'destructive';
    disabled?: boolean;
};

export type DataTableBulkAction<TData> = {
    label: string;
    onAction: (rows: TData[]) => void;
    variant?: 'default' | 'destructive';
};

export type DataTableColumn<TData extends RowData, TValue = any> = ColumnDef<
    DataTableFeatures,
    TData,
    TValue
>;

export type DataTableMode = 'server' | 'client';

export const PAGE_SIZES = [10, 15, 20, 50, 100] as const;

export type PageSize = (typeof PAGE_SIZES)[number];
