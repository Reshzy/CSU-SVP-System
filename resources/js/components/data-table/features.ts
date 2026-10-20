import {
    columnFilteringFeature,
    columnPinningFeature,
    columnResizingFeature,
    columnSizingFeature,
    columnVisibilityFeature,
    createFilteredRowModel,
    createPaginatedRowModel,
    createSortedRowModel,
    filterFn_includesString,
    globalFilteringFeature,
    rowPaginationFeature,
    rowSelectionFeature,
    rowSortingFeature,
    sortFn_alphanumeric,
    tableFeatures,
} from '@tanstack/react-table';

export const dataTableFeatures = tableFeatures({
    columnVisibilityFeature,
    columnPinningFeature,
    columnSizingFeature,
    columnResizingFeature,
    columnFilteringFeature,
    globalFilteringFeature,
    rowSortingFeature,
    rowPaginationFeature,
    rowSelectionFeature,
    filteredRowModel: createFilteredRowModel(),
    sortedRowModel: createSortedRowModel(),
    paginatedRowModel: createPaginatedRowModel(),
    filterFns: {
        includesString: filterFn_includesString,
    },
    sortFns: {
        alphanumeric: sortFn_alphanumeric,
    },
});

export type DataTableFeatures = typeof dataTableFeatures;
