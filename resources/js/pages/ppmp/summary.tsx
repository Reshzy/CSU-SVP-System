import { Form, Head, Link } from '@inertiajs/react';
import { CheckCircle2, Pencil } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { edit, index, validate } from '@/routes/ppmp';
import type { Ppmp, PpmpSummaryItem } from '@/types';

type Props = {
    ppmp: Ppmp;
    items: PpmpSummaryItem[];
    currentQuarter: number;
};

export default function PpmpSummary({ ppmp, items, currentQuarter }: Props) {
    const isValidated = ppmp.status === 'validated';

    return (
        <>
            <Head title={`${ppmp.fiscal_year} plan`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={`${ppmp.fiscal_year} plan`}
                        description={`${ppmp.department?.name ?? 'Department'} · ${items.length} items · ${ppmp.total_estimated_cost} estimated`}
                    />
                    <div className="flex items-center gap-2">
                        {isValidated ? (
                            <Badge>
                                Validated
                                {ppmp.validator
                                    ? ` by ${ppmp.validator.name}`
                                    : ''}
                            </Badge>
                        ) : (
                            <Badge variant="secondary">Draft</Badge>
                        )}
                        <Button variant="outline" asChild>
                            <Link href={edit(ppmp.id)}>
                                <Pencil className="size-4" />
                                Edit
                            </Link>
                        </Button>
                        {!isValidated && (
                            <Form
                                {...validate.form(ppmp.id)}
                                disableWhileProcessing
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        data-test="validate-ppmp-button"
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <CheckCircle2 className="size-4" />
                                        )}
                                        Validate plan
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                </div>

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
                                    Planned
                                </TableHead>
                                <TableHead className="text-right">
                                    Remaining
                                </TableHead>
                                <TableHead className="text-right">
                                    Left in Q{currentQuarter}
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
                                        colSpan={10}
                                        className="text-muted-foreground py-10 text-center"
                                    >
                                        This plan has no items yet.
                                    </TableCell>
                                </TableRow>
                            )}

                            {items.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell className="font-mono text-xs">
                                        {item.app_item.item_code}
                                    </TableCell>
                                    <TableCell>
                                        <span className="font-medium">
                                            {item.app_item.item_name}
                                        </span>
                                        <span className="text-muted-foreground block text-xs">
                                            {item.app_item.category} ·{' '}
                                            {item.app_item.unit_of_measure}
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
                                        {item.remaining_quantity}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.remaining_this_quarter}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {item.estimated_total_cost}
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

PpmpSummary.layout = {
    breadcrumbs: [{ title: 'PPMP', href: index() }],
};
