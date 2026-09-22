import { Head, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { EmployeeDashboard, type EmployeeDashboardProps } from '@/components/employee-dashboard';
import { ManagementDashboard, type ManagementDashboardProps } from '@/components/management-dashboard';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

type DashboardProps =
    | ({ variant: 'employee' } & EmployeeDashboardProps & Record<string, unknown>)
    | ({ variant: 'management' } & ManagementDashboardProps & Record<string, unknown>);

export default function Dashboard() {
    const props = usePage<DashboardProps>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            {props.variant === 'employee' ? <EmployeeDashboard {...props} /> : <ManagementDashboard {...props} />}
        </AppLayout>
    );
}
