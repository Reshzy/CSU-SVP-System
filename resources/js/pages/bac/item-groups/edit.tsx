import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { destroy, update } from '@/routes/bac/item-groups';
import { index, manage } from '@/routes/bac/quotations';
import {
    draftsFromExisting,
    ItemGroupForm,
    type ExistingGroup,
    type QuotableItem,
} from './group-form';

type Props = {
    purchaseRequest: {
        id: number;
        pr_number: string | null;
        quotable_items: QuotableItem[];
        item_groups: ExistingGroup[];
    };
};

export default function BacItemGroupsEdit({ purchaseRequest }: Props) {
    return (
        <>
            <Head title={`Edit groups ${purchaseRequest.pr_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Edit groups · ${purchaseRequest.pr_number}`}
                    description="Saving recreates groups as G1, G2…"
                />
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href={manage.url(purchaseRequest.id)}>
                            Back to quotations
                        </Link>
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        onClick={() => {
                            if (
                                window.confirm(
                                    'Remove all groups from this purchase request?',
                                )
                            ) {
                                router.delete(destroy.url(purchaseRequest.id));
                            }
                        }}
                    >
                        Remove all groups
                    </Button>
                </div>
                <ItemGroupForm
                    quotableItems={purchaseRequest.quotable_items}
                    initialGroups={draftsFromExisting(
                        purchaseRequest.item_groups,
                    )}
                    actionUrl={update.url(purchaseRequest.id)}
                    method="put"
                />
            </div>
        </>
    );
}

BacItemGroupsEdit.layout = {
    breadcrumbs: [{ title: 'BAC quotations', href: index() }],
};
