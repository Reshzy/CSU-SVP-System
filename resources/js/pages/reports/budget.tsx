import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { budget, pr } from '@/routes/reports';
import { exportMethod } from '@/routes/reports/budget';

type Row = {
    department: { id: number; name: string; code: string };
    pr_count: number;
    pr_total: number;
    po_count: number;
    po_total: number;
    utilization_rate: number | null;
};

type Props = {
    rows: Row[];
    totals: { pr_total: number; po_total: number; pr_count: number; po_count: number };
};

export default function ReportsBudget({ rows, totals }: Props) {
    return (
        <>
            <Head title="Budget utilization" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Budget utilization"
                    description="PR totals versus PO totals by department."
                />
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href={pr()}>PR report</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <a href={exportMethod.url()}>Download CSV</a>
                    </Button>
                </div>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Department</th>
                                <th className="px-3 py-2">PR Count</th>
                                <th className="px-3 py-2">PR Total</th>
                                <th className="px-3 py-2">PO Count</th>
                                <th className="px-3 py-2">PO Total</th>
                                <th className="px-3 py-2">Utilization %</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.department.id} className="border-t">
                                    <td className="px-3 py-2">{row.department.name}</td>
                                    <td className="px-3 py-2">{row.pr_count}</td>
                                    <td className="px-3 py-2">
                                        {row.pr_total.toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                        })}
                                    </td>
                                    <td className="px-3 py-2">{row.po_count}</td>
                                    <td className="px-3 py-2">
                                        {row.po_total.toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                        })}
                                    </td>
                                    <td className="px-3 py-2">{row.utilization_rate ?? '—'}</td>
                                </tr>
                            ))}
                            <tr className="border-t font-medium">
                                <td className="px-3 py-2">Totals</td>
                                <td className="px-3 py-2">{totals.pr_count}</td>
                                <td className="px-3 py-2">
                                    {totals.pr_total.toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                    })}
                                </td>
                                <td className="px-3 py-2">{totals.po_count}</td>
                                <td className="px-3 py-2">
                                    {totals.po_total.toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                    })}
                                </td>
                                <td className="px-3 py-2" />
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

ReportsBudget.layout = {
    breadcrumbs: [
        { title: 'Reports', href: pr() },
        { title: 'Budget', href: budget() },
    ],
};
