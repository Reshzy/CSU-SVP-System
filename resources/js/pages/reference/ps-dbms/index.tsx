import { Head, Link } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useMemo } from 'react';
import {
    createDataTableColumnHelper,
    DataTable,
    type TableQueryFilters,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { importMethod, index } from '@/routes/ps-dbms';
import type { AppItem, Paginated } from '@/types';

type Props = {
    appItems: Paginated<AppItem>;
    filters: TableQueryFilters & { fiscal_year: number; category: string | null };
    categories: string[];
    fiscalYears: number[];
    stats: { total: number; active: number };
};

const columnHelper = createDataTableColumnHelper<AppItem>();

export default function PsDbmsIndex({
    appItems,
    filters,
    categories,
    fiscalYears,
    stats,
}: Props) {
    const columns = useMemo(
        () => [
            columnHelper.accessor('item_code', {
                header: 'Code',
                cell: ({ getValue }) => (
                    <span className="font-mono text-xs">{getValue()}</span>
                ),
            }),
            columnHelper.accessor('item_name', {
                header: 'Item',
                cell: ({ getValue }) => (
                    <span className="font-medium">{getValue()}</span>
                ),
            }),
            columnHelper.accessor('category', {
                header: 'Category',
                cell: ({ getValue }) => (
                    <span className="text-muted-foreground text-xs">
                        {getValue()}
                    </span>
                ),
            }),
            columnHelper.accessor('unit_of_measure', { header: 'Unit' }),
            columnHelper.accessor('unit_price', {
                header: 'Unit price',
                cell: ({ getValue }) =>
                    getValue() ?? <Badge variant="secondary">Unpriced</Badge>,
            }),
        ],
        [],
    );

    const years = fiscalYears.length > 0 ? fiscalYears : [filters.fiscal_year];

    return (
        <>
            <Head title="PS-DBMS catalog" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="PS-DBMS catalog"
                        description={`${stats.active} active of ${stats.total} items for ${filters.fiscal_year}. Departments plan against this list.`}
                    />
                    <Button asChild>
                        <Link href={importMethod()}>
                            <Upload className="size-4" />
                            Import CSV
                        </Link>
                    </Button>
                </div>

                <DataTable
                    tableId="ps-dbms-catalog"
                    visitUrl={index.url()}
                    paginated={appItems}
                    columns={columns}
                    filters={filters}
                    searchPlaceholder="Item name or code"
                    filterDefs={[
                        {
                            id: 'fiscal_year',
                            label: 'Year',
                            options: years.map((year) => ({
                                value: String(year),
                                label: String(year),
                            })),
                        },
                        {
                            id: 'category',
                            label: 'Category',
                            options: categories.map((category) => ({
                                value: category,
                                label: category,
                            })),
                        },
                    ]}
                    initialPinning={{ start: ['item_code'] }}
                    emptyMessage={`No catalog items for ${filters.fiscal_year}. Import the APP-CSE worksheet first.`}
                />
            </div>
        </>
    );
}

PsDbmsIndex.layout = {
    breadcrumbs: [{ title: 'PS-DBMS catalog', href: index() }],
};
