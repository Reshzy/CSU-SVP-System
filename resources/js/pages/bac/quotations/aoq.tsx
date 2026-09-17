import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';

type Props = {
    purchaseRequest: { id: number; pr_number: string | null };
    unresolvedTies: number[];
    latestAoq: { aoq_reference_number: string } | null;
};

export default function BacAoqPage({ purchaseRequest, unresolvedTies, latestAoq }: Props) {
    const form = useForm({});

    return (
        <>
            <Head title={`AOQ ${purchaseRequest.pr_number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title={`AOQ · ${purchaseRequest.pr_number}`} description="Abstract of Quotations" />
                {unresolvedTies.length > 0 ? (
                    <p className="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm">
                        Unresolved ties on items: {unresolvedTies.join(', ')}. Resolve ties before generating AOQ.
                    </p>
                ) : (
                    <Button
                        onClick={() => form.post(`/bac/quotations/${purchaseRequest.id}/aoq/generate`)}
                        disabled={form.processing}
                    >
                        Generate AOQ
                    </Button>
                )}
                {latestAoq && (
                    <p className="text-sm">
                        Latest AOQ: {latestAoq.aoq_reference_number}{' '}
                        <a className="underline" href={`/bac/quotations/${purchaseRequest.id}/aoq/download`}>
                            Download
                        </a>
                    </p>
                )}
            </div>
        </>
    );
}
