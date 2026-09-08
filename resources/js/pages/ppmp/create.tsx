import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { PpmpForm } from '@/components/ppmp-form';
import { create, index, store } from '@/routes/ppmp';
import type { CatalogItem, PlannedItem, Ppmp } from '@/types';

type Props = {
    ppmp: Ppmp;
    appItems: CatalogItem[];
    plannedItems: PlannedItem[];
};

export default function PpmpCreate({ ppmp, appItems, plannedItems }: Props) {
    return (
        <>
            <Head title={`Plan ${ppmp.fiscal_year}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Plan ${ppmp.fiscal_year}`}
                    description={`Set quarterly quantities for ${ppmp.department?.name ?? 'your department'}. Saving replaces the whole plan.`}
                />

                <PpmpForm
                    {...store.form()}
                    fiscalYear={ppmp.fiscal_year}
                    appItems={appItems}
                    plannedItems={plannedItems}
                    submitLabel="Save plan"
                />
            </div>
        </>
    );
}

PpmpCreate.layout = {
    breadcrumbs: [
        { title: 'PPMP', href: index() },
        { title: 'Plan', href: create() },
    ],
};
