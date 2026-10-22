import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { create, destroy, edit, index } from '@/routes/supply/po-signatories';

type Signatory = {
    id: number;
    position: string;
    prefix: string | null;
    suffix: string | null;
    is_active: boolean;
    manual_name: string | null;
    user?: { name: string } | null;
};

type Props = { signatories: Signatory[] };

export default function SupplyPoSignatoriesIndex({ signatories }: Props) {
    return (
        <>
            <Head title="PO signatories" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading title="PO signatories" description="CEO and chief accountant names for PO Excel." />
                    <Link href={create.url()} className="text-sm text-primary underline">
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
                                <th className="px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {signatories.map((signatory) => (
                                <tr key={signatory.id} className="border-t">
                                    <td className="px-3 py-2">
                                        {[signatory.prefix, signatory.manual_name ?? signatory.user?.name, signatory.suffix]
                                            .filter(Boolean)
                                            .join(' ')}
                                    </td>
                                    <td className="px-3 py-2">{signatory.position.replaceAll('_', ' ')}</td>
                                    <td className="px-3 py-2">{signatory.is_active ? 'Yes' : 'No'}</td>
                                    <td className="px-3 py-2 space-x-3">
                                        <Link href={edit.url(signatory.id)} className="text-primary underline">
                                            Edit
                                        </Link>
                                        <button
                                            type="button"
                                            className="text-destructive underline"
                                            onClick={() => router.delete(destroy.url(signatory.id))}
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

SupplyPoSignatoriesIndex.layout = {
    breadcrumbs: [{ title: 'PO signatories', href: index() }],
};
