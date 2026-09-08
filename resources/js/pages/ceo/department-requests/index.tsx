import { Head, Link } from '@inertiajs/react';
import { ApprovalStatusBadge } from '@/components/approval-status-badge';
import { ApprovalStatusTabs } from '@/components/approval-status-tabs';
import Heading from '@/components/heading';
import { PaginationNav } from '@/components/pagination-nav';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, show } from '@/routes/ceo/department-requests';
import type { ApprovalStatus, Paginated } from '@/types';

type DepartmentRequestRow = {
    id: number;
    name: string;
    code: string;
    requester_email: string;
    status: ApprovalStatus;
    created_at: string;
};

type Props = {
    departmentRequests: Paginated<DepartmentRequestRow>;
    filters: { status: ApprovalStatus };
    statusCounts: Partial<Record<ApprovalStatus, number>>;
};

export default function CeoDepartmentRequestsIndex({
    departmentRequests,
    filters,
    statusCounts,
}: Props) {
    return (
        <>
            <Head title="Department requests" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Department requests"
                    description="Departments that guests asked for while registering."
                />

                <ApprovalStatusTabs
                    current={filters.status}
                    counts={statusCounts}
                    hrefFor={(status) => index({ query: { status } }).url}
                />

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Requested by</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Submitted</TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {departmentRequests.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-muted-foreground py-10 text-center"
                                    >
                                        No {filters.status} requests.
                                    </TableCell>
                                </TableRow>
                            )}

                            {departmentRequests.data.map((request) => (
                                <TableRow key={request.id}>
                                    <TableCell className="font-mono text-xs">
                                        {request.code}
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {request.name}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-sm">
                                        {request.requester_email}
                                    </TableCell>
                                    <TableCell>
                                        <ApprovalStatusBadge
                                            status={request.status}
                                        />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-sm">
                                        {new Date(
                                            request.created_at,
                                        ).toLocaleDateString()}
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={show(request.id)}>
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
                    links={departmentRequests.links}
                    from={departmentRequests.from}
                    to={departmentRequests.to}
                    total={departmentRequests.total}
                />
            </div>
        </>
    );
}

CeoDepartmentRequestsIndex.layout = {
    breadcrumbs: [{ title: 'Department requests', href: index() }],
};
