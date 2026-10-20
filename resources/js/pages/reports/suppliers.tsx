import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { pr, suppliers } from '@/routes/reports';
import { exportMethod } from '@/routes/reports/suppliers';

type Row = {
    supplier: { id: number; business_name: string; supplier_code: string };
    total_quotes: number;
    awards: number;
    win_rate: number | null;
    awarded_total: number;
    po_count: number;
    po_completed: number;
    po_total: number;
};

export default function ReportsSuppliers({ rows }: { rows: Row[] }) {
    return (
        <>
            <Head title="Supplier performance" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Supplier performance"
                    description="Quotes, awards, and completed purchase orders."
                />
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href={pr()}>PR report</Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <a href={exportMethod.url()}>Download CSV</a>
                    </Button>
                </div>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-3 py-2">Supplier</th>
                                <th className="px-3 py-2">Quotes</th>
                                <th className="px-3 py-2">Awards</th>
                                <th className="px-3 py-2">Win Rate %</th>
                                <th className="px-3 py-2">Awarded Value</th>
                                <th className="px-3 py-2">POs</th>
                                <th className="px-3 py-2">Completed POs</th>
                                <th className="px-3 py-2">PO Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr key={row.supplier.id} className="border-t">
                                    <td className="px-3 py-2">{row.supplier.business_name}</td>
                                    <td className="px-3 py-2">{row.total_quotes}</td>
                                    <td className="px-3 py-2">{row.awards}</td>
                                    <td className="px-3 py-2">{row.win_rate ?? '—'}</td>
                                    <td className="px-3 py-2">
                                        {row.awarded_total.toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                        })}
                                    </td>
                                    <td className="px-3 py-2">{row.po_count}</td>
                                    <td className="px-3 py-2">{row.po_completed}</td>
                                    <td className="px-3 py-2">
                                        {row.po_total.toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                        })}
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

ReportsSuppliers.layout = {
    breadcrumbs: [
        { title: 'Reports', href: pr() },
        { title: 'Suppliers', href: suppliers() },
    ],
};
