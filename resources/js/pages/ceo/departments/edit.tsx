import { Head } from '@inertiajs/react';
import { DepartmentForm } from '@/components/department-form';
import Heading from '@/components/heading';
import { index, update } from '@/routes/ceo/departments';
import type { Department } from '@/types';

type Props = {
    department: Department;
};

export default function CeoDepartmentsEdit({ department }: Props) {
    return (
        <>
            <Head title={department.name} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={department.name}
                    description={`Code ${department.code}`}
                />

                <DepartmentForm
                    {...update.form(department.id)}
                    department={department}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

CeoDepartmentsEdit.layout = {
    breadcrumbs: [{ title: 'Departments', href: index() }],
};
