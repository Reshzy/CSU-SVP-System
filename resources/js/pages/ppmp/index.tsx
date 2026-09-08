import { Head, Link } from '@inertiajs/react';
import { Plus, Upload } from 'lucide-react';
import Heading from '@/components/heading';
import { PaginationNav } from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, importMethod, index, summary } from '@/routes/ppmp';
import type { Department, Paginated, Ppmp } from '@/types';

type Props = {
    ppmps: Paginated<Ppmp>;
    department: Department | null;
    fiscalYear: number;
};

export default function PpmpIndex({ ppmps, department, fiscalYear }: Props) {
    return (
        <>
            <Head title="PPMP" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="Procurement plans"
                        description={
                            department
                                ? `${department.name} plans one PPMP per fiscal year.`
                                : 'Your account is not attached to a department yet.'
                        }
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href={importMethod()}>
                                <Upload className="size-4" />
                                Import CSV
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={create()}>
                                <Plus className="size-4" />
                                Plan {fiscalYear}
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Fiscal year</TableHead>
                                <TableHead className="text-right">
                                    Items
                                </TableHead>
                                <TableHead className="text-right">
                                    Estimated cost
                                </TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {ppmps.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground py-10 text-center"
                                    >
                                        No plan yet. Import an APP-CSE worksheet
                                        or build one by hand.
                                    </TableCell>
                                </TableRow>
                            )}

                            {ppmps.data.map((ppmp) => (
                                <TableRow key={ppmp.id}>
                                    <TableCell className="font-medium">
                                        {ppmp.fiscal_year}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {ppmp.items_count ?? 0}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {ppmp.total_estimated_cost}
                                    </TableCell>
                                    <TableCell>
                                        {ppmp.status === 'validated' ? (
                                            <Badge>Validated</Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                Draft
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={summary(ppmp.id)}>
                                                Open
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <PaginationNav
                    links={ppmps.links}
                    from={ppmps.from}
                    to={ppmps.to}
                    total={ppmps.total}
                />
            </div>
        </>
    );
}

PpmpIndex.layout = {
    breadcrumbs: [{ title: 'PPMP', href: index() }],
};
