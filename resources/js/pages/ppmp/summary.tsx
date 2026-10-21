import { Form, Head, Link } from '@inertiajs/react';
import { CheckCircle2, Pencil } from 'lucide-react';
import { useMemo } from 'react';
import {
    createDataTableColumnHelper,
    DataTable,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { edit, index, validate } from '@/routes/ppmp';
import type { Ppmp, PpmpSummaryItem } from '@/types';

type Props = {
    ppmp: Ppmp;
    items: PpmpSummaryItem[];
    currentQuarter: number;
};

type SummaryRow = PpmpSummaryItem & {
    category: string;
    item_code: string;
    item_name: string;
};

const columnHelper = createDataTableColumnHelper<SummaryRow>();

function flattenItem(item: PpmpSummaryItem): SummaryRow {
    return {
        ...item,
        category: item.app_item.category,
        item_code: item.app_item.item_code,
        item_name: item.app_item.item_name,
    };
}

function NumericCell({
    value,
    className,
}: {
    value: number | string;
    className?: string;
}) {
    return (
        <span className={cn('block text-right tabular-nums', className)}>
            {value}
        </span>
    );
}

export default function PpmpSummary({ ppmp, items, currentQuarter }: Props) {
    const isValidated = ppmp.status === 'validated';

    const rows = useMemo(() => items.map(flattenItem), [items]);

    const categories = useMemo(
        () =>
            [...new Set(rows.map((row) => row.category))]
                .filter(Boolean)
                .sort()
                .map((category) => ({ value: category, label: category })),
        [rows],
    );

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
                cell: ({ row }) => (
                    <>
                        <span className="font-medium">
                            {row.original.item_name}
                        </span>
                        <span className="text-muted-foreground block text-xs">
                            {row.original.category} ·{' '}
                            {row.original.app_item.unit_of_measure}
                        </span>
                    </>
                ),
            }),
            columnHelper.accessor('q1_quantity', {
                header: 'Q1',
                cell: ({ getValue }) => <NumericCell value={getValue()} />,
            }),
            columnHelper.accessor('q2_quantity', {
                header: 'Q2',
                cell: ({ getValue }) => <NumericCell value={getValue()} />,
            }),
            columnHelper.accessor('q3_quantity', {
                header: 'Q3',
                cell: ({ getValue }) => <NumericCell value={getValue()} />,
            }),
            columnHelper.accessor('q4_quantity', {
                header: 'Q4',
                cell: ({ getValue }) => <NumericCell value={getValue()} />,
            }),
            columnHelper.accessor('total_quantity', {
                header: 'Planned',
                cell: ({ getValue }) => (
                    <NumericCell value={getValue()} className="font-medium" />
                ),
            }),
            columnHelper.accessor('remaining_quantity', {
                header: 'Remaining',
                cell: ({ getValue }) => <NumericCell value={getValue()} />,
            }),
            columnHelper.accessor('remaining_this_quarter', {
                header: `Left in Q${currentQuarter}`,
                cell: ({ getValue }) => <NumericCell value={getValue()} />,
            }),
            columnHelper.accessor('estimated_total_cost', {
                header: 'Estimated cost',
                cell: ({ getValue }) => <NumericCell value={getValue()} />,
            }),
        ],
        [currentQuarter],
    );

    return (
        <>
            <Head title={`${ppmp.fiscal_year} plan`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={`${ppmp.fiscal_year} plan`}
                        description={`${ppmp.department?.name ?? 'Department'} · ${items.length} items · ${ppmp.total_estimated_cost} estimated`}
                    />
                    <div className="flex items-center gap-2">
                        {isValidated ? (
                            <Badge>
                                Validated
                                {ppmp.validator
                                    ? ` by ${ppmp.validator.name}`
                                    : ''}
                            </Badge>
                        ) : (
                            <Badge variant="secondary">Draft</Badge>
                        )}
                        <Button variant="outline" asChild>
                            <Link href={edit(ppmp.id)}>
                                <Pencil className="size-4" />
                                Edit
                            </Link>
                        </Button>
                        {!isValidated && (
                            <Form
                                {...validate.form(ppmp.id)}
                                disableWhileProcessing
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        data-test="validate-ppmp-button"
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <CheckCircle2 className="size-4" />
                                        )}
                                        Validate plan
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                </div>

                <DataTable
                    tableId="ppmp-summary"
                    mode="client"
                    data={rows}
                    columns={columns}
                    searchPlaceholder="Item name or code"
                    filterDefs={
                        categories.length > 0
                            ? [
                                  {
                                      id: 'category',
                                      label: 'Category',
                                      options: categories,
                                  },
                              ]
                            : []
                    }
                    initialPinning={{ start: ['item_code'] }}
                    emptyMessage="This plan has no items yet."
                    noResultsMessage="No items match the current filters."
                />
            </div>
        </>
    );
}

PpmpSummary.layout = {
    breadcrumbs: [{ title: 'PPMP', href: index() }],
};
