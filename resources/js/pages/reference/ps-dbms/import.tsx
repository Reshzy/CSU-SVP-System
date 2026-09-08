import { Head } from '@inertiajs/react';
import { CsvImportForm } from '@/components/csv-import-form';
import Heading from '@/components/heading';
import { importMethod, index, process } from '@/routes/ps-dbms';

type Props = {
    fiscalYear: number;
};

export default function PsDbmsImport({ fiscalYear }: Props) {
    return (
        <>
            <Head title="Import PS-DBMS catalog" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Import PS-DBMS catalog"
                    description="Items upsert on item code and fiscal year, so re-importing a corrected worksheet is safe."
                />

                <CsvImportForm
                    {...process.form()}
                    fiscalYear={fiscalYear}
                    submitLabel="Import catalog"
                />
            </div>
        </>
    );
}

PsDbmsImport.layout = {
    breadcrumbs: [
        { title: 'PS-DBMS catalog', href: index() },
        { title: 'Import', href: importMethod() },
    ],
};
