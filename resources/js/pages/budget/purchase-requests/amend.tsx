import { Head, Link } from '@inertiajs/react';
import { EarmarkForm } from '@/components/earmark-form';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    purchaseRequestStatusBadgeVariant,
    purchaseRequestStatusLabel,
} from '@/lib/purchase-request-status';
import {
    amendEarmark,
    edit,
    index,
} from '@/routes/budget/purchase-requests';
import type { FundClusterOption, PurchaseRequest } from '@/types';

type Props = {
    purchaseRequest: PurchaseRequest;
    fundClusters: FundClusterOption[];
};

export default function BudgetPurchaseRequestAmend({
    purchaseRequest,
    fundClusters,
}: Props) {
    return (
        <>
            <Head title={`Amend ${purchaseRequest.earmark_id ?? 'earmark'}`} />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={`Amend ${purchaseRequest.earmark_id ?? 'earmark'}`}
                        description={`${purchaseRequest.pr_number} · status stays ${purchaseRequestStatusLabel(purchaseRequest.status)}`}
                    />
                    <div className="flex items-center gap-2">
                        <Badge
                            variant={purchaseRequestStatusBadgeVariant(
                                purchaseRequest.status,
                            )}
                        >
                            {purchaseRequestStatusLabel(purchaseRequest.status)}
                        </Badge>
                        <Button variant="outline" asChild>
                            <Link href={edit(purchaseRequest.id)}>Back</Link>
                        </Button>
                    </div>
                </div>

                <EarmarkForm
                    actionUrl={amendEarmark.url(purchaseRequest.id)}
                    method="patch"
                    purchaseRequest={purchaseRequest}
                    fundClusters={fundClusters}
                    submitLabel="Save earmark"
                />
            </div>
        </>
    );
}

BudgetPurchaseRequestAmend.layout = {
    breadcrumbs: [{ title: 'Budget requests', href: index() }],
};
