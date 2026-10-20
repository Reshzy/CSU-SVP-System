import type { RowData, Table } from '@tanstack/react-table';
import type { DataTableFeatures } from './features';
import { csvEscape, stringifyCell } from './utils';

export function exportTableCsv<TData extends RowData>(
    table: Table<DataTableFeatures, TData>,
    filename: string,
): void {
    const selected = table.getFilteredSelectedRowModel().rows;
    const rows = selected.length > 0 ? selected : table.getRowModel().rows;
    const columns = table
        .getVisibleLeafColumns()
        .filter((column) => column.id !== 'select' && column.id !== 'actions');

    const header = columns
        .map((column) => {
            const label = column.columnDef.header;

            return csvEscape(typeof label === 'string' ? label : column.id);
        })
        .join(',');

    const body = rows
        .map((row) =>
            columns
                .map((column) => csvEscape(stringifyCell(row.getValue(column.id))))
                .join(','),
        )
        .join('\n');

    const blob = new Blob([`${header}\n${body}`], {
        type: 'text/csv;charset=utf-8;',
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename.endsWith('.csv') ? filename : `${filename}.csv`;
    link.click();
    URL.revokeObjectURL(url);
}
