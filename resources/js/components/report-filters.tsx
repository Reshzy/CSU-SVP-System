import { router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ReportFilters = {
    status: string | null;
    department_id: number | null;
    date_from: string | null;
    date_to: string | null;
};

type DepartmentOption = { id: number; name: string };
type StatusOption = { value: string; label: string };

export function ReportFiltersForm({
    visitUrl,
    exportUrl,
    filters,
    departments,
    statusOptions,
    extraFields,
}: {
    visitUrl: string;
    exportUrl: string;
    filters: ReportFilters;
    departments: DepartmentOption[];
    statusOptions: StatusOption[];
    extraFields?: ReactNode;
}) {
    return (
        <form
            className="grid gap-3 rounded-lg border p-4 md:grid-cols-2 lg:grid-cols-4"
            onSubmit={(event) => {
                event.preventDefault();
                const data = new FormData(event.currentTarget);
                const query: Record<string, string | string[]> = {};

                data.forEach((value, key) => {
                    if (value === '' || value === null) {
                        return;
                    }

                    if (key.endsWith('[]')) {
                        const clean = key.slice(0, -2);
                        const current = query[clean];
                        query[clean] = Array.isArray(current)
                            ? [...current, String(value)]
                            : [String(value)];

                        return;
                    }

                    query[key] = String(value);
                });

                router.get(visitUrl, query, { preserveState: true });
            }}
        >
            <div className="space-y-2">
                <Label htmlFor="status">Status</Label>
                <select
                    id="status"
                    name="status"
                    defaultValue={filters.status ?? ''}
                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                >
                    <option value="">All</option>
                    {statusOptions.map((status) => (
                        <option key={status.value} value={status.value}>
                            {status.label}
                        </option>
                    ))}
                </select>
            </div>
            <div className="space-y-2">
                <Label htmlFor="department_id">Department</Label>
                <select
                    id="department_id"
                    name="department_id"
                    defaultValue={filters.department_id ?? ''}
                    className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                >
                    <option value="">All</option>
                    {departments.map((department) => (
                        <option key={department.id} value={department.id}>
                            {department.name}
                        </option>
                    ))}
                </select>
            </div>
            <div className="space-y-2">
                <Label htmlFor="date_from">From</Label>
                <Input
                    id="date_from"
                    type="date"
                    name="date_from"
                    defaultValue={filters.date_from ?? ''}
                />
            </div>
            <div className="space-y-2">
                <Label htmlFor="date_to">To</Label>
                <Input
                    id="date_to"
                    type="date"
                    name="date_to"
                    defaultValue={filters.date_to ?? ''}
                />
            </div>
            {extraFields}
            <div className="flex flex-wrap items-end gap-2 md:col-span-2 lg:col-span-4">
                <Button type="submit">Apply filters</Button>
                <Button variant="outline" asChild>
                    <a href={exportUrl} data-testid="download-csv">
                        Download CSV
                    </a>
                </Button>
            </div>
        </form>
    );
}
