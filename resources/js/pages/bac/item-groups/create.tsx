import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/bac/item-groups';
import { index, manage } from '@/routes/bac/quotations';
import {
    draftsFromExisting,
    emptyGroup,
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

export default function BacItemGroupsCreate({ purchaseRequest }: Props) {
    const initialGroups =
        purchaseRequest.item_groups.length > 0
            ? draftsFromExisting(purchaseRequest.item_groups)
            : [emptyGroup(), emptyGroup()];

    return (
        <>
            <Head title={`Split items ${purchaseRequest.pr_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={`Split items · ${purchaseRequest.pr_number}`}
                    description="Assign quotable items to parallel BAC groups (G1, G2…)."
                />
                <Button variant="outline" asChild>
                    <Link href={manage.url(purchaseRequest.id)}>
                        Back to quotations
                    </Link>
                </Button>
                <ItemGroupForm
                    quotableItems={purchaseRequest.quotable_items}
                    initialGroups={initialGroups}
                    actionUrl={store.url(purchaseRequest.id)}
                    method="post"
                />
            </div>
        </>
    );
}

BacItemGroupsCreate.layout = {
    breadcrumbs: [{ title: 'BAC quotations', href: index() }],
};
