import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { SupplierForm } from '@/components/supplier-form';
import { index, update } from '@/routes/supply/suppliers';

type Supplier = {
    id: number;
    business_name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    tin: string | null;
    business_type: string | null;
    status: string;
};

export default function SupplySuppliersEdit({ supplier }: { supplier: Supplier }) {
    return (
        <>
            <Head title={supplier.business_name} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={supplier.business_name}
                    description="Update the internal supplier record."
                />
                <SupplierForm
                    {...update.form(supplier.id)}
                    supplier={{
                        business_name: supplier.business_name,
                        contact_person: supplier.contact_person ?? '',
                        email: supplier.email ?? '',
                        phone: supplier.phone ?? '',
                        address: supplier.address ?? '',
                        tin: supplier.tin,
                        business_type: supplier.business_type ?? 'corporation',
                        status: supplier.status,
                    }}
                    includeStatus
                    submitLabel="Save supplier"
                />
            </div>
        </>
    );
}

SupplySuppliersEdit.layout = {
    breadcrumbs: [
        { title: 'Suppliers', href: index() },
        { title: 'Edit', href: '#' },
    ],
};
