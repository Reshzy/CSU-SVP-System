import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo } from 'react';
import {
    createDataTableColumnHelper,
    DataTable,
    type TableQueryFilters,
} from '@/components/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/ceo/departments';
import type { Department, Paginated } from '@/types';

type Props = {
    departments: Paginated<Department>;
    filters: TableQueryFilters;
};

const columnHelper = createDataTableColumnHelper<Department>();

export default function CeoDepartmentsIndex({ departments, filters }: Props) {
    const columns = useMemo(
        () => [
            columnHelper.accessor('code', {
                header: 'Code',
                cell: ({ getValue }) => (
                    <span className="font-mono text-xs">{getValue()}</span>
                ),
            }),
            columnHelper.accessor('name', {
                header: 'Name',
                cell: ({ getValue }) => (
                    <span className="font-medium">{getValue()}</span>
                ),
            }),
            columnHelper.accessor('head_name', {
                header: 'Head',
                cell: ({ getValue }) => getValue() ?? '—',
            }),
            columnHelper.accessor('users_count', {
                header: 'Users',
                cell: ({ getValue }) => (
                    <span className="tabular-nums">{getValue() ?? 0}</span>
                ),
            }),
            columnHelper.accessor('is_active', {
                header: 'State',
                cell: ({ row }) => {
                    if (row.original.is_archived) {
                        return <Badge variant="destructive">Archived</Badge>;
                    }

                    return row.original.is_active ? (
                        <Badge>Active</Badge>
                    ) : (
                        <Badge variant="secondary">Inactive</Badge>
                    );
                },
            }),
        ],
        [],
    );

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

                <DataTable
                    tableId="ceo-departments"
                    visitUrl={index.url()}
                    paginated={departments}
                    columns={columns}
                    filters={filters}
                    searchPlaceholder="Name or code"
                    filterDefs={[
                        {
                            id: 'is_active',
                            label: 'State',
                            options: [
                                { value: '1', label: 'Active' },
                                { value: '0', label: 'Inactive' },
                            ],
                        },
                    ]}
                    initialPinning={{ start: ['name'] }}
                    rowActions={(department) => [
                        { label: 'Edit', href: edit.url(department.id) },
                    ]}
                    emptyMessage="No departments match that search."
                />
            </div>
        </>
    );
}

CeoDepartmentsIndex.layout = {
    breadcrumbs: [{ title: 'Departments', href: index() }],
};
