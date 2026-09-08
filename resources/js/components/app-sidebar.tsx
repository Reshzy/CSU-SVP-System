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
import { index as ppmpIndex } from '@/routes/ppmp';
import { index as psDbmsIndex } from '@/routes/ps-dbms';
import type { NavItem, SvpRole } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
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

                {managesCatalog && (
                    <NavMain items={planningNavItems} label="Planning" />
                )}

                {isExecutiveOfficer && (
                    <NavMain
                        items={executiveOfficerNavItems}
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
