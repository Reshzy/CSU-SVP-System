import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { PpmpForm } from '@/components/ppmp-form';
import { index, update } from '@/routes/ppmp';
import type { CatalogItem, PlannedItem, Ppmp } from '@/types';

type Props = {
    ppmp: Ppmp;
    appItems: CatalogItem[];
    plannedItems: PlannedItem[];
};

export default function PpmpEdit({ ppmp, appItems, plannedItems }: Props) {
    return (
        <>
            <Head title={`Edit ${ppmp.fiscal_year} plan`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Edit ${ppmp.fiscal_year} plan`}
                    description={`${ppmp.department?.name ?? 'Your department'}. Saving replaces every line, so items left at zero are dropped.`}
                />

                <PpmpForm
                    {...update.form(ppmp.id)}
                    fiscalYear={ppmp.fiscal_year}
                    appItems={appItems}
                    plannedItems={plannedItems}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

PpmpEdit.layout = {
    breadcrumbs: [{ title: 'PPMP', href: index() }],
};
