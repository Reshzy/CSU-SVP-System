import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { edit, index } from '@/routes/budget';
import type { DepartmentBudgetRow } from '@/types';

type Props = {
    budget: DepartmentBudgetRow;
};

export default function BudgetDepartmentShow({ budget }: Props) {
    return (
        <>
            <Head title={`${budget.department?.name ?? 'Department'} budget`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={budget.department?.name ?? 'Department'}
                        description={`${budget.department?.code ?? ''} · FY ${budget.fiscal_year}`}
                    />
                    <div className="flex gap-2">
                        <Button asChild>
                            <Link
                                href={edit(budget.department_id, {
                                    query: { fiscal_year: budget.fiscal_year },
                                })}
                            >
                                Edit allocation
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={index()}>All departments</Link>
                        </Button>
                    </div>
                </div>

                <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Stat label="Allocated" value={budget.allocated_budget} />
                    <Stat label="Reserved" value={budget.reserved_budget} />
                    <Stat label="Utilized" value={budget.utilized_budget} />
                    <Stat
                        label="Available"
                        value={budget.available_budget.toFixed(2)}
                    />
                    <Stat
                        label="Committed"
                        value={budget.committed_budget.toFixed(2)}
                    />
                </dl>

                {budget.notes && (
                    <div className="rounded-xl border p-4">
                        <h2 className="mb-2 text-sm font-medium">Notes</h2>
                        <p className="text-sm">{budget.notes}</p>
                    </div>
                )}
            </div>
        </>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border p-4">
            <dt className="text-muted-foreground text-sm">{label}</dt>
            <dd className="mt-1 text-lg font-medium tabular-nums">{value}</dd>
        </div>
    );
}

BudgetDepartmentShow.layout = {
    breadcrumbs: [{ title: 'Department budgets', href: index() }],
};
