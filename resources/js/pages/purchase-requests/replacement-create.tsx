import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Original = {
    id: number;
    pr_number: string | null;
    return_remarks: string | null;
};

type Props = {
    original: Original;
};

export default function ReplacementCreate({ original }: Props) {
    const form = useForm({
        pr_title: '',
        purpose: '',
        date_needed: '',
        fund_cluster_code: '01',
        items: [] as Array<{ ppmp_item_id: number; quantity_requested: number }>,
    });

    return (
        <>
            <Head title="Replacement purchase request" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Create replacement"
                    description={`Replacing ${original.pr_number}. Supply remarks: ${original.return_remarks ?? '—'}`}
                />
                <p className="text-sm text-muted-foreground">
                    Use the standard create form flow with your validated PPMP items, then submit here once wired
                    with item selection. For now submit via API/tests with item payload.
                </p>
                <form
                    className="max-w-xl space-y-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/purchase-requests/${original.id}/replacement`);
                    }}
                >
                    <div className="space-y-2">
                        <Label>Title</Label>
                        <Input
                            value={form.data.pr_title}
                            onChange={(event) => form.setData('pr_title', event.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Purpose</Label>
                        <Textarea
                            value={form.data.purpose}
                            onChange={(event) => form.setData('purpose', event.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label>Date needed</Label>
                        <Input
                            type="date"
                            value={form.data.date_needed}
                            onChange={(event) => form.setData('date_needed', event.target.value)}
                        />
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Submit replacement
                    </Button>
                </form>
            </div>
        </>
    );
}
