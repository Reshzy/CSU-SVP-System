import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { ReportFiltersForm, type ReportFilters } from '@/components/report-filters';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { custom, pr } from '@/routes/reports';
import { exportMethod } from '@/routes/reports/custom';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    pr_number: string | null;
    purpose: string | null;
    status: string;
    estimated_total: string;
    created_at: string;
    date_needed: string | null;
    requester?: { name: string } | null;
    department?: { name: string } | null;
};

type Props = {
    purchaseRequests: Paginated<Row>;
    filters: ReportFilters;
    available: Record<string, string>;
    selected: string[];
    departments: { id: number; name: string }[];
    statusOptions: { value: string; label: string }[];
};

function queryFrom(filters: ReportFilters, selected: string[]): Record<string, string | number | string[]> {
    const query: Record<string, string | number | string[]> = {
        columns: selected,
    };

    if (filters.status) {
        query.status = filters.status;
    }

    if (filters.department_id) {
        query.department_id = filters.department_id;
    }

    if (filters.date_from) {
        query.date_from = filters.date_from;
    }

    if (filters.date_to) {
        query.date_to = filters.date_to;
    }

    return query;
}

export default function ReportsCustom({
    purchaseRequests,
    filters,
    available,
    selected,
    departments,
    statusOptions,
}: Props) {
    return (
        <>
            <Head title="Custom report" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Custom report"
                    description="Choose which purchase request columns to export."
                />
                <Button variant="outline" asChild>
                    <Link href={pr()}>PR report</Link>
                </Button>
                <ReportFiltersForm
                    visitUrl={custom.url()}
                    exportUrl={exportMethod.url({ query: queryFrom(filters, selected) })}
                    filters={filters}
                    departments={departments}
                    statusOptions={statusOptions}
                    extraFields={
                        <fieldset className="space-y-2 md:col-span-2 lg:col-span-4">
                            <legend className="text-sm font-medium">Columns</legend>
                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {Object.entries(available).map(([key, label]) => (
                                    <Label key={key} className="flex items-center gap-2 font-normal">
                                        <input
                                            type="checkbox"
                                            name="columns[]"
                                            value={key}
                                            defaultChecked={selected.includes(key)}
                                        />
                                        {label}
                                    </Label>
                                ))}
                            </div>
                        </fieldset>
                    }
                />
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                {selected.map((key) => (
                                    <th key={key} className="px-3 py-2">
                                        {available[key]}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {purchaseRequests.data.map((row) => (
                                <tr key={row.id} className="border-t">
                                    {selected.map((key) => (
                                        <td key={key} className="px-3 py-2">
                                            {key === 'requester'
                                                ? row.requester?.name
                                                : key === 'department'
                                                  ? row.department?.name
                                                  : key === 'priority'
                                                    ? ''
                                                    : String(row[key as keyof Row] ?? '')}
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

ReportsCustom.layout = {
    breadcrumbs: [
        { title: 'Reports', href: pr() },
        { title: 'Custom', href: custom() },
    ],
};
