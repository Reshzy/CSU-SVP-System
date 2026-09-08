import { Form, Head, Link } from '@inertiajs/react';
import { Search, Upload } from 'lucide-react';
import Heading from '@/components/heading';
import { PaginationNav } from '@/components/pagination-nav';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { importMethod, index } from '@/routes/ps-dbms';
import type { AppItem, Paginated } from '@/types';

type Props = {
    appItems: Paginated<AppItem>;
    filters: {
        fiscal_year: number;
        category: string | null;
        search: string | null;
    };
    categories: string[];
    fiscalYears: number[];
    stats: { total: number; active: number };
};

export default function PsDbmsIndex({
    appItems,
    filters,
    categories,
    fiscalYears,
    stats,
}: Props) {
    return (
        <>
            <Head title="PS-DBMS catalog" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="PS-DBMS catalog"
                        description={`${stats.active} active of ${stats.total} items for ${filters.fiscal_year}. Departments plan against this list.`}
                    />
                    <Button asChild>
                        <Link href={importMethod()}>
                            <Upload className="size-4" />
                            Import CSV
                        </Link>
                    </Button>
                </div>

                <Form
                    action={index().url}
                    method="get"
                    className="flex flex-wrap gap-2"
                >
                    <Input
                        name="search"
                        type="search"
                        placeholder="Item name or code"
                        defaultValue={filters.search ?? ''}
                        className="w-56"
                    />
                    <select
                        name="fiscal_year"
                        defaultValue={filters.fiscal_year}
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        {(fiscalYears.length > 0
                            ? fiscalYears
                            : [filters.fiscal_year]
                        ).map((year) => (
                            <option key={year} value={year}>
                                {year}
                            </option>
                        ))}
                    </select>
                    <select
                        name="category"
                        defaultValue={filters.category ?? ''}
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option value="">All categories</option>
                        {categories.map((category) => (
                            <option key={category} value={category}>
                                {category}
                            </option>
                        ))}
                    </select>
                    <Button type="submit" variant="outline" size="icon">
                        <Search className="size-4" />
                        <span className="sr-only">Search</span>
                    </Button>
                </Form>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Item</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead>Unit</TableHead>
                                <TableHead className="text-right">
                                    Unit price
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {appItems.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground py-10 text-center"
                                    >
                                        No catalog items for{' '}
                                        {filters.fiscal_year}. Import the
                                        APP-CSE worksheet first.
                                    </TableCell>
                                </TableRow>
                            )}

                            {appItems.data.map((appItem) => (
                                <TableRow key={appItem.id}>
                                    <TableCell className="font-mono text-xs">
                                        {appItem.item_code}
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {appItem.item_name}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-xs">
                                        {appItem.category}
                                    </TableCell>
                                    <TableCell>
                                        {appItem.unit_of_measure}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {appItem.unit_price ?? (
                                            <Badge variant="secondary">
                                                Unpriced
                                            </Badge>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <PaginationNav
                    links={appItems.links}
                    from={appItems.from}
                    to={appItems.to}
                    total={appItems.total}
                />
            </div>
        </>
    );
}

PsDbmsIndex.layout = {
    breadcrumbs: [{ title: 'PS-DBMS catalog', href: index() }],
};
