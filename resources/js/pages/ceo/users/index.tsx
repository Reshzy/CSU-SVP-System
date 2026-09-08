import { Form, Head, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { ApprovalStatusBadge } from '@/components/approval-status-badge';
import { ApprovalStatusTabs } from '@/components/approval-status-tabs';
import Heading from '@/components/heading';
import { PaginationNav } from '@/components/pagination-nav';
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
import { index, show } from '@/routes/ceo/users';
import type { ApprovalStatus, Paginated, User } from '@/types';

type Props = {
    users: Paginated<User>;
    filters: { status: ApprovalStatus; search: string | null };
    statusCounts: Partial<Record<ApprovalStatus, number>>;
};

export default function CeoUsersIndex({ users, filters, statusCounts }: Props) {
    return (
        <>
            <Head title="User approvals" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="User approvals"
                    description="Review registrations before they can sign in. Approving grants the role their position maps to."
                />

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <ApprovalStatusTabs
                        current={filters.status}
                        counts={statusCounts}
                        hrefFor={(status) => index({ query: { status } }).url}
                    />

                    <Form
                        action={index().url}
                        method="get"
                        className="flex gap-2"
                    >
                        <input
                            type="hidden"
                            name="status"
                            value={filters.status}
                        />
                        <Input
                            name="search"
                            type="search"
                            placeholder="Name or email"
                            defaultValue={filters.search ?? ''}
                            className="w-56"
                        />
                        <Button type="submit" variant="outline" size="icon">
                            <Search className="size-4" />
                            <span className="sr-only">Search</span>
                        </Button>
                    </Form>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead>Position</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Registered</TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-muted-foreground py-10 text-center"
                                    >
                                        No {filters.status} registrations.
                                    </TableCell>
                                </TableRow>
                            )}

                            {users.data.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {user.name}
                                        </div>
                                        <div className="text-muted-foreground text-xs">
                                            {user.email}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {user.department?.code ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {user.position?.name ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <ApprovalStatusBadge
                                            status={user.approval_status}
                                        />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-sm">
                                        {new Date(
                                            user.created_at,
                                        ).toLocaleDateString()}
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={show(user.id)}>
                                                Review
                                            </Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <PaginationNav
                    links={users.links}
                    from={users.from}
                    to={users.to}
                    total={users.total}
                />
            </div>
        </>
    );
}

CeoUsersIndex.layout = {
    breadcrumbs: [{ title: 'User approvals', href: index() }],
};
