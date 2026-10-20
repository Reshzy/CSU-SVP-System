import { createColumnHelper, type RowData } from '@tanstack/react-table';
import type { DataTableFeatures } from './features';

export function createDataTableColumnHelper<TData extends RowData>() {
    return createColumnHelper<DataTableFeatures, TData>();
}
