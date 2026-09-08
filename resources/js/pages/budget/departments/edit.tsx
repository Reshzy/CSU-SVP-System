import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/budget';
import type { DepartmentBudgetRow } from '@/types';

type Props = {
    budget: DepartmentBudgetRow;
};

export default function BudgetDepartmentEdit({ budget }: Props) {
    const form = useForm('put', update.url(budget.department_id), {
        fiscal_year: budget.fiscal_year,
        allocated_budget: Number(budget.allocated_budget),
        notes: budget.notes ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(budget.department_id));
    };

    return (
        <>
            <Head title={`Edit ${budget.department?.name ?? 'budget'}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={budget.department?.name ?? 'Department'}
                    description={`FY ${budget.fiscal_year} · reserved ${budget.reserved_budget} · utilized ${budget.utilized_budget}`}
                />

                <form onSubmit={submit} className="max-w-xl space-y-4">
                    <input
                        type="hidden"
                        name="fiscal_year"
                        value={form.data.fiscal_year}
                    />
                    <div className="space-y-2">
                        <Label htmlFor="allocated_budget">
                            Allocated budget
                        </Label>
                        <Input
                            id="allocated_budget"
                            type="number"
                            min={0}
                            step="0.01"
                            value={form.data.allocated_budget}
                            onChange={(event) =>
                                form.setData(
                                    'allocated_budget',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <InputError message={form.errors.allocated_budget} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="notes">Notes</Label>
                        <textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            className={cn(
                                'border-input flex min-h-20 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
                                'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
                            )}
                        />
                        <InputError message={form.errors.notes} />
                    </div>
                    <div className="flex gap-2">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Save allocation
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

BudgetDepartmentEdit.layout = {
    breadcrumbs: [{ title: 'Department budgets', href: index() }],
};
