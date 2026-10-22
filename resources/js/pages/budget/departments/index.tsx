import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    createDataTableColumnHelper,
    DataTable,
    type TableQueryFilters,
} from '@/components/data-table';
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
    filters: TableQueryFilters;
};

const columnHelper = createDataTableColumnHelper<Budget>();

function DebugBudgetInput({
    source,
    field,
    budgetId,
    value,
    onChange,
}: {
    source: 'table' | 'create';
    field: 'allocated_budget' | 'fiscal_year';
    budgetId?: number;
    value: string;
    onChange: (next: string) => void;
}) {
    const instanceId = useRef(Math.random().toString(36).slice(2, 8)).current;

    useEffect(() => {
        // #region agent log
        fetch('http://127.0.0.1:7914/ingest/e66699f4-b3b7-440d-b82c-30537e1be9fc', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Debug-Session-Id': '6ad2b1',
            },
            body: JSON.stringify({
                sessionId: '6ad2b1',
                hypothesisId: 'A',
                location: 'budget/departments/index.tsx:DebugBudgetInput',
                message: 'input-mount',
                data: { source, field, budgetId, instanceId, value },
                timestamp: Date.now(),
            }),
        }).catch(() => {});
        // #endregion

        return () => {
            // #region agent log
            fetch('http://127.0.0.1:7914/ingest/e66699f4-b3b7-440d-b82c-30537e1be9fc', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Debug-Session-Id': '6ad2b1',
                },
                body: JSON.stringify({
                    sessionId: '6ad2b1',
                    hypothesisId: 'A',
                    location: 'budget/departments/index.tsx:DebugBudgetInput',
                    message: 'input-unmount',
                    data: { source, field, budgetId, instanceId },
                    timestamp: Date.now(),
                }),
            }).catch(() => {});
            // #endregion
        };
    }, [source, field, budgetId, instanceId]);

    return (
        <Input
            data-debug-input={instanceId}
            value={value}
            onFocus={() => {
                // #region agent log
                fetch('http://127.0.0.1:7914/ingest/e66699f4-b3b7-440d-b82c-30537e1be9fc', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Debug-Session-Id': '6ad2b1',
                    },
                    body: JSON.stringify({
                        sessionId: '6ad2b1',
                        hypothesisId: 'C',
                        location: 'budget/departments/index.tsx:DebugBudgetInput',
                        message: 'input-focus',
                        data: { source, field, budgetId, instanceId, value },
                        timestamp: Date.now(),
                    }),
                }).catch(() => {});
                // #endregion
            }}
            onBlur={() => {
                // #region agent log
                fetch('http://127.0.0.1:7914/ingest/e66699f4-b3b7-440d-b82c-30537e1be9fc', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Debug-Session-Id': '6ad2b1',
                    },
                    body: JSON.stringify({
                        sessionId: '6ad2b1',
                        hypothesisId: 'C',
                        location: 'budget/departments/index.tsx:DebugBudgetInput',
                        message: 'input-blur',
                        data: { source, field, budgetId, instanceId, value },
                        timestamp: Date.now(),
                    }),
                }).catch(() => {});
                // #endregion
            }}
            onChange={(event) => {
                const next = event.target.value;
                const stillFocused = document.activeElement === event.target;
                // #region agent log
                fetch('http://127.0.0.1:7914/ingest/e66699f4-b3b7-440d-b82c-30537e1be9fc', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Debug-Session-Id': '6ad2b1',
                    },
                    body: JSON.stringify({
                        sessionId: '6ad2b1',
                        hypothesisId: 'D',
                        location: 'budget/departments/index.tsx:DebugBudgetInput',
                        message: 'input-change',
                        data: {
                            source,
                            field,
                            budgetId,
                            instanceId,
                            prev: value,
                            next,
                            stillFocused,
                        },
                        timestamp: Date.now(),
                    }),
                }).catch(() => {});
                // #endregion
                requestAnimationFrame(() => {
                    const active = document.activeElement as HTMLElement | null;
                    // #region agent log
                    fetch('http://127.0.0.1:7914/ingest/e66699f4-b3b7-440d-b82c-30537e1be9fc', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Debug-Session-Id': '6ad2b1',
                        },
                        body: JSON.stringify({
                            sessionId: '6ad2b1',
                            hypothesisId: 'A',
                            location: 'budget/departments/index.tsx:DebugBudgetInput',
                            message: 'input-after-paint',
                            data: {
                                source,
                                field,
                                budgetId,
                                instanceId,
                                next,
                                activeDebugId: active?.getAttribute('data-debug-input'),
                                activeTag: active?.tagName ?? null,
                            },
                            timestamp: Date.now(),
                        }),
                    }).catch(() => {});
                    // #endregion
                });
                onChange(next);
            }}
        />
    );
}

