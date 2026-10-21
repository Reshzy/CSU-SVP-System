import type { Column, RowData } from '@tanstack/react-table';
import type { CSSProperties } from 'react';
import type { DataTableFeatures } from './features';
import type { TableQueryFilters, TableQueryValue } from './types';

const IDENTIFIER = /^[A-Za-z_][A-Za-z0-9_.]*$/;

export function compactQuery(
    filters: TableQueryFilters,
): Record<string, string | number | boolean> {
    const query: Record<string, string | number | boolean> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (value === null || value === undefined || value === '') {
            continue;
        }

        if (key === 'page' && Number(value) === 1) {
            continue;
        }

        query[key] = value as string | number | boolean;
    }

    return query;
}

export function humanizeStatus(status: string): string {
    if (status === 'rejected') {
        return 'Deferred';
    }

    return status.replaceAll('_', ' ');
}

export function isSafeColumnId(value: string): boolean {
    return IDENTIFIER.test(value);
}

export const DEFAULT_COLUMN_MIN_SIZE = 40;

export type ColumnLayout = {
    widths: Map<string, number>;
    starts: Map<string, number>;
    afters: Map<string, number>;
    tableWidth: number;
};

export function clampColumnSize(
    size: number,
    minSize = DEFAULT_COLUMN_MIN_SIZE,
    maxSize = Number.MAX_SAFE_INTEGER,
): number {
    return Math.min(maxSize, Math.max(minSize, size));
}

export function computeColumnLayout<TData extends RowData>(
    columns: Column<DataTableFeatures, TData, unknown>[],
    containerWidth: number,
): ColumnLayout {
    const widths = new Map<string, number>();

    for (const column of columns) {
        widths.set(column.id, column.getSize());
    }

    const fillColumns = columns.filter((column) => column.columnDef.meta?.fill);
    const baseTotal = columns.reduce(
        (total, column) => total + (widths.get(column.id) ?? 0),
        0,
    );
    const leftover = Math.max(0, containerWidth - baseTotal);

    if (fillColumns.length > 0 && leftover > 0) {
        const extra = leftover / fillColumns.length;

        for (const column of fillColumns) {
            const current = widths.get(column.id) ?? column.getSize();
            const maxSize = column.columnDef.maxSize ?? Number.MAX_SAFE_INTEGER;

            widths.set(column.id, Math.min(maxSize, current + extra));
        }
    }

    const visualTotal = columns.reduce(
        (total, column) => total + (widths.get(column.id) ?? 0),
        0,
    );
    const tableWidth =
        fillColumns.length > 0 ? Math.max(containerWidth, visualTotal) : visualTotal;

    const starts = new Map<string, number>();
    let startOffset = 0;

    for (const column of columns) {
        if (column.getIsPinned() !== 'start') {
            continue;
        }

        starts.set(column.id, startOffset);
        startOffset += widths.get(column.id) ?? 0;
    }

    const endPinned = columns.filter((column) => column.getIsPinned() === 'end');
    const afters = new Map<string, number>();
    let afterOffset = 0;

    for (const column of [...endPinned].reverse()) {
        afters.set(column.id, afterOffset);
        afterOffset += widths.get(column.id) ?? 0;
    }

    return { widths, starts, afters, tableWidth };
}

export function pinnedStyle<TData extends RowData>(
    column: Column<DataTableFeatures, TData, unknown>,
    layout: ColumnLayout,
): CSSProperties {
    const pinned = column.getIsPinned();
    const width = layout.widths.get(column.id) ?? column.getSize();

    return {
        width,
        minWidth: width,
        maxWidth: width,
        position: pinned ? 'sticky' : undefined,
        insetInlineStart:
            pinned === 'start'
                ? `${layout.starts.get(column.id) ?? 0}px`
                : undefined,
        insetInlineEnd:
            pinned === 'end' ? `${layout.afters.get(column.id) ?? 0}px` : undefined,
        zIndex: pinned ? 2 : 0,
        background: pinned ? 'var(--background)' : undefined,
        boxShadow:
            pinned === 'start'
                ? 'inset -8px 0 8px -8px rgb(0 0 0 / 0.12)'
                : pinned === 'end'
                  ? 'inset 8px 0 8px -8px rgb(0 0 0 / 0.12)'
                  : undefined,
    };
}

export function csvEscape(value: string): string {
    if (/[",\n]/.test(value)) {
        return `"${value.replaceAll('"', '""')}"`;
    }

    return value;
}

export function stringifyCell(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }

    return JSON.stringify(value);
}

export function asFilterString(value: TableQueryValue): string {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}
