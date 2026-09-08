import { Head } from '@inertiajs/react';
import { CsvImportForm } from '@/components/csv-import-form';
import Heading from '@/components/heading';
import { importMethod, index } from '@/routes/ppmp';
import { process } from '@/routes/ppmp/import';
import type { Department } from '@/types';

type Props = {
    fiscalYear: number;
    department: Department | null;
};

export default function PpmpImport({ fiscalYear, department }: Props) {
    return (
        <>
            <Head title="Import PPMP" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Import PPMP"
                    description={`Quarterly quantities load into the ${department?.name ?? 'department'} plan. Items missing from that year's PS-DBMS catalog are skipped.`}
                />

                <CsvImportForm
                    {...process.form()}
                    fiscalYear={fiscalYear}
                    submitLabel="Import plan"
                />
            </div>
        </>
    );
}

PpmpImport.layout = {
    breadcrumbs: [
        { title: 'PPMP', href: index() },
        { title: 'Import', href: importMethod() },
    ],
};
