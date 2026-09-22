import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, DataTableColumn, Trip } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Calendar, Car, CheckCircle, Clock, Edit, Eye, FileText, MapPin, Plus, TrendingUp, User } from 'lucide-react';
import { useMemo } from 'react';

interface PaginatedTrips {
    data: Trip[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface TripsPageProps {
    trips: PaginatedTrips;
    stats: {
        total: number;
        pending: number;
        approved: number;
        in_progress: number;
        completed: number;
        today: number;
    };
    queryParams: {
        search?: string;
        status?: string;
        schedule_type?: string;
        trip_type?: string;
        priority?: string;
        date_from?: string;
        date_to?: string;
        vehicle_id?: number;
        department_id?: number;
        sort?: string;
        direction?: 'asc' | 'desc';
        per_page?: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Trips', href: '/trips' },
];

const getStatusBadge = (status: Trip['status']) => {
    const config = {
        pending: { label: 'Pending', className: 'border-yellow-200 bg-yellow-100 text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-500/15 dark:text-yellow-300' },
        approved: { label: 'Approved', className: 'border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/15 dark:text-blue-300' },
        rejected: { label: 'Rejected', className: 'border-red-200 bg-red-100 text-red-800 dark:border-red-500/30 dark:bg-red-500/15 dark:text-red-300' },
        assigned: { label: 'Assigned', className: 'border-cyan-200 bg-cyan-100 text-cyan-800 dark:border-cyan-500/30 dark:bg-cyan-500/15 dark:text-cyan-300' },
        in_progress: { label: 'In Progress', className: 'border-violet-200 bg-violet-100 text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/15 dark:text-violet-300' },
        completed: { label: 'Completed', className: 'border-green-200 bg-green-100 text-green-800 dark:border-green-500/30 dark:bg-green-500/15 dark:text-green-300' },
        cancelled: { label: 'Cancelled', className: 'border-slate-200 bg-slate-100 text-slate-800 dark:border-slate-500/30 dark:bg-slate-500/15 dark:text-slate-300' },
    };
    const { label, className } = config[status] || config.pending;
    return <Badge className={className}>{label}</Badge>;
};

const getPriorityBadge = (priority: Trip['priority']) => {
    const config = {
        urgent: { label: 'Urgent', className: 'border-red-300 bg-red-100 text-red-800 dark:border-red-500/40 dark:bg-red-500/20 dark:text-red-300' },
        high: { label: 'High', className: 'border-orange-200 bg-orange-100 text-orange-800 dark:border-orange-500/30 dark:bg-orange-500/15 dark:text-orange-300' },
        medium: { label: 'Medium', className: 'border-yellow-200 bg-yellow-100 text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-500/15 dark:text-yellow-300' },
        low: { label: 'Low', className: 'border-slate-200 bg-slate-100 text-slate-800 dark:border-slate-500/30 dark:bg-slate-500/15 dark:text-slate-300' },
    };
    const { label, className } = config[priority] || config.medium;
    return <Badge variant="outline" className={className}>{label}</Badge>;
};

export default function TripsIndex({ trips, stats, queryParams }: TripsPageProps) {
    const columns: DataTableColumn<Trip>[] = useMemo(() => [
        {
            key: 'trip_number',
            label: 'Trip Number',
            sortable: true,
            render: (value) => (
                <span className="font-mono text-xs font-semibold text-blue-600">{value}</span>
            ),
        },
        {
            key: 'scheduled_date',
            label: 'Schedule',
            sortable: true,
            render: (_, row) => (
                <div className="flex flex-col space-y-1">
                    <div className="flex items-center text-sm font-medium">
                        <Calendar className="mr-1.5 h-3.5 w-3.5 text-gray-500" />
                        {formatDate(row.scheduled_date)}
                    </div>
                    <div className="flex items-center text-xs text-gray-500">
                        <Clock className="mr-1.5 h-3 w-3" />
                        {row.scheduled_start_time} - {row.scheduled_end_time}
                    </div>
                </div>
            ),
        },
        {
            key: 'trip_type',
            label: 'Type',
            render: (value, row) => (
                <div className="flex flex-col space-y-1">
                    {value && (
                        <Badge variant="outline" className="text-xs font-medium capitalize">
                            {value.replace('-', ' ')}
                        </Badge>
                    )}
                    <span className="text-xs text-gray-500 capitalize">
                        {row.schedule_type.replace('-', ' ')}
                    </span>
                </div>
            ),
        },
        {
            key: 'purpose',
            label: 'Purpose & Details',
            sortable: true,
            render: (value, row) => (
                <div className="max-w-xs">
                    <p className="text-sm font-medium line-clamp-1">{value}</p>
                    {row.start_location && row.end_location && (
                        <div className="mt-1 flex items-center text-xs text-gray-500">
                            <MapPin className="mr-1 h-3 w-3" />
                            <span className="line-clamp-1">
                                {row.start_location} → {row.end_location}
                            </span>
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'vehicle_id',
            label: 'Vehicle',
            render: (_, row) =>
                row.vehicle ? (
                    <div className="flex items-center space-x-2">
                        <Car className="h-4 w-4 text-gray-400" />
                        <div className="flex flex-col">
                            <span className="text-sm font-medium">{row.vehicle.registration_number}</span>
                            <span className="text-xs text-gray-500">
                                {row.vehicle.brand} {row.vehicle.model}
                            </span>
                        </div>
                    </div>
                ) : (
                    <span className="text-sm text-gray-400 italic">Not assigned</span>
                ),
        },
        {
            key: 'driver_id',
            label: 'Driver',
            render: (_, row) =>
                row.driver ? (
                    <div className="flex items-center space-x-2">
                        <User className="h-3.5 w-3.5 text-gray-400" />
                        <span className="text-sm">{row.driver.name}</span>
                    </div>
                ) : (
                    <span className="text-sm text-gray-400 italic">Not assigned</span>
                ),
        },
        {
            key: 'department_id',
            label: 'Department',
            render: (_, row) =>
                row.department ? (
                    <span className="text-sm">{row.department.name}</span>
                ) : (
                    <span className="text-sm text-gray-400">-</span>
                ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (value) => getStatusBadge(value as Trip['status']),
        },
        {
            key: 'priority',
            label: 'Priority',
            render: (value) => getPriorityBadge(value as Trip['priority']),
        },
        {
            key: 'id',
            label: 'Actions',
            render: (_, row) => (
                <div className="flex items-center space-x-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => router.visit(route('trips.show', row.id))}
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => router.visit(route('trips.edit', row.id))}
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ], []);

    const filters = useMemo(() => [
        {
            key: 'status',
            label: 'Status',
            options: [
                { label: 'All', value: '' },
                { label: 'Pending', value: 'pending' },
                { label: 'Approved', value: 'approved' },
                { label: 'In Progress', value: 'in_progress' },
                { label: 'Completed', value: 'completed' },
                { label: 'Cancelled', value: 'cancelled' },
            ],
            type: 'select' as const,
        },
        {
            key: 'priority',
            label: 'Priority',
            options: [
                { label: 'All', value: '' },
                { label: 'Urgent', value: 'urgent' },
                { label: 'High', value: 'high' },
                { label: 'Medium', value: 'medium' },
                { label: 'Low', value: 'low' },
            ],
            type: 'select' as const,
        },
        {
            key: 'schedule_type',
            label: 'Schedule Type',
            options: [
                { label: 'All', value: '' },
                { label: 'Pick & Drop', value: 'pick-and-drop' },
                { label: 'Engineer', value: 'engineer' },
                { label: 'Training', value: 'training' },
                { label: 'Ad-hoc', value: 'adhoc' },
                { label: 'Reposition', value: 'reposition' },
            ],
            type: 'select' as const,
        },
        {
            key: 'trip_type',
            label: 'Trip Type',
            options: [
                { label: 'All', value: '' },
                { label: 'Inspection', value: 'inspection' },
                { label: 'Pick-up', value: 'pick-up' },
                { label: 'Drop-off', value: 'drop-off' },
                { label: 'Training', value: 'training' },
                { label: 'Complaints', value: 'complaints' },
                { label: 'CVV', value: 'CVV' },
                { label: 'Incident Inspection', value: 'Incident Inspection' },
                { label: 'Officials', value: 'officials' },
                { label: 'Assigned', value: 'Assigned' },
            ],
            type: 'select' as const,
        },
    ], []);

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Trips" />

            <div className="space-y-6">
                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">Total</p>
                                <p className="text-2xl font-bold">{stats.total}</p>
                            </div>
                            <FileText className="h-8 w-8 text-gray-400" />
                        </div>
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">Today</p>
                                <p className="text-2xl font-bold text-blue-600">{stats.today}</p>
                            </div>
                            <Calendar className="h-8 w-8 text-blue-400" />
                        </div>
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">Pending</p>
                                <p className="text-2xl font-bold text-yellow-600">{stats.pending}</p>
                            </div>
                            <Clock className="h-8 w-8 text-yellow-400" />
                        </div>
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">Approved</p>
                                <p className="text-2xl font-bold text-blue-600">{stats.approved}</p>
                            </div>
                            <CheckCircle className="h-8 w-8 text-blue-400" />
                        </div>
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">In Progress</p>
                                <p className="text-2xl font-bold text-purple-600">{stats.in_progress}</p>
                            </div>
                            <TrendingUp className="h-8 w-8 text-purple-400" />
                        </div>
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">Completed</p>
                                <p className="text-2xl font-bold text-green-600">{stats.completed}</p>
                            </div>
                            <CheckCircle className="h-8 w-8 text-green-400" />
                        </div>
                    </Card>
                </div>

                {/* Data Table */}
                <PageHeader
                    title="Trip Management"
                    description="Manage and track all vehicle trips"
                    actions={[
                        {
                            label: 'Create Trip',
                            icon: <Plus className="h-4 w-4" />,
                            href: route('trips.create'),
                        },
                    ]}
                />

                <ServerSideDataTable
                    data={trips}
                    columns={columns}
                    queryParams={queryParams}
                    filterOptions={{}}
                    filters={filters}
                    searchPlaceholder="Search by trip number, purpose, location..."
                    exportable={false}
                    emptyMessage="No trips found. Create your first trip to get started."
                />
            </div>
        </AppSidebarLayout>
    );
}
