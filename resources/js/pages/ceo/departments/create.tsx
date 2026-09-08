import { Head } from '@inertiajs/react';
import { DepartmentForm } from '@/components/department-form';
import Heading from '@/components/heading';
import { index, store } from '@/routes/ceo/departments';

export default function CeoDepartmentsCreate() {
    return (
        <>
            <Head title="New department" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="New department"
                    description="Add a college or office that staff can register under."
                />

                <DepartmentForm
                    {...store.form()}
                    submitLabel="Create department"
                />
            </div>
        </>
    );
}

CeoDepartmentsCreate.layout = {
    breadcrumbs: [
        { title: 'Departments', href: index() },
        { title: 'New', href: '#' },
    ],
};
