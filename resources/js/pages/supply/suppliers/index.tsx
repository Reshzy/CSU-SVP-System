import { Form, Head, Link } from '@inertiajs/react';
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
import {
    approve,
    create,
    edit,
    index,
} from '@/routes/supply/suppliers';
import type { Paginated } from '@/types';

type SupplierRow = {
    id: number;
    supplier_code: string;
    business_name: string;
    contact_person: string | null;
    email: string | null;
    status: string;
};

type Props = {
    suppliers: Paginated<SupplierRow>;
    filters: TableQueryFilters;
    canApprove: boolean;
};

const columnHelper = createDataTableColumnHelper<SupplierRow>();

export default function SupplySuppliersIndex({
    suppliers,
    filters,
    canApprove,
}: Props) {
    const columns = useMemo(
        () => [
            columnHelper.accessor('supplier_code', {
                header: 'Code',
                cell: ({ getValue }) => (
                    <span className="font-mono text-xs">{getValue()}</span>
                ),
            }),
            columnHelper.accessor('business_name', {
                header: 'Business name',
                cell: ({ getValue }) => (
                    <span className="font-medium">{getValue()}</span>
                ),
            }),
            columnHelper.accessor('contact_person', {
                header: 'Contact',
                cell: ({ getValue }) => getValue() ?? '—',
            }),
            columnHelper.accessor('status', {
                header: 'Status',
                cell: ({ getValue }) => (
                    <Badge variant="secondary">
                        {getValue().replaceAll('_', ' ')}
                    </Badge>
                ),
            }),
        ],
        [],
    );

    return (
        <>
            <Head title="Suppliers" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title="Suppliers"
                        description="Internal supplier roster. Public registration stays closed."
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            New supplier
                        </Link>
                    </Button>
                </div>

                <DataTable
                    tableId="supply-suppliers"
                    visitUrl={index.url()}
                    paginated={suppliers}
                    columns={columns}
                    filters={filters}
                    searchPlaceholder="Name, code, or contact"
                    filterDefs={[
                        {
                            id: 'status',
                            label: 'Status',
                            options: [
                                { value: 'pending_verification', label: 'Pending verification' },
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Inactive' },
                                { value: 'blacklisted', label: 'Blacklisted' },
                            ],
                        },
                    ]}
                    rowActions={(supplier) => [
                        { label: 'Edit', href: edit.url(supplier.id) },
                    ]}
                    emptyMessage="No suppliers match that search."
                />

                {canApprove && (
                    <div className="space-y-2">
                        {suppliers.data
                            .filter((supplier) =>
                                ['pending_verification', 'inactive'].includes(
                                    supplier.status,
                                ),
                            )
                            .map((supplier) => (
                                <Form
                                    key={supplier.id}
                                    {...approve.form(supplier.id)}
                                    className="flex items-center justify-between rounded-lg border p-3 text-sm"
                                >
                                    <span>
                                        {supplier.business_name} is{' '}
                                        {supplier.status.replaceAll('_', ' ')}
                                    </span>
                                    <Button
                                        type="submit"
                                        size="sm"
                                        data-testid={`approve-supplier-${supplier.id}`}
                                    >
                                        Approve
                                    </Button>
                                </Form>
                            ))}
                    </div>
                )}
            </div>
        </>
    );
}

SupplySuppliersIndex.layout = {
    breadcrumbs: [{ title: 'Suppliers', href: index() }],
};
