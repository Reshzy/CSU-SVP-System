import { Head, Link } from '@inertiajs/react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { analytics, pr } from '@/routes/reports';

type Props = {
    labels: string[];
    counts: number[];
    cycle: Array<number | null>;
};

export default function ReportsAnalytics({ labels, counts, cycle }: Props) {
    const data = labels.map((label, index) => ({
        month: label,
        count: counts[index] ?? 0,
        cycle: cycle[index],
    }));

    return (
        <>
            <Head title="Procurement analytics" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Procurement analytics"
                    description="Last 12 months. View only — no export."
                />
                <Button variant="outline" asChild>
                    <Link href={pr()}>Back to PR report</Link>
                </Button>
                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-lg border p-4" data-testid="prs-created-chart">
                        <h2 className="mb-4 text-sm font-medium">PRs Created</h2>
                        <div className="h-72">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={data}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="month" />
                                    <YAxis allowDecimals={false} />
                                    <Tooltip />
                                    <Bar dataKey="count" fill="#800000" name="PRs Created" />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>
                    <div className="rounded-lg border p-4" data-testid="cycle-time-chart">
                        <h2 className="mb-4 text-sm font-medium">Avg Cycle Time (days)</h2>
                        <div className="h-72">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={data}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="month" />
                                    <YAxis />
                                    <Tooltip />
                                    <Line
                                        type="monotone"
                                        dataKey="cycle"
                                        stroke="#FF8C00"
                                        strokeWidth={2}
                                        name="Avg Cycle Time (days)"
                                        connectNulls
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

ReportsAnalytics.layout = {
    breadcrumbs: [
        { title: 'Reports', href: pr() },
        { title: 'Analytics', href: analytics() },
    ],
};
