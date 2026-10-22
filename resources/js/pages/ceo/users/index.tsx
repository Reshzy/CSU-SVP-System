import { Head } from '@inertiajs/react';
import { useMemo } from 'react';
import { ApprovalStatusBadge } from '@/components/approval-status-badge';
import { ApprovalStatusTabs } from '@/components/approval-status-tabs';
import {
    createDataTableColumnHelper,
    DataTable,
    type TableQueryFilters,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { index, show } from '@/routes/ceo/users';
import type { ApprovalStatus, Paginated, User } from '@/types';

type Props = {
    users: Paginated<User>;
    filters: TableQueryFilters & { status: ApprovalStatus };
    statusCounts: Partial<Record<ApprovalStatus, number>>;
};

const columnHelper = createDataTableColumnHelper<User>();

export default function CeoUsersIndex({ users, filters, statusCounts }: Props) {
    const columns = useMemo(
        () => [
            columnHelper.accessor('name', {
                header: 'Name',
                cell: ({ row }) => (
                    <div>
                        <div className="font-medium">{row.original.name}</div>
                        <div className="text-muted-foreground text-xs">
                            {row.original.email}
                        </div>
                    </div>
                ),
            }),
            columnHelper.accessor((row) => row.department?.code ?? '', {
                id: 'department',
                header: 'Department',
                enableSorting: false,
                cell: ({ getValue }) => getValue() || '—',
            }),
            columnHelper.accessor((row) => row.position?.name ?? '', {
                id: 'position',
                header: 'Position',
                enableSorting: false,
                cell: ({ getValue }) => getValue() || '—',
            }),
            columnHelper.accessor('approval_status', {
                header: 'Status',
                cell: ({ getValue }) => (
                    <ApprovalStatusBadge status={getValue()} />
                ),
            }),
            columnHelper.accessor('created_at', {
                header: 'Registered',
                cell: ({ getValue }) => (
                    <span className="text-muted-foreground text-sm">
                        {new Date(getValue()).toLocaleDateString()}
                    </span>
                ),
            }),
        ],
        [],
    );

    return (
        <>
            <Head title="User approvals" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="User approvals"
                    description="Review registrations before they can sign in. Approving grants the role their position maps to."
                />

                <ApprovalStatusTabs
                    current={filters.status}
                    counts={statusCounts}
                    hrefFor={(status) => index({ query: { status } }).url}
                />

                <DataTable
                    tableId="ceo-users"
                    visitUrl={index.url()}
                    paginated={users}
                    columns={columns}
                    filters={filters}
                    searchPlaceholder="Name or email"
                    dateColumn="created_at"
                    dateLabel="Registered"
                    initialPinning={{ start: ['name'] }}
                    rowActions={(user) => [
                        { label: 'Review', href: show.url(user.id) },
                    ]}
                    emptyMessage={`No ${filters.status} registrations.`}
                />
            </div>
        </>
    );
}

CeoUsersIndex.layout = {
    breadcrumbs: [{ title: 'User approvals', href: index() }],
};
