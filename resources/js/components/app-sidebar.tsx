import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Building2,
    FolderGit2,
    Inbox,
    LayoutGrid,
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
import { index as departmentRequestsIndex } from '@/routes/ceo/department-requests';
import { index as departmentsIndex } from '@/routes/ceo/departments';
import { index as usersIndex } from '@/routes/ceo/users';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'UI Kit',
        href: uiKit(),
        icon: Palette,
    },
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

    // A display hint only; the routes themselves sit behind
    // `role:Executive Officer` middleware on the server.
    const isExecutiveOfficer = auth.roles.includes('Executive Officer');

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
