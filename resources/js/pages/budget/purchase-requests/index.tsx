import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { index as budgetIndex } from '@/routes/budget';
import { edit } from '@/routes/budget/purchase-requests';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    estimated_total: string;
    requester?: { name: string } | null;
};

type Props = { purchaseRequests: Paginated<Row> };

export default function BudgetPurchaseRequestIndex({ purchaseRequests }: Props) {
    return (
        <>
            <Head title="Budget earmark queue" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="Budget earmark queue" description="Approve or defer purchase requests." />
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">PR</th>
                                <th className="px-3 py-2">Title</th>
                                <th className="px-3 py-2">Total</th>
                                <th className="px-3 py-2">Requester</th>
                            </tr>
                        </thead>
                        <tbody>
                            {purchaseRequests.data.map((pr) => (
                                <tr key={pr.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link href={edit.url(pr.id)} className="text-primary underline-offset-4 hover:underline">
                                            {pr.pr_number}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">{pr.pr_title}</td>
                                    <td className="px-3 py-2">{pr.estimated_total}</td>
                                    <td className="px-3 py-2">{pr.requester?.name}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

BudgetPurchaseRequestIndex.layout = {
    breadcrumbs: [
        { title: 'Budgets', href: budgetIndex() },
        { title: 'Earmark queue', href: '/budget/purchase-requests' },
    ],
};
