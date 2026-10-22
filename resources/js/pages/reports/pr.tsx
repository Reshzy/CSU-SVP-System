import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { ReportFiltersForm, type ReportFilters } from '@/components/report-filters';
import { Button } from '@/components/ui/button';
import { analytics, budget, custom, pr, suppliers } from '@/routes/reports';
import { exportMethod } from '@/routes/reports/pr';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    pr_number: string | null;
    purpose: string | null;
    status: string;
    estimated_total: string;
    created_at: string;
    requester?: { name: string } | null;
    department?: { name: string } | null;
};

type Props = {
    purchaseRequests: Paginated<Row>;
    filters: ReportFilters;
    departments: { id: number; name: string }[];
    statusOptions: { value: string; label: string }[];
};

function queryFrom(filters: ReportFilters): Record<string, string | number> {
    const query: Record<string, string | number> = {};

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

export default function ReportsPr({
    purchaseRequests,
    filters,
    departments,
    statusOptions,
}: Props) {
    return (
        <>
            <Head title="Purchase request report" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Purchase request report"
                    description="Filter and export purchase requests as CSV."
                />
                <div className="flex flex-wrap gap-2">
                    <Button variant="secondary" asChild>
                        <Link href={pr()}>PR list</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={analytics()}>Analytics</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={suppliers()}>Suppliers</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={budget()}>Budget</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={custom()}>Custom</Link>
                    </Button>
                </div>
                <ReportFiltersForm
                    visitUrl={pr.url()}
                    exportUrl={exportMethod.url({ query: queryFrom(filters) })}
                    filters={filters}
                    departments={departments}
                    statusOptions={statusOptions}
                />
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">PR Number</th>
                                <th className="px-3 py-2">Created At</th>
                                <th className="px-3 py-2">Requester</th>
                                <th className="px-3 py-2">Department</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Estimated Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {purchaseRequests.data.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {row.pr_number}
                                    </td>
                                    <td className="px-3 py-2">
                                        {new Date(row.created_at).toLocaleString()}
                                    </td>
                                    <td className="px-3 py-2">{row.requester?.name}</td>
                                    <td className="px-3 py-2">{row.department?.name}</td>
                                    <td className="px-3 py-2 capitalize">
                                        {row.status.replaceAll('_', ' ')}
                                    </td>
                                    <td className="px-3 py-2">
                                        {Number(row.estimated_total).toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                        })}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

ReportsPr.layout = {
    breadcrumbs: [{ title: 'Reports', href: pr() }],
};
