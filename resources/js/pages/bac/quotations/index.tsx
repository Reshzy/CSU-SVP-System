import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import type { Paginated } from '@/types';

type Row = { id: number; pr_number: string | null; pr_title: string | null; status: string };

type Props = { purchaseRequests: Paginated<Row> };

export default function BacQuotationsIndex({ purchaseRequests }: Props) {
    return (
        <>
            <Head title="BAC quotations" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading title="BAC quotations" description="Canvass RFQ, quotations, and AOQ." />
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">PR</th>
                                <th className="px-3 py-2">Title</th>
                                <th className="px-3 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {purchaseRequests.data.map((pr) => (
                                <tr key={pr.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link href={`/bac/quotations/${pr.id}/manage`} className="text-primary underline-offset-4 hover:underline">
                                            {pr.pr_number}
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2">{pr.pr_title}</td>
                                    <td className="px-3 py-2 capitalize">{pr.status.replaceAll('_', ' ')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
