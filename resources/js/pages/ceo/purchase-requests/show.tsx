import { Head, Link, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { index, show } from '@/routes/ceo/purchase-requests';
import type { Paginated } from '@/types';

type Row = {
    id: number;
    pr_number: string | null;
    pr_title: string | null;
    estimated_total: string;
};

type IndexProps = { purchaseRequests: Paginated<Row> };

export function CeoPurchaseRequestIndexPage({ purchaseRequests }: IndexProps) {
    return (
        <>
            <Head title="CEO approvals" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="CEO purchase request approvals" description="Approve for BAC evaluation or defer." />
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">PR</th>
                                <th className="px-3 py-2">Title</th>
                                <th className="px-3 py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            {purchaseRequests.data.map((pr) => (
                                <tr key={pr.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link href={show.url(pr.id)} className="text-primary underline-offset-4 hover:underline">
                                            {pr.pr_number}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">{pr.pr_title}</td>
                                    <td className="px-3 py-2">{pr.estimated_total}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

CeoPurchaseRequestIndexPage.layout = {
    breadcrumbs: [{ title: 'CEO purchase requests', href: index() }],
};

type ShowProps = {
    purchaseRequest: {
        id: number;
        pr_number: string | null;
        pr_title: string | null;
        purpose: string | null;
        earmark_id: string | null;
    };
    statusLabel: string;
};

export default function CeoPurchaseRequestShow({ purchaseRequest, statusLabel }: ShowProps) {
    const form = useForm({
        decision: 'approve',
        rejection_reason: '',
    });

    return (
        <>
            <Head title={purchaseRequest.pr_number ?? 'CEO PR'} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={purchaseRequest.pr_number ?? 'Purchase request'}
                    description={`${purchaseRequest.pr_title} · ${statusLabel}`}
                />
                <p className="text-sm text-muted-foreground">{purchaseRequest.purpose}</p>
                <p className="text-sm">Earmark: {purchaseRequest.earmark_id}</p>
                <form
                    className="max-w-xl space-y-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/ceo/purchase-requests/${purchaseRequest.id}`);
                    }}
                >
                    <div className="flex gap-2">
                        <Button type="button" variant={form.data.decision === 'approve' ? 'default' : 'outline'} onClick={() => form.setData('decision', 'approve')}>
                            Approve
                        </Button>
                        <Button type="button" variant={form.data.decision === 'reject' ? 'default' : 'outline'} onClick={() => form.setData('decision', 'reject')}>
                            Defer
                        </Button>
                    </div>
                    <Textarea
                        placeholder="Deferral reason"
                        value={form.data.rejection_reason}
                        onChange={(event) => form.setData('rejection_reason', event.target.value)}
                    />
                    <Button type="submit" disabled={form.processing}>
                        Submit decision
                    </Button>
                </form>
            </div>
        </>
    );
}

CeoPurchaseRequestShow.layout = {
    breadcrumbs: [{ title: 'CEO purchase requests', href: index() }],
};