function AllocatedBudgetCell({ budget }: { budget: Budget }) {
    const [value, setValue] = useState(String(budget.allocated_budget));

    useEffect(() => {
        setValue(String(budget.allocated_budget));
    }, [budget.allocated_budget]);

    const dirty = value !== String(budget.allocated_budget);

    return (
        <div className="flex min-w-0 items-center gap-2">
            <div className="min-w-0 flex-1">
                <DebugBudgetInput
                    source="table"
                    field="allocated_budget"
                    budgetId={budget.id}
                    value={value}
                    onChange={setValue}
                />
            </div>
            <Button
                size="sm"
                type="button"
                disabled={!dirty}
                onClick={() => {
                    router.put(
                        update.url(budget.id),
                        {
                            allocated_budget: Number(value),
                            notes: budget.notes ?? '',
                        },
                        { preserveScroll: true },
                    );
                }}
            >
                Update
            </Button>
        </div>
    );
}

export default function BudgetDepartmentsIndex({
    budgets,
    departments,
    fiscalYear,
    filters,
}: Props) {
    const form = useForm({
        department_id: String(departments[0]?.id ?? ''),
        fiscal_year: String(fiscalYear),
        allocated_budget: '0',
        notes: '',
    });

    const columns = useMemo(() => {
        // #region agent log
        fetch('http://127.0.0.1:7914/ingest/e66699f4-b3b7-440d-b82c-30537e1be9fc', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Debug-Session-Id': '6ad2b1',
            },
            body: JSON.stringify({
                sessionId: '6ad2b1',
                hypothesisId: 'E',
                location: 'budget/departments/index.tsx:columns',
                message: 'columns-recreated',
                data: {
                    editIds: [],
                    formAllocated: 'stable-columns',
                    formYear: 'stable-columns',
                },
                timestamp: Date.now(),
            }),
        }).catch(() => {});
        // #endregion

        return [
            columnHelper.accessor((row) => row.department?.name ?? '', {
                id: 'department',
                header: 'Department',
                enableSorting: false,
                cell: ({ row }) => (
                    <span className="font-medium">
                        {row.original.department?.name ?? '—'}
                    </span>
                ),
            }),
            columnHelper.accessor('fiscal_year', { header: 'Year' }),
            columnHelper.accessor('allocated_budget', {
                header: 'Allocated',
                cell: ({ row }) => (
                    <AllocatedBudgetCell budget={row.original} />
                ),
            }),
            columnHelper.accessor('reserved_budget', {
                header: 'Reserved',
                cell: ({ getValue }) => (
                    <span className="tabular-nums">{getValue()}</span>
                ),
            }),
            columnHelper.accessor('utilized_budget', {
                header: 'Utilized',
                cell: ({ getValue }) => (
                    <span className="tabular-nums">{getValue()}</span>
                ),
            }),
        ];
    }, []);

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
                        <DebugBudgetInput
                            source="create"
                            field="fiscal_year"
                            value={form.data.fiscal_year}
                            onChange={(next) =>
                                form.setData('fiscal_year', next)
                            }
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Allocated</Label>
                        <DebugBudgetInput
                            source="create"
                            field="allocated_budget"
                            value={form.data.allocated_budget}
                            onChange={(next) =>
                                form.setData('allocated_budget', next)
                            }
                        />
                    </div>
                    <div className="flex items-end">
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                    </div>
                </form>

                <DataTable
                    tableId="budget-departments"
                    visitUrl={index.url()}
                    paginated={budgets}
                    columns={columns}
                    filters={filters}
                    searchPlaceholder="Department, code, or year"
                    initialPinning={{ start: ['department'] }}
                    emptyMessage="No department budgets yet."
                    noResultsMessage="No department budgets match the current filters."
                />
            </div>
        </>
    );
}

BudgetDepartmentsIndex.layout = {
    breadcrumbs: [{ title: 'Department budgets', href: index() }],
};
