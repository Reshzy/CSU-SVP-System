import { useCallback, useEffect, useState } from 'react';
import type { ColumnPinningState, ColumnSizingState, ColumnVisibilityState } from '@tanstack/react-table';
import type { PageSize } from './types';

export type TablePreferences = {
    columnVisibility: ColumnVisibilityState;
    columnSizing: ColumnSizingState;
    columnPinning: ColumnPinningState;
    columnFill: Record<string, boolean>;
    pageSize: PageSize;
};

function storageKey(tableId: string): string {
    return `data-table:${tableId}`;
}

function isPageSize(value: unknown): value is PageSize {
    return value === 10 || value === 15 || value === 20 || value === 50 || value === 100;
}

function readPreferences(tableId: string, fallback: TablePreferences): TablePreferences {
    if (typeof window === 'undefined') {
        return fallback;
    }

    try {
        const raw = window.localStorage.getItem(storageKey(tableId));

        if (!raw) {
            return fallback;
        }

        const parsed = JSON.parse(raw) as Partial<TablePreferences>;

        return {
            columnVisibility:
                parsed.columnVisibility && typeof parsed.columnVisibility === 'object'
                    ? parsed.columnVisibility
                    : fallback.columnVisibility,
            columnSizing:
                parsed.columnSizing && typeof parsed.columnSizing === 'object'
                    ? parsed.columnSizing
                    : fallback.columnSizing,
            columnPinning: {
                start: parsed.columnPinning?.start ?? fallback.columnPinning.start,
                end: parsed.columnPinning?.end ?? fallback.columnPinning.end,
            },
            columnFill:
                parsed.columnFill && typeof parsed.columnFill === 'object'
                    ? parsed.columnFill
                    : fallback.columnFill,
            pageSize: isPageSize(parsed.pageSize) ? parsed.pageSize : fallback.pageSize,
        };
    } catch {
        return fallback;
    }
}

export function useTablePreferences(tableId: string, defaults: TablePreferences) {
    const [preferences, setPreferences] = useState<TablePreferences>(() =>
        readPreferences(tableId, {
            ...defaults,
            columnPinning: {
                start: defaults.columnPinning.start ?? [],
                end: defaults.columnPinning.end ?? [],
            },
        }),
    );

    useEffect(() => {
        window.localStorage.setItem(storageKey(tableId), JSON.stringify(preferences));
    }, [preferences, tableId]);

    const resetPreferences = useCallback(() => {
        setPreferences({
            columnVisibility: {},
            columnSizing: {},
            columnPinning: {
                start: [...(defaults.columnPinning.start ?? [])],
                end: [...(defaults.columnPinning.end ?? [])],
            },
            columnFill: {},
            pageSize: defaults.pageSize,
        });
    }, [defaults]);

    return { preferences, setPreferences, resetPreferences };
}
