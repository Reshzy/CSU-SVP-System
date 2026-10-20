import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { SupplierForm } from '@/components/supplier-form';
import { create, index, store } from '@/routes/supply/suppliers';

export default function SupplySuppliersCreate() {
    return (
        <>
            <Head title="New supplier" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Register supplier"
                    description="Creates a pending record. The Executive Officer activates it."
                />
                <SupplierForm
                    {...store.form()}
                    submitLabel="Register supplier"
                />
            </div>
        </>
    );
}

SupplySuppliersCreate.layout = {
    breadcrumbs: [
        { title: 'Suppliers', href: index() },
        { title: 'New', href: create() },
    ],
};
