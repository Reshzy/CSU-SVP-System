import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { dashboard } from '@/routes';
import { index as accountingVouchersIndex } from '@/routes/accounting/vouchers';
import { index as bacQuotationsIndex } from '@/routes/bac/quotations';
import { index as budgetPurchaseRequestsIndex } from '@/routes/budget/purchase-requests';
import { index as ceoPurchaseRequestsIndex } from '@/routes/ceo/purchase-requests';
import { index as ceoUsersIndex } from '@/routes/ceo/users';
import {
    create as purchaseRequestsCreate,
    index as purchaseRequestsIndex,
} from '@/routes/purchase-requests';
import { analytics, pr as reportsPr } from '@/routes/reports';
import { index as supplyPurchaseOrdersIndex } from '@/routes/supply/purchase-orders';
import { index as supplyPurchaseRequestsIndex } from '@/routes/supply/purchase-requests';
import { index as supplySuppliersIndex } from '@/routes/supply/suppliers';
import type { SvpRole } from '@/types';

type QueueCard = {
    title: string;
    description: string;
    href: string;
    roles?: SvpRole[];
    can?: 'viewReports' | 'manageSuppliers';
};

const cards: QueueCard[] = [
    {
        title: 'Create a purchase request',
        description: 'Submit items from a validated PPMP. New PRs go to Supply Office.',
        href: purchaseRequestsCreate.url(),
        roles: ['Dean', 'End User', 'BAC Chair', 'BAC Members', 'BAC Secretariat'],
    },
    {
        title: 'My purchase requests',
        description: 'Track status, lots, and the activity timeline.',
        href: purchaseRequestsIndex.url(),
    },
    {
        title: 'Supply queue',
        description: 'Activate, return, or defer incoming purchase requests.',
        href: supplyPurchaseRequestsIndex.url(),
        roles: ['Supply Officer'],
    },
    {
        title: 'Purchase orders',
        description: 'Send, acknowledge, deliver, and complete POs.',
        href: supplyPurchaseOrdersIndex.url(),
        roles: ['Supply Officer'],
    },
    {
        title: 'Suppliers',
        description: 'Internal roster. Executive Officer activates pending records.',
        href: supplySuppliersIndex.url(),
        can: 'manageSuppliers',
    },
    {
        title: 'Budget earmark queue',
        description: 'Approve or defer after Supply activation.',
        href: budgetPurchaseRequestsIndex.url(),
        roles: ['Budget Office'],
    },
    {
        title: 'CEO approvals',
        description: 'Release PRs to BAC evaluation or defer.',
        href: ceoPurchaseRequestsIndex.url(),
        roles: ['Executive Officer'],
    },
    {
        title: 'User approvals',
        description: 'Review registrations and government IDs.',
        href: ceoUsersIndex.url(),
        roles: ['Executive Officer'],
    },
    {
        title: 'BAC quotations',
        description: 'RFQ, quotes, AOQ, and awards.',
        href: bacQuotationsIndex.url(),
        roles: ['BAC Chair', 'BAC Members', 'BAC Secretariat'],
    },
    {
        title: 'Disbursement vouchers',
        description: 'Accounting status chain after delivery.',
        href: accountingVouchersIndex.url(),
        roles: ['Accounting Office'],
    },
    {
        title: 'Reports',
        description: 'CSV exports and 12-month analytics.',
        href: reportsPr.url(),
        can: 'viewReports',
    },
    {
        title: 'Analytics',
        description: 'PR counts and cycle time for the last year.',
        href: analytics.url(),
        can: 'viewReports',
    },
];

export default function Dashboard() {
    const { auth } = usePage().props;
    const visible = cards.filter((card) => {
        if (card.can && auth.can?.[card.can]) {
            return true;
        }

        if (!card.roles && !card.can) {
            return true;
        }

        return (card.roles ?? []).some((role) => auth.roles.includes(role));
    });

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Work queues"
                    description={`Signed in as ${auth.primaryRole ?? 'staff'}. Open the next step, not a placeholder.`}
                />
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {visible.map((card) => (
                        <Link
                            key={card.title}
                            href={card.href}
                            className="hover:border-primary/40 rounded-xl border bg-card p-5 transition-colors"
                        >
                            <h2 className="font-medium">{card.title}</h2>
                            <p className="text-muted-foreground mt-2 text-sm leading-6">
                                {card.description}
                            </p>
                        </Link>
                    ))}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
