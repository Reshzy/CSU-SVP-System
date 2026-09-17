import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    ClipboardList,
    FolderGit2,
    Inbox,
    Layers,
    LayoutGrid,
    Library,
    Palette,
    UserCheck,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, uiKit } from '@/routes';
import { index as consolidatedAppIndex } from '@/routes/bac/app';
import { index as departmentRequestsIndex } from '@/routes/ceo/department-requests';
import { index as departmentsIndex } from '@/routes/ceo/departments';
import { index as usersIndex } from '@/routes/ceo/users';
import { index as bacQuotationsIndex } from '@/routes/bac/quotations';
import { index as budgetIndex } from '@/routes/budget';
import { index as budgetPurchaseRequestsIndex } from '@/routes/budget/purchase-requests';
import { index as ceoPurchaseRequestsIndex } from '@/routes/ceo/purchase-requests';
import { index as supplyPurchaseRequestsIndex } from '@/routes/supply/purchase-requests';
import { index as ppmpIndex } from '@/routes/ppmp';
import { index as purchaseRequestsIndex } from '@/routes/purchase-requests';
import { index as psDbmsIndex } from '@/routes/ps-dbms';
import type { NavItem, SvpRole } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Purchase requests',
        href: purchaseRequestsIndex(),
        icon: Inbox,
    },
    {
        title: 'PPMP',
        href: ppmpIndex(),
        icon: ClipboardList,
    },
    {
        title: 'UI Kit',
        href: uiKit(),
        icon: Palette,
    },
];

const supplyNavItems: NavItem[] = [
    {
        title: 'Supply queue',
        href: supplyPurchaseRequestsIndex(),
        icon: Inbox,
    },
];

const budgetNavItems: NavItem[] = [
    {
        title: 'Department budgets',
        href: budgetIndex(),
        icon: Building2,
    },
    {
        title: 'Earmark queue',
        href: budgetPurchaseRequestsIndex(),
        icon: ClipboardList,
    },
];

const bacNavItems: NavItem[] = [
    {
        title: 'BAC quotations',
        href: bacQuotationsIndex(),
        icon: Layers,
    },
];

const ceoPrNavItems: NavItem[] = [
    {
        title: 'CEO PR approvals',
        href: ceoPurchaseRequestsIndex(),
        icon: UserCheck,
    },
];

const planningNavItems: NavItem[] = [
    {
        title: 'PS-DBMS catalog',
        href: psDbmsIndex(),
        icon: Library,
    },
    {
        title: 'Consolidated APP',
        href: consolidatedAppIndex(),
        icon: Layers,
    },
];

/** Roles holding `manage-ps-dbms` and `view-consolidated-app`. */
const catalogRoles: SvpRole[] = [
    'BAC Secretariat',
    'System Admin',
    'Executive Officer',
];

const executiveOfficerNavItems: NavItem[] = [
    {
        title: 'User approvals',
        href: usersIndex(),
        icon: UserCheck,
    },
    {
        title: 'Departments',
        href: departmentsIndex(),
        icon: Building2,
    },
    {
        title: 'Department requests',
        href: departmentRequestsIndex(),
        icon: Inbox,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;

    // Display hints only; the routes themselves sit behind
    // `role:` and `can:` middleware on the server.
    const isExecutiveOfficer = auth.roles.includes('Executive Officer');
    const isSupplyOfficer = auth.roles.includes('Supply Officer');
    const isBudgetOffice = auth.roles.includes('Budget Office');
    const isBac = ['BAC Chair', 'BAC Members', 'BAC Secretariat'].some((role) =>
        auth.roles.includes(role as SvpRole),
    );
    const managesCatalog = catalogRoles.some((role) =>
        auth.roles.includes(role),
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />

                {isSupplyOfficer && (
                    <NavMain items={supplyNavItems} label="Supply" />
                )}

                {isBudgetOffice && (
                    <NavMain items={budgetNavItems} label="Budget" />
                )}

                {isBac && <NavMain items={bacNavItems} label="BAC" />}

                {managesCatalog && (
                    <NavMain items={planningNavItems} label="Planning" />
                )}

                {isExecutiveOfficer && (
                    <NavMain
                        items={[...ceoPrNavItems, ...executiveOfficerNavItems]}
                        label="Executive Officer"
                    />
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
