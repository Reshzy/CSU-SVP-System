import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/purchase-requests';
import type { Paginated } from '@/types';

type PurchaseRequestRow = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    status: string;
    estimated_total: string;
    department?: { name: string } | null;
    requester?: { name: string } | null;
    created_at: string;
};

type Props = {
    purchaseRequests: Paginated<PurchaseRequestRow>;
    canCreate: boolean;
};

function statusLabel(status: string): string {
    return status === 'rejected' ? 'Deferred' : status.replaceAll('_', ' ');
}

export default function PurchaseRequestIndex({ purchaseRequests, canCreate }: Props) {
    return (
        <>
            <Head title="Purchase requests" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <Heading
                        title="Purchase requests"
                        description="Create and track department purchase requests."
                    />
                    {canCreate && (
                        <Button asChild>
                            <Link href={create.url()}>
                                <Plus className="size-4" />
                                New request
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2 font-medium">PR number</th>
                                <th className="px-3 py-2 font-medium">Title</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">Total</th>
                                <th className="px-3 py-2 font-medium">Requester</th>
                            </tr>
                        </thead>
                        <tbody>
                            {purchaseRequests.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-3 py-8 text-center text-muted-foreground">
                                        No purchase requests yet.
                                    </td>
                                </tr>
                            )}
                            {purchaseRequests.data.map((pr) => (
                                <tr key={pr.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link
                                            href={show.url(pr.id)}
                                            className="font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            {pr.pr_number ?? `#${pr.id}`}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">{pr.pr_title}</td>
                                    <td className="px-3 py-2 capitalize">{statusLabel(pr.status)}</td>
                                    <td className="px-3 py-2">
                                        {Number(pr.estimated_total).toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                        })}
                                    </td>
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

PurchaseRequestIndex.layout = {
    breadcrumbs: [{ title: 'Purchase requests', href: index() }],
};
