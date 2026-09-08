import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { PurchaseRequestForm } from '@/components/purchase-request-form';
import { create, index } from '@/routes/purchase-requests';
import type {
    DepartmentBudgetSummary,
    Ppmp,
    PpmpLineForPr,
} from '@/types';

type Props = {
    ppmp: Ppmp;
    ppmpCategories: string[];
    categorizedItems: Record<string, PpmpLineForPr[]>;
    departmentBudget: DepartmentBudgetSummary;
    fiscalYear: number;
    currentQuarter: number;
    quarterLabel: string;
};

export default function PurchaseRequestCreate({
    ppmp,
    categorizedItems,
    departmentBudget,
    fiscalYear,
    currentQuarter,
    quarterLabel,
}: Props) {
    return (
        <>
            <Head title="New purchase request" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`New purchase request · ${fiscalYear}`}
                    description={`${ppmp.department?.name ?? 'Your department'} can only draw from the Q${currentQuarter} allocation of the validated plan.`}
                />

                <PurchaseRequestForm
                    categorizedItems={categorizedItems}
                    departmentBudget={departmentBudget}
                    currentQuarter={currentQuarter}
                    quarterLabel={quarterLabel}
                />
            </div>
        </>
    );
}

PurchaseRequestCreate.layout = {
    breadcrumbs: [
        { title: 'Purchase requests', href: index() },
        { title: 'New request', href: create() },
    ],
};
