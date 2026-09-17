import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Paginated } from '@/types';

type Budget = {
    id: number;
    fiscal_year: number;
    allocated_budget: string;
    reserved_budget: string;
    utilized_budget: string;
    department?: { name: string } | null;
};

type Department = { id: number; name: string; code: string };

type Props = {
    budgets: Paginated<Budget>;
    departments: Department[];
    fiscalYear: number;
};

export default function BudgetDepartmentsIndex({ budgets, departments, fiscalYear }: Props) {
    const form = useForm({
        department_id: String(departments[0]?.id ?? ''),
        fiscal_year: String(fiscalYear),
        allocated_budget: '0',
        notes: '',
    });

    return (
        <>
            <Head title="Department budgets" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Department budgets" description="Allocate fiscal-year budgets." />

                <form
                    className="grid max-w-3xl gap-3 rounded-lg border p-4 md:grid-cols-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) => ({
                            ...data,
                            department_id: Number(data.department_id),
                            fiscal_year: Number(data.fiscal_year),
                            allocated_budget: Number(data.allocated_budget),
                        })).post('/budget/departments');
                    }}
                >
                    <div className="space-y-2">
                        <Label>Department</Label>
                        <select
                            className="border-input h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.department_id}
                            onChange={(event) => form.setData('department_id', event.target.value)}
                        >
                            {departments.map((department) => (
                                <option key={department.id} value={department.id}>
                                    {department.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-2">
                        <Label>Year</Label>
                        <Input
                            value={form.data.fiscal_year}
                            onChange={(event) => form.setData('fiscal_year', event.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Allocated</Label>
                        <Input
                            value={form.data.allocated_budget}
                            onChange={(event) => form.setData('allocated_budget', event.target.value)}
                        />
                    </div>
                    <div className="flex items-end">
                        <Button type="submit">Save</Button>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Department</th>
                                <th className="px-3 py-2">Year</th>
                                <th className="px-3 py-2">Allocated</th>
                                <th className="px-3 py-2">Reserved</th>
                                <th className="px-3 py-2">Utilized</th>
                            </tr>
                        </thead>
                        <tbody>
                            {budgets.data.map((budget) => (
                                <tr key={budget.id} className="border-t">
                                    <td className="px-3 py-2">{budget.department?.name}</td>
                                    <td className="px-3 py-2">{budget.fiscal_year}</td>
                                    <td className="px-3 py-2">{budget.allocated_budget}</td>
                                    <td className="px-3 py-2">{budget.reserved_budget}</td>
                                    <td className="px-3 py-2">{budget.utilized_budget}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
