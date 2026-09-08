import { Form, Head, Link } from '@inertiajs/react';
import { createColumnHelper, flexRender } from '@tanstack/react-table';
import {
    getCoreRowModel,
    useLegacyTable,
} from '@tanstack/react-table/legacy';
import { useMemo } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { edit, index, show } from '@/routes/budget';
import type { DepartmentBudgetRow } from '@/types';

const columnHelper = createColumnHelper<DepartmentBudgetRow>();

type Props = {
    budgets: DepartmentBudgetRow[];
    filters: { fiscal_year: number };
};

export default function BudgetDepartmentsIndex({ budgets, filters }: Props) {
    const columns = useMemo(
        () => [
            columnHelper.accessor((row) => row.department?.code ?? '—', {
                id: 'code',
                header: 'Code',
            }),
            columnHelper.accessor((row) => row.department?.name ?? '—', {
                id: 'department',
                header: 'Department',
            }),
            columnHelper.accessor('fiscal_year', { header: 'Year' }),
            columnHelper.accessor('allocated_budget', {
                header: 'Allocated',
                cell: (info) => (
                    <span className="tabular-nums">{info.getValue()}</span>
                ),
            }),
            columnHelper.accessor('reserved_budget', {
                header: 'Reserved',
                cell: (info) => (
                    <span className="tabular-nums">{info.getValue()}</span>
                ),
            }),
            columnHelper.accessor('utilized_budget', {
                header: 'Utilized',
                cell: (info) => (
                    <span className="tabular-nums">{info.getValue()}</span>
                ),
            }),
            columnHelper.accessor('available_budget', {
                header: 'Available',
                cell: (info) => (
                    <span className="tabular-nums">
                        {info.getValue().toFixed(2)}
                    </span>
                ),
            }),
            columnHelper.accessor('committed_budget', {
                header: 'Committed',
                cell: (info) => (
                    <span className="tabular-nums">
                        {info.getValue().toFixed(2)}
                    </span>
                ),
            }),
            columnHelper.display({
                id: 'actions',
                header: '',
                cell: (info) => (
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={show(info.row.original.department_id, {
                                    query: {
                                        fiscal_year:
                                            info.row.original.fiscal_year,
                                    },
                                })}
                            >
                                View
                            </Link>
                        </Button>
                        <Button variant="outline" size="sm" asChild>
                            <Link
                                href={edit(info.row.original.department_id, {
                                    query: {
                                        fiscal_year:
                                            info.row.original.fiscal_year,
                                    },
                                })}
                            >
                                Edit
                            </Link>
                        </Button>
                    </div>
                ),
            }),
        ],
        [],
    );

    const table = useLegacyTable({
        data: budgets,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <Head title="Department budgets" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Department budgets"
                    description="Set allocated envelopes. Available is allocated minus reserved minus utilized."
                />

                <Form action={index().url} method="get" className="flex gap-2">
                    <Input
                        name="fiscal_year"
                        type="number"
                        defaultValue={filters.fiscal_year}
                        className="w-32"
                    />
                    <Button type="submit" variant="outline">
                        Show year
                    </Button>
                </Form>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <TableHead key={header.id}>
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                      header.column.columnDef
                                                          .header,
                                                      header.getContext(),
                                                  )}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {table.getRowModel().rows.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={columns.length}
                                        className="text-muted-foreground py-10 text-center"
                                    >
                                        No departments.
                                    </TableCell>
                                </TableRow>
                            )}
                            {table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

BudgetDepartmentsIndex.layout = {
    breadcrumbs: [{ title: 'Department budgets', href: index() }],
};
