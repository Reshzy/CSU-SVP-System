import { Head, useForm } from '@inertiajs/react';
import { flexRender } from '@tanstack/react-table/flex-render';
import {
    getCoreRowModel,
    legacyCreateColumnHelper,
    useLegacyTable,
} from '@tanstack/react-table/legacy';
import { useMemo, useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store, update } from '@/routes/budget';
import type { Paginated } from '@/types';

type Budget = {
    id: number;
    fiscal_year: number;
    allocated_budget: string;
    reserved_budget: string;
    utilized_budget: string;
    notes: string | null;
    department?: { name: string } | null;
};

type Department = { id: number; name: string; code: string };

type Props = {
    budgets: Paginated<Budget>;
    departments: Department[];
    fiscalYear: number;
};

const columnHelper = legacyCreateColumnHelper<Budget>();

export default function BudgetDepartmentsIndex({
    budgets,
    departments,
    fiscalYear,
}: Props) {
    const form = useForm({
        department_id: String(departments[0]?.id ?? ''),
        fiscal_year: String(fiscalYear),
        allocated_budget: '0',
        notes: '',
    });

    const [edits, setEdits] = useState<
        Record<number, { allocated_budget: string; notes: string }>
    >({});

    const columns = useMemo(
        () => [
            columnHelper.accessor((row) => row.department?.name ?? '', {
                id: 'department',
                header: 'Department',
            }),
            columnHelper.accessor('fiscal_year', { header: 'Year' }),
            columnHelper.display({
                id: 'allocated',
                header: 'Allocated',
                cell: (info) => {
                    const budget = info.row.original;
                    const value =
                        edits[budget.id]?.allocated_budget ??
                        budget.allocated_budget;

                    return (
                        <Input
                            value={value}
                            onChange={(event) =>
                                setEdits((current) => ({
                                    ...current,
                                    [budget.id]: {
                                        allocated_budget: event.target.value,
                                        notes:
                                            current[budget.id]?.notes ??
                                            budget.notes ??
                                            '',
                                    },
                                }))
                            }
                        />
                    );
                },
            }),
            columnHelper.accessor('reserved_budget', { header: 'Reserved' }),
            columnHelper.accessor('utilized_budget', { header: 'Utilized' }),
            columnHelper.display({
                id: 'actions',
                header: 'Actions',
                cell: (info) => {
                    const budget = info.row.original;
                    const draft = edits[budget.id];

                    return (
                        <Button
                            size="sm"
                            type="button"
                            disabled={!draft}
                            onClick={() => {
                                form.transform(() => ({
                                    allocated_budget: Number(
                                        draft.allocated_budget,
                                    ),
                                    notes: draft.notes,
                                }));
                                form.put(update.url(budget.id));
                            }}
                        >
                            Update
                        </Button>
                    );
                },
            }),
        ],
        [edits, form],
    );

    const table = useLegacyTable({
        data: budgets.data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <Head title="Department budgets" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Department budgets"
                    description="Allocate fiscal-year budgets."
                />

                <form
                    className="grid max-w-3xl gap-3 rounded-lg border p-4 md:grid-cols-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            department_id: Number(data.department_id),
                            fiscal_year: Number(data.fiscal_year),
                            allocated_budget: Number(data.allocated_budget),
                        }));
                        form.post(store.url());
                    }}
                >
                    <div className="space-y-2">
                        <Label>Department</Label>
                        <select
                            className="border-input h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.department_id}
                            onChange={(event) =>
                                form.setData('department_id', event.target.value)
                            }
                        >
                            {departments.map((department) => (
                                <option
                                    key={department.id}
                                    value={department.id}
                                >
                                    {department.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-2">
                        <Label>Year</Label>
                        <Input
                            value={form.data.fiscal_year}
                            onChange={(event) =>
                                form.setData('fiscal_year', event.target.value)
                            }
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Allocated</Label>
                        <Input
                            value={form.data.allocated_budget}
                            onChange={(event) =>
                                form.setData(
                                    'allocated_budget',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                    <div className="flex items-end">
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            {table.getHeaderGroups().map((headerGroup) => (
                                <tr key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <th
                                            key={header.id}
                                            className="px-3 py-2 font-medium"
                                        >
                                            {flexRender(
                                                header.column.columnDef.header,
                                                header.getContext(),
                                            )}
                                        </th>
                                    ))}
                                </tr>
                            ))}
                        </thead>
                        <tbody>
                            {table.getRowModel().rows.map((row) => (
                                <tr key={row.id} className="border-t">
                                    {row.getVisibleCells().map((cell) => (
                                        <td key={cell.id} className="px-3 py-2">
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

BudgetDepartmentsIndex.layout = {
    breadcrumbs: [{ title: 'Department budgets', href: index() }],
};
