
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { hasPermission } from '@/lib/permissions';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BarChart3, Boxes, Briefcase, Building2, CalendarClock, Car, ClipboardCheck, ClipboardList, Factory, FileSpreadsheet, Gauge, Globe, History, LayoutGrid, MapPin, MessageSquareWarning, PieChart, Route, Shield, ShieldAlert, UserCheck, Users, UsersRound } from 'lucide-react';
import AppLogo from './app-logo';

// `permission` gates each item to match its target page's controller middleware
// (see e.g. RoleController/PermissionController/etc.) — omit it for pages open to
// any authenticated user (Dashboard).
const navSections: { label: string; items: NavItem[] }[] = [
    {
        label: 'Overview',
        items: [{ title: 'Dashboard', href: '/dashboard', icon: LayoutGrid }],
    },
    {
        label: 'Operations',
        items: [
            { title: 'Trips', href: '/trips', icon: MapPin, permission: ['view-trips', 'view-own-trips'] },
            { title: 'Passenger Events', href: '/trips/passenger-events', icon: CalendarClock, permission: 'view-trips' },
            { title: 'Feedback & Complaints', href: '/complaints', icon: MessageSquareWarning, permission: ['view-complaints', 'view-own-complaints'] },
            { title: 'Attendance', href: '/attendance', icon: ClipboardCheck, permission: 'capture-own-attendance' },
            { title: 'Attendance Reports', href: '/attendance/reports', icon: ClipboardList, permission: 'view-attendance-reports' },
        ],
    },
    {
        label: 'Fleet',
        items: [
            { title: 'Vehicles', href: '/vehicles', icon: Car, permission: 'view-vehicles' },
            { title: 'Drivers', href: '/drivers', icon: UserCheck, permission: 'view-drivers' },
            { title: 'Vendors', href: '/vendors', icon: Building2, permission: 'view-vendors' },
            { title: 'Routes', href: '/routes', icon: Route, permission: 'view-routes' },
            { title: 'Factories', href: '/factories', icon: Factory, permission: 'view-factories' },
            { title: 'Logistics', href: '/logistics', icon: Boxes, permission: 'view-logistics' },
        ],
    },
    {
        label: 'Reports',
        items: [
            { title: 'Trip Report', href: '/reports', icon: BarChart3, permission: 'view-reports' },
            { title: 'Monthly Vehicle Report', href: '/reports/monthly-vehicle', icon: FileSpreadsheet, permission: 'view-reports' },
            { title: 'Complaints & SLA', href: '/reports/complaints', icon: MessageSquareWarning, permission: 'view-reports' },
            { title: 'Driver Performance', href: '/reports/driver-performance', icon: Gauge, permission: 'view-reports' },
            { title: 'Compliance & Expiry', href: '/reports/compliance', icon: ShieldAlert, permission: 'view-reports' },
            { title: 'Department Usage', href: '/reports/department-usage', icon: PieChart, permission: 'view-reports' },
        ],
    },
    {
        label: 'Administration',
        items: [
            { title: 'Users', href: '/users', icon: Users, permission: 'view-users' },
            { title: 'User Groups', href: '/user-groups', icon: UsersRound, permission: 'view-user-groups' },
            { title: 'Departments', href: '/departments', icon: Briefcase, permission: 'view-departments' },
            { title: 'Zones', href: '/zones', icon: Globe, permission: 'view-zones' },
            { title: 'Roles', href: '/roles', icon: Shield, permission: 'view-roles' },
            { title: 'Activity Log', href: '/activity-logs', icon: History, permission: 'view-user-activity' },
        ],
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

function activeNavHref(items: NavItem[], path: string): string | null {
    const hrefs = items.flatMap((item) => [item.href, ...(item.children ?? []).map((child) => child.href)]);

    return hrefs.filter((href) => path === href || path.startsWith(`${href}/`)).sort((a, b) => b.length - a.length)[0] ?? null;
}

export function AppSidebar() {
    const page = usePage<SharedData>();
    const { auth } = page.props;
    const visibleSections = navSections
        .map((section) => ({ ...section, items: filterNavItemsByPermission(section.items, auth.permissions ?? []) }))
        .filter((section) => section.items.length > 0);
    // Highlight only the closest match (so /trips isn't lit on /trips/passenger-events)
    const activeHref = activeNavHref(
        visibleSections.flatMap((section) => section.items),
        page.url.split('?')[0],
    );

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
                {visibleSections.map((section) => (
                    <NavMain key={section.label} label={section.label} items={section.items} activeHref={activeHref} />
                ))}
            </SidebarContent>

            <SidebarFooter>

                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
