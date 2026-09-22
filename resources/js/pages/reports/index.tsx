import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { BarChart3, Download, Filter, Route, TrendingUp, Users } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

interface ReportPageProps {
    report: {
        filters: {
            from: string;
            to: string;
            schedule_type: string | null;
            status?: string | null;
            driver_id?: number | null;
            vehicle_id?: number | null;
            route_id?: number | null;
            search?: string | null;
            per_page?: number;
        };
        summary: {
            total_trips: number;
            completed_trips: number;
            cancelled_trips: number;
            completion_rate: number;
            cancellation_rate: number;
            active_vehicles: number;
            total_passengers: number;
            completed_passengers: number;
            no_show_passengers: number;
        };
        charts: {
            daily_trips: Array<{
                date: string;
                total: number;
                completed: number;
            }>;
            trips_by_status: Array<{
                status: string;
                value: number;
            }>;
            trips_by_schedule_type: Array<{
                schedule_type: string;
                value: number;
            }>;
        };
        top_routes: Array<{
            route_name: string;
            trip_count: number;
        }>;
        trip_histories: {
            data: Array<{
                id: number;
                trip_number: string;
                scheduled_date: string;
                scheduled_start_time: string;
                scheduled_end_time: string;
                status: string;
                schedule_type: string;
                start_location: string | null;
                end_location: string | null;
                vehicleRoute?: { id: number; name: string } | null;
                driver?: { id: number; name: string } | null;
                vehicle?: { id: number; registration_number: string } | null;
                department?: { id: number; name: string } | null;
            }>;
            current_page: number;
            last_page: number;
            total: number;
        };
        filter_options: {
            statuses: string[];
            drivers: Array<{ id: number; name: string }>;
            vehicles: Array<{ id: number; registration_number: string }>;
            routes: Array<{ id: number; name: string }>;
        };
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/reports' },
];

const STATUS_COLORS = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2'];

export default function ReportsIndex({ report }: ReportPageProps) {
    const [filters, setFilters] = useState({
        from: report.filters.from,
        to: report.filters.to,
        schedule_type: report.filters.schedule_type ?? 'all',
        status: report.filters.status ?? 'all',
        driver_id: report.filters.driver_id ? String(report.filters.driver_id) : 'all',
        vehicle_id: report.filters.vehicle_id ? String(report.filters.vehicle_id) : 'all',
        route_id: report.filters.route_id ? String(report.filters.route_id) : 'all',
        search: report.filters.search ?? '',
        per_page: report.filters.per_page ?? 15,
        export_format: 'csv' as 'csv' | 'excel' | 'pdf',
    });

    const getStatusClass = (status: string) => {
        const classes: Record<string, string> = {
            pending: 'border-yellow-200 bg-yellow-100 text-yellow-800',
            approved: 'border-blue-200 bg-blue-100 text-blue-800',
            rejected: 'border-red-200 bg-red-100 text-red-800',
            assigned: 'border-cyan-200 bg-cyan-100 text-cyan-800',
            in_progress: 'border-violet-200 bg-violet-100 text-violet-800',
            completed: 'border-green-200 bg-green-100 text-green-800',
            cancelled: 'border-slate-200 bg-slate-100 text-slate-800',
        };

        return classes[status] ?? classes.pending;
    };

    const buildQuery = (page?: number) => ({
        from: filters.from,
        to: filters.to,
        schedule_type: filters.schedule_type === 'all' ? undefined : filters.schedule_type,
        status: filters.status === 'all' ? undefined : filters.status,
        driver_id: filters.driver_id === 'all' ? undefined : filters.driver_id,
        vehicle_id: filters.vehicle_id === 'all' ? undefined : filters.vehicle_id,
        route_id: filters.route_id === 'all' ? undefined : filters.route_id,
        search: filters.search || undefined,
        per_page: filters.per_page,
        page,
    });

    const handleApplyFilters = () => {
        router.get(route('reports.index'), buildQuery(), { preserveState: true, preserveScroll: true });
    };

    const handleExport = () => {
        const params = new URLSearchParams();

        Object.entries(buildQuery()).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                params.set(key, String(value));
            }
        });

        params.set('format', filters.export_format);

        window.location.href = `${route('reports.export')}?${params.toString()}`;
    };

    const statusChartData = useMemo(
        () => report.charts.trips_by_status.map((item) => ({ ...item, name: item.status.replace('_', ' ') })),
        [report.charts.trips_by_status],
    );

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />

            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-gradient-to-r from-slate-50 via-white to-emerald-50 p-6 md:flex-row md:items-center md:justify-between dark:border-slate-800 dark:from-slate-950 dark:via-slate-900 dark:to-emerald-950/40">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Reports Module</h1>
                        <p className="text-sm text-muted-foreground">
                            Analyze trip operations, passenger movement, and route efficiency.
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-base">
                            <Filter className="h-4 w-4" />
                            Report Filters
                        </CardTitle>
                        <CardDescription>Filter report metrics by date range and schedule type.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
                            <Input
                                type="date"
                                value={filters.from}
                                onChange={(event) => setFilters((prev) => ({ ...prev, from: event.target.value }))}
                            />
                            <Input
                                type="date"
                                value={filters.to}
                                onChange={(event) => setFilters((prev) => ({ ...prev, to: event.target.value }))}
                            />
                            <Select
                                value={filters.schedule_type}
                                onValueChange={(value) => setFilters((prev) => ({ ...prev, schedule_type: value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Schedule Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Schedule Types</SelectItem>
                                    <SelectItem value="pick-and-drop">Pick & Drop</SelectItem>
                                    <SelectItem value="pick-up">Pick-up</SelectItem>
                                    <SelectItem value="drop-off">Drop-off</SelectItem>
                                    <SelectItem value="engineer">Engineer</SelectItem>
                                    <SelectItem value="training">Training</SelectItem>
                                    <SelectItem value="adhoc">Adhoc</SelectItem>
                                    <SelectItem value="reposition">Reposition</SelectItem>
                                    <SelectItem value="inspection">Inspection</SelectItem>
                                    <SelectItem value="complaints">Complaints</SelectItem>
                                    <SelectItem value="CVV">CVV</SelectItem>
                                    <SelectItem value="Incident Inspection">Incident Inspection</SelectItem>
                                    <SelectItem value="officials">Officials</SelectItem>
                                    <SelectItem value="Assigned">Assigned</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.status}
                                onValueChange={(value) => setFilters((prev) => ({ ...prev, status: value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Statuses</SelectItem>
                                    {report.filter_options.statuses.map((status) => (
                                        <SelectItem key={status} value={status}>
                                            {status.replace('_', ' ')}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.driver_id}
                                onValueChange={(value) => setFilters((prev) => ({ ...prev, driver_id: value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Driver" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Drivers</SelectItem>
                                    {report.filter_options.drivers.map((driver) => (
                                        <SelectItem key={driver.id} value={String(driver.id)}>
                                            {driver.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.route_id}
                                onValueChange={(value) => setFilters((prev) => ({ ...prev, route_id: value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Route" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Routes</SelectItem>
                                    {report.filter_options.routes.map((routeItem) => (
                                        <SelectItem key={routeItem.id} value={String(routeItem.id)}>
                                            {routeItem.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.vehicle_id}
                                onValueChange={(value) => setFilters((prev) => ({ ...prev, vehicle_id: value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Vehicle" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Vehicles</SelectItem>
                                    {report.filter_options.vehicles.map((vehicle) => (
                                        <SelectItem key={vehicle.id} value={String(vehicle.id)}>
                                            {vehicle.registration_number}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Input
                                placeholder="Search trip number, location, driver, vehicle"
                                value={filters.search}
                                onChange={(event) => setFilters((prev) => ({ ...prev, search: event.target.value }))}
                            />
                            <Select
                                value={filters.export_format}
                                onValueChange={(value: 'csv' | 'excel' | 'pdf') =>
                                    setFilters((prev) => ({ ...prev, export_format: value }))
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Export Format" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="csv">CSV</SelectItem>
                                    <SelectItem value="excel">Excel</SelectItem>
                                    <SelectItem value="pdf">PDF</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select
                                value={String(filters.per_page)}
                                onValueChange={(value) => setFilters((prev) => ({ ...prev, per_page: Number(value) }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Rows" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="15">15 rows</SelectItem>
                                    <SelectItem value="25">25 rows</SelectItem>
                                    <SelectItem value="50">50 rows</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button onClick={handleApplyFilters} className="gap-2">
                                <Filter className="h-4 w-4" />
                                Apply Filters
                            </Button>
                            <Button onClick={handleExport} variant="outline" className="gap-2">
                                <Download className="h-4 w-4" />
                                Export
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardDescription>Total Trips</CardDescription>
                            <CardTitle className="text-2xl">{report.summary.total_trips}</CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground">
                            Completed: {report.summary.completed_trips} ({report.summary.completion_rate}%)
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-3">
                            <CardDescription>Passengers</CardDescription>
                            <CardTitle className="text-2xl">{report.summary.total_passengers}</CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground">
                            Completed: {report.summary.completed_passengers}, No-show: {report.summary.no_show_passengers}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-3">
                            <CardDescription>Active Vehicles</CardDescription>
                            <CardTitle className="text-2xl">{report.summary.active_vehicles}</CardTitle>
                        </CardHeader>
                        <CardContent className="text-xs text-muted-foreground">
                            Cancellation Rate: {report.summary.cancellation_rate}%
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <TrendingUp className="h-4 w-4" />
                                Daily Trips Trend
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="h-72">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={report.charts.daily_trips}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="date" tick={{ fontSize: 12 }} />
                                    <YAxis tick={{ fontSize: 12 }} />
                                    <Tooltip />
                                    <Line type="monotone" dataKey="total" stroke="#2563eb" strokeWidth={2} />
                                    <Line type="monotone" dataKey="completed" stroke="#16a34a" strokeWidth={2} />
                                </LineChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <BarChart3 className="h-4 w-4" />
                                Trips By Status
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="h-72">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie data={statusChartData} dataKey="value" nameKey="name" outerRadius={95} label>
                                        {statusChartData.map((_, index) => (
                                            <Cell key={index} fill={STATUS_COLORS[index % STATUS_COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip />
                                </PieChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Route className="h-4 w-4" />
                                Top Routes
                            </CardTitle>
                            <CardDescription>Routes with the most trips in selected period.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {report.top_routes.length === 0 && (
                                    <p className="text-sm text-muted-foreground">No route data for the selected filters.</p>
                                )}
                                {report.top_routes.map((routeItem) => (
                                    <div
                                        key={routeItem.route_name}
                                        className="flex items-center justify-between rounded-md border border-slate-200 p-3 text-sm dark:border-slate-800"
                                    >
                                        <div>
                                            <p className="font-medium">{routeItem.route_name}</p>
                                            <p className="text-xs text-muted-foreground">{routeItem.trip_count} trips</p>
                                        </div>
                                        <span className="font-medium">{routeItem.trip_count}</span>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Users className="h-4 w-4" />
                                Trips By Schedule Type
                            </CardTitle>
                            <CardDescription>Distribution of schedule categories.</CardDescription>
                        </CardHeader>
                        <CardContent className="h-80">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={report.charts.trips_by_schedule_type}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="schedule_type" tick={{ fontSize: 12 }} />
                                    <YAxis tick={{ fontSize: 12 }} />
                                    <Tooltip />
                                    <Bar dataKey="value" fill="#0f766e" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Analysis Notes</CardTitle>
                        <CardDescription>Quick interpretation for the selected date range.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm text-muted-foreground">
                        <p>
                            Completion performance is <span className="font-medium text-foreground">{report.summary.completion_rate}%</span> and
                            cancellation is <span className="font-medium text-foreground">{report.summary.cancellation_rate}%</span>.
                        </p>
                        <p>
                            Total trips analyzed: <span className="font-medium text-foreground">{report.summary.total_trips}</span>
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Trip History</CardTitle>
                        <CardDescription>
                            {report.trip_histories.total} trips found with current filters.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto rounded-md border">
                            <table className="w-full min-w-[980px] text-sm">
                                <thead className="bg-muted/60">
                                    <tr className="text-left">
                                        <th className="px-3 py-2 font-medium">Trip</th>
                                        <th className="px-3 py-2 font-medium">Date & Time</th>
                                        <th className="px-3 py-2 font-medium">Route</th>
                                        <th className="px-3 py-2 font-medium">Driver</th>
                                        <th className="px-3 py-2 font-medium">Vehicle</th>
                                        <th className="px-3 py-2 font-medium">Status</th>
                                        <th className="px-3 py-2 font-medium">Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {report.trip_histories.data.length === 0 && (
                                        <tr>
                                            <td colSpan={7} className="px-3 py-8 text-center text-muted-foreground">
                                                No trips found for the selected filters.
                                            </td>
                                        </tr>
                                    )}
                                    {report.trip_histories.data.map((trip) => (
                                        <tr key={trip.id} className="border-t">
                                            <td className="px-3 py-2">
                                                <p className="font-medium">{trip.trip_number}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {trip.start_location || '-'} to {trip.end_location || '-'}
                                                </p>
                                            </td>
                                            <td className="px-3 py-2">
                                                <p>{trip.scheduled_date}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {trip.scheduled_start_time} - {trip.scheduled_end_time}
                                                </p>
                                            </td>
                                            <td className="px-3 py-2">{trip.vehicleRoute?.name || '-'}</td>
                                            <td className="px-3 py-2">{trip.driver?.name || '-'}</td>
                                            <td className="px-3 py-2">{trip.vehicle?.registration_number || '-'}</td>
                                            <td className="px-3 py-2">
                                                <Badge className={getStatusClass(trip.status)}>
                                                    {trip.status.replace('_', ' ')}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2">{trip.schedule_type}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="mt-4 flex items-center justify-between">
                            <p className="text-sm text-muted-foreground">
                                Page {report.trip_histories.current_page} of {report.trip_histories.last_page}
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={report.trip_histories.current_page <= 1}
                                    onClick={() =>
                                        router.get(route('reports.index'), buildQuery(report.trip_histories.current_page - 1), {
                                            preserveState: true,
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    Previous
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={report.trip_histories.current_page >= report.trip_histories.last_page}
                                    onClick={() =>
                                        router.get(route('reports.index'), buildQuery(report.trip_histories.current_page + 1), {
                                            preserveState: true,
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppSidebarLayout>
    );
}
