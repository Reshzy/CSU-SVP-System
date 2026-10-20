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

export function pinnedStyle<TData extends RowData>(
    column: Column<DataTableFeatures, TData, unknown>,
): CSSProperties {
    const pinned = column.getIsPinned();

    return {
        width: column.getSize(),
        minWidth: column.getSize(),
        maxWidth: column.getSize(),
        position: pinned ? 'sticky' : undefined,
        insetInlineStart:
            pinned === 'start' ? `${column.getStart('start')}px` : undefined,
        insetInlineEnd:
            pinned === 'end' ? `${column.getAfter('end')}px` : undefined,
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
