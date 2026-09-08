import { Form, Head, Link } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
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
import { create, edit, index } from '@/routes/ceo/departments';
import type { Department, Paginated } from '@/types';

type Props = {
    departments: Paginated<Department>;
    filters: { search: string | null };
};

export default function CeoDepartmentsIndex({ departments, filters }: Props) {
    return (
        <>
            <Head title="Departments" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="Departments"
                        description="Colleges and offices that staff can register under."
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            New department
                        </Link>
                    </Button>
                </div>

                <Form action={index().url} method="get" className="flex gap-2">
                    <Input
                        name="search"
                        type="search"
                        placeholder="Name or code"
                        defaultValue={filters.search ?? ''}
                        className="w-56"
                    />
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
                                <TableHead>Name</TableHead>
                                <TableHead>Head</TableHead>
                                <TableHead className="text-right">
                                    Users
                                </TableHead>
                                <TableHead>State</TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {departments.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-muted-foreground py-10 text-center"
                                    >
                                        No departments match that search.
                                    </TableCell>
                                </TableRow>
                            )}

                            {departments.data.map((department) => (
                                <TableRow key={department.id}>
                                    <TableCell className="font-mono text-xs">
                                        {department.code}
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {department.name}
                                    </TableCell>
                                    <TableCell>
                                        {department.head_name ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right tabular-nums">
                                        {department.users_count ?? 0}
                                    </TableCell>
                                    <TableCell>
                                        {department.is_archived ? (
                                            <Badge variant="destructive">
                                                Archived
                                            </Badge>
                                        ) : department.is_active ? (
                                            <Badge>Active</Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                Inactive
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={edit(department.id)}>
                                                Edit
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <PaginationNav
                    links={departments.links}
                    from={departments.from}
                    to={departments.to}
                    total={departments.total}
                />
            </div>
        </>
    );
}

CeoDepartmentsIndex.layout = {
    breadcrumbs: [{ title: 'Departments', href: index() }],
};
