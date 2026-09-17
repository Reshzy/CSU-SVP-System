import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';

type Signatory = {
    id: number;
    position: string;
    prefix: string | null;
    suffix: string | null;
    is_active: boolean;
    user?: { name: string } | null;
};

type Props = { signatories: Signatory[] };

export default function BacSignatoriesIndex({ signatories }: Props) {
    return (
        <>
            <Head title="BAC signatories" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="BAC signatories" description="Standing roster for resolutions, RFQ, and AOQ." />
                    <Link href="/bac/signatories/create" className="text-sm text-primary underline">
                        Add signatory
                    </Link>
                </div>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Name</th>
                                <th className="px-3 py-2">Position</th>
                                <th className="px-3 py-2">Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            {signatories.map((signatory) => (
                                <tr key={signatory.id} className="border-t">
                                    <td className="px-3 py-2">
                                        {[signatory.prefix, signatory.user?.name, signatory.suffix].filter(Boolean).join(' ')}
                                    </td>
                                    <td className="px-3 py-2">{signatory.position}</td>
                                    <td className="px-3 py-2">{signatory.is_active ? 'Yes' : 'No'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
