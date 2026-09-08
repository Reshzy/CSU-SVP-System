import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { PurchaseRequestForm } from '@/components/purchase-request-form';
import { index } from '@/routes/purchase-requests';
import { store } from '@/routes/purchase-requests/replacement';
import type {
    DepartmentBudgetSummary,
    Ppmp,
    PpmpLineForPr,
    PurchaseRequest,
    ReplacementFormDefaults,
} from '@/types';

type Props = {
    ppmp: Ppmp;
    categorizedItems: Record<string, PpmpLineForPr[]>;
    departmentBudget: DepartmentBudgetSummary;
    fiscalYear: number;
    currentQuarter: number;
    quarterLabel: string;
    originalPr: PurchaseRequest;
    defaults: ReplacementFormDefaults;
};

export default function PurchaseRequestReplacement({
    ppmp,
    categorizedItems,
    departmentBudget,
    fiscalYear,
    currentQuarter,
    quarterLabel,
    originalPr,
    defaults,
}: Props) {
    return (
        <>
            <Head title={`Replace ${originalPr.pr_number}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Replace ${originalPr.pr_number} · ${fiscalYear}`}
                    description={`${ppmp.department?.name ?? 'Your department'} can resubmit from the Q${currentQuarter} allocation. The original request will be archived.`}
                />

                {originalPr.return_remarks && (
                    <p className="rounded-xl border p-4 text-sm">
                        <span className="font-medium">Supply remarks: </span>
                        {originalPr.return_remarks}
                    </p>
                )}

                <PurchaseRequestForm
                    categorizedItems={categorizedItems}
                    departmentBudget={departmentBudget}
                    currentQuarter={currentQuarter}
                    quarterLabel={quarterLabel}
                    actionUrl={store.url(originalPr.id)}
                    defaults={defaults}
                    submitLabel="Submit replacement"
                />
            </div>
        </>
    );
}

PurchaseRequestReplacement.layout = {
    breadcrumbs: [{ title: 'Purchase requests', href: index() }],
};
