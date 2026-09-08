import { Form, Head } from '@inertiajs/react';
import { Search } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/bac/app';
import type { ConsolidatedAppItem, ConsolidatedAppStats } from '@/types';

type Props = {
    items: ConsolidatedAppItem[];
    stats: ConsolidatedAppStats;
    fiscalYear: number;
    fiscalYears: number[];
};

const peso = new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
});

export default function ConsolidatedApp({
    items,
    stats,
    fiscalYear,
    fiscalYears,
}: Props) {
    return (
        <>
            <Head title="Consolidated APP" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Consolidated APP"
                    description={`${stats.item_count} items across ${stats.ppmp_count} validated plans from ${stats.department_count} departments, totalling ${peso.format(stats.total_estimated_cost)}.`}
                />

                <Form action={index().url} method="get" className="flex gap-2">
                    <select
                        name="fiscal_year"
                        defaultValue={fiscalYear}
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        {(fiscalYears.length > 0
                            ? fiscalYears
                            : [fiscalYear]
                        ).map((year) => (
                            <option key={year} value={year}>
                                {year}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="outline" size="icon">
                        <Search className="size-4" />
                        <span className="sr-only">Show fiscal year</span>
                    </Button>
                </Form>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Item</TableHead>
                                <TableHead className="text-right">Q1</TableHead>
                                <TableHead className="text-right">Q2</TableHead>
                                <TableHead className="text-right">Q3</TableHead>
                                <TableHead className="text-right">Q4</TableHead>
                                <TableHead className="text-right">
                                    Total
                                </TableHead>
                                <TableHead className="text-right">
                                    Departments
                                </TableHead>
                                <TableHead className="text-right">
                                    Estimated cost
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {items.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={9}
                                        className="text-muted-foreground py-10 text-center"
                                    >
                                        No department has validated a{' '}
                                        {fiscalYear} plan yet.
                                    </TableCell>
                                </TableRow>
                            )}

                            {items.map((item) => (
                                <TableRow key={item.app_item_id}>
                                    <TableCell className="font-mono text-xs">
                                        {item.item_code}
                                    </TableCell>
                                    <TableCell>
                                        <span className="font-medium">
                                            {item.item_name}
                                        </span>
                                        <span className="text-muted-foreground block text-xs">
                                            {item.category} ·{' '}
                                            {item.unit_of_measure}
                                        </span>
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.q1_quantity}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.q2_quantity}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.q3_quantity}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.q4_quantity}
                                    </TableCell>
                                    <TableCell className="text-right font-medium tabular-nums">
                                        {item.total_quantity}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.department_count}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {peso.format(item.estimated_total_cost)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </>
    );
}

ConsolidatedApp.layout = {
    breadcrumbs: [{ title: 'Consolidated APP', href: index() }],
};
