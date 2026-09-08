import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { index } from '@/routes/supply/purchase-requests';

export default function SupplyPurchaseRequestsIndex() {
    return (
        <>
            <Head title="Supply purchase requests" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Purchase requests"
                    description="New requests land here for Supply Office review. Review actions arrive in the next slice."
                />
                <p className="text-muted-foreground text-sm">
                    No review queue yet. You will still receive mail when a
                    department submits a request.
                </p>
            </div>
        </>
    );
}

SupplyPurchaseRequestsIndex.layout = {
    breadcrumbs: [{ title: 'Supply requests', href: index() }],
};
