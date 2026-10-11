
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { hasPermission } from '@/lib/permissions';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Building2, Car, LayoutGrid, Users, UserCheck, Shield, Route, Briefcase, MapPin, Factory, UsersRound, CalendarClock, BarChart3, FileSpreadsheet, MessageSquareWarning, ClipboardCheck, History, Globe, ShieldAlert } from 'lucide-react';
import AppLogo from './app-logo';

// `permission` gates each item to match its target page's controller middleware
// (see e.g. RoleController/PermissionController/etc.) — omit it for pages open to
// any authenticated user (Dashboard).
const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Roles',
        href: '/roles',
        icon: Shield,
        permission: 'view-roles',
    },
    {
        title: 'Zones',
        href: '/zones',
        icon: Globe,
        permission: 'view-zones',
    },
    {
        title: 'Departments',
        href: '/departments',
        icon: Briefcase,
        permission: 'view-departments',
    },
    {
        title: 'Users',
        href: '/users',
        icon: Users,
        permission: 'view-users',
    },
    {
        title: 'User Groups',
        href: '/user-groups',
        icon: UsersRound,
        permission: 'view-user-groups',
    },


    {
        title: 'Factories',
        href: '/factories',
        icon: Factory,
        permission: 'view-factories',
    },

    {
        title: 'Logistics',
        href: '/logistics',
        icon: Briefcase,
        permission: 'view-logistics',
    },
      {
        title: 'Vendors',
        href: '/vendors',
        icon: Building2,
        permission: 'view-vendors',
    },
     {
        title: 'Drivers',
        href: '/drivers',
        icon: UserCheck,
        permission: 'view-drivers',
    },
    {
        title: 'Vehicles',
        href: '/vehicles',
        icon: Car,
        permission: 'view-vehicles',
    },
    {
        title: 'Routes',
        href: '/routes',
        icon: Route,
        permission: 'view-routes',
    },
    {
        title: 'Trips',
        href: '/trips',
        icon: MapPin,
        permission: ['view-trips', 'view-own-trips'],
    },
    {
        title: 'Passenger Events',
        href: '/trips/passenger-events',
        icon: CalendarClock,
        permission: 'view-trips',
    },
    {
        title: 'Attendance',
        href: '/attendance',
        icon: ClipboardCheck,
        permission: 'capture-own-attendance',
    },
    {
        title: 'Attendance Reports',
        href: '/attendance/reports',
        icon: ClipboardCheck,
        permission: 'view-attendance-reports',
    },
    {
        title: 'Feedback & Complaints',
        href: '/complaints',
        icon: MessageSquareWarning,
        permission: ['view-complaints', 'view-own-complaints'],
    },
    {
        title: 'Reports',
        href: '/reports',
        icon: BarChart3,
        permission: 'view-reports',
        children: [
            { title: 'Trip Report', href: '/reports', icon: BarChart3, permission: 'view-reports' },
            { title: 'Monthly Vehicle Report', href: '/reports/monthly-vehicle', icon: FileSpreadsheet, permission: 'view-reports' },
            { title: 'Complaints & SLA', href: '/reports/complaints', icon: MessageSquareWarning, permission: 'view-reports' },
            { title: 'Driver Performance', href: '/reports/driver-performance', icon: UserCheck, permission: 'view-reports' },
            { title: 'Compliance & Expiry', href: '/reports/compliance', icon: ShieldAlert, permission: 'view-reports' },
            { title: 'Department Usage', href: '/reports/department-usage', icon: Building2, permission: 'view-reports' },
        ],
    },
    {
        title: 'Activity Log',
        href: '/activity-logs',
        icon: History,
        permission: 'view-user-activity',
    },

];

// Keep an item if the user has its required permission (or it has none), or if
// at least one of its children remains visible after the same check.
function filterNavItemsByPermission(items: NavItem[], userPermissions: string[]): NavItem[] {
    return items.reduce<NavItem[]>((visible, item) => {
        const children = item.children ? filterNavItemsByPermission(item.children, userPermissions) : undefined;
        const isVisible = hasPermission(userPermissions, item.permission) || (children?.length ?? 0) > 0;

        if (isVisible) {
            visible.push(children ? { ...item, children } : item);
        }

        return visible;
    }, []);
}

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const visibleNavItems = filterNavItemsByPermission(mainNavItems, auth.permissions ?? []);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild tooltip={{ children: "RSC Vehicle Management System" }}>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={visibleNavItems} />
            </SidebarContent>

            <SidebarFooter>

                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
