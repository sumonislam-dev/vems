import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { AlertTriangle, CalendarClock, Filter, Truck, Users } from 'lucide-react';
import { useState } from 'react';

interface AttendanceReportRow {
    id: number;
    work_date: string;
    status: string;
    check_in_at: string | null;
    check_out_at: string | null;
    break_minutes: number;
    net_minutes: number | null;
    overtime_minutes: number;
    source: string | null;
    used_transport: boolean;
    has_anomaly: boolean;
    user: { id: number; name: string; employee_id: string | null; department?: { name: string } | null } | null;
    trip_passenger_event: { trip: { id: number; trip_number: string } | null } | null;
    events: Array<{ factory: { id: number; name: string } | null; location_name: string | null }>;
}

interface PaginatedRecords {
    data: AttendanceReportRow[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface ReportsPageProps {
    records: PaginatedRecords;
    stats: {
        total: number;
        checked_in_today: number;
        anomalies: number;
        used_transport: number;
    };
    queryParams: {
        date_from?: string;
        date_to?: string;
        source?: string;
        used_transport?: string;
        has_anomaly?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Attendance Reports', href: '/attendance/reports' },
];

function formatDate(value: string) {
    return new Date(value).toLocaleDateString();
}

function formatTime(value: string | null) {
    if (!value) return '—';
    return new Date(value).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

function formatDuration(minutes: number | null) {
    if (minutes === null) return '—';
    return `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
}

export default function AttendanceReports({ records, stats, queryParams }: ReportsPageProps) {
    const [filters, setFilters] = useState({
        date_from: queryParams.date_from ?? '',
        date_to: queryParams.date_to ?? '',
        source: queryParams.source ?? '',
        used_transport: queryParams.used_transport ?? '',
        has_anomaly: queryParams.has_anomaly ?? '',
    });
    const [filtersOpen, setFiltersOpen] = useState(Object.values(filters).some(Boolean));
    const hasActive = Object.values(filters).some(Boolean);

    function apply() {
        const params: Record<string, string> = {};
        Object.entries(filters).forEach(([k, v]) => {
            if (v) params[k] = v;
        });
        router.get('/attendance/reports', params, { preserveState: true });
    }

    function reset() {
        const empty = { date_from: '', date_to: '', source: '', used_transport: '', has_anomaly: '' };
        setFilters(empty);
        router.get('/attendance/reports', {}, { preserveState: false });
    }

    function goToPage(page: number) {
        const params: Record<string, string> = {};
        Object.entries(filters).forEach(([k, v]) => {
            if (v) params[k] = v;
        });
        params.page = String(page);
        router.get('/attendance/reports', params, { preserveState: true });
    }

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Attendance Reports" />
            <div className="flex flex-col gap-6 p-6">
                <PageHeader title="Attendance Reports" description="Cross-employee check-in/check-out history" />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {[
                        { label: 'Total Records', value: stats.total, icon: CalendarClock },
                        { label: 'Checked In Today', value: stats.checked_in_today, icon: Users },
                        { label: 'Anomalies', value: stats.anomalies, icon: AlertTriangle },
                        { label: 'Used Transport', value: stats.used_transport, icon: Truck },
                    ].map(({ label, value, icon: Icon }) => (
                        <Card key={label}>
                            <CardContent className="p-4">
                                <div className="flex items-center gap-2">
                                    <Icon className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm text-muted-foreground">{label}</span>
                                </div>
                                <p className="mt-1 text-2xl font-bold">{value}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader className="pb-2">
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                <Filter className="h-4 w-4" />
                                Filters
                                {hasActive && (
                                    <Badge variant="secondary" className="ml-1">
                                        {Object.values(filters).filter(Boolean).length}
                                    </Badge>
                                )}
                            </CardTitle>
                            <div className="flex gap-2">
                                {hasActive && (
                                    <Button variant="ghost" size="sm" onClick={reset}>
                                        Clear
                                    </Button>
                                )}
                                <Button variant="ghost" size="sm" onClick={() => setFiltersOpen((o) => !o)}>
                                    {filtersOpen ? 'Hide' : 'Show'}
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    {filtersOpen && (
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs">Date From</Label>
                                    <Input
                                        type="date"
                                        value={filters.date_from}
                                        onChange={(e) => setFilters((f) => ({ ...f, date_from: e.target.value }))}
                                    />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs">Date To</Label>
                                    <Input
                                        type="date"
                                        value={filters.date_to}
                                        onChange={(e) => setFilters((f) => ({ ...f, date_to: e.target.value }))}
                                    />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs">Source</Label>
                                    <Select value={filters.source} onValueChange={(v) => setFilters((f) => ({ ...f, source: v === '_all' ? '' : v }))}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="All sources" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="_all">All sources</SelectItem>
                                            <SelectItem value="self_service">Self Service</SelectItem>
                                            <SelectItem value="biometric_device">Biometric Device</SelectItem>
                                            <SelectItem value="manual">Manual</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs">Used Transport</Label>
                                    <Select
                                        value={filters.used_transport}
                                        onValueChange={(v) => setFilters((f) => ({ ...f, used_transport: v === '_all' ? '' : v }))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="_all">All</SelectItem>
                                            <SelectItem value="yes">Yes</SelectItem>
                                            <SelectItem value="no">No</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs">Anomaly</Label>
                                    <Select
                                        value={filters.has_anomaly}
                                        onValueChange={(v) => setFilters((f) => ({ ...f, has_anomaly: v === '_all' ? '' : v }))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="_all">All</SelectItem>
                                            <SelectItem value="yes">Yes</SelectItem>
                                            <SelectItem value="no">No</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <div className="mt-4 flex justify-end">
                                <Button onClick={apply}>Apply Filters</Button>
                            </div>
                        </CardContent>
                    )}
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-semibold">
                            Records
                            <span className="ml-2 text-muted-foreground font-normal">({records.total} total)</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/50">
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Employee</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Date</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Check In</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Check Out</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Net</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Overtime</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Source</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Trip</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Checkout Location</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Anomaly</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {records.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={10} className="px-4 py-12 text-center text-muted-foreground">
                                                No attendance records found.
                                            </td>
                                        </tr>
                                    ) : (
                                        records.data.map((record) => {
                                            const checkoutEvent = record.events?.[0];
                                            return (
                                                <tr key={record.id} className="border-b hover:bg-muted/30 transition-colors">
                                                    <td className="px-4 py-3">
                                                        <p className="font-medium">{record.user?.name ?? '—'}</p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {record.user?.employee_id} {record.user?.department?.name && `· ${record.user.department.name}`}
                                                        </p>
                                                    </td>
                                                    <td className="px-4 py-3 whitespace-nowrap">{formatDate(record.work_date)}</td>
                                                    <td className="px-4 py-3">{formatTime(record.check_in_at)}</td>
                                                    <td className="px-4 py-3">{formatTime(record.check_out_at)}</td>
                                                    <td className="px-4 py-3">{formatDuration(record.net_minutes)}</td>
                                                    <td className="px-4 py-3">{record.overtime_minutes}m</td>
                                                    <td className="px-4 py-3 text-xs capitalize">{record.source ?? '—'}</td>
                                                    <td className="px-4 py-3 text-xs">
                                                        {record.trip_passenger_event?.trip?.trip_number ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3 text-xs">
                                                        {checkoutEvent?.factory?.name ?? checkoutEvent?.location_name ?? '—'}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        {record.has_anomaly ? (
                                                            <Badge variant="destructive" className="text-xs">
                                                                Anomaly
                                                            </Badge>
                                                        ) : (
                                                            <span className="text-muted-foreground">—</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {records.last_page > 1 && (
                            <div className="flex items-center justify-between border-t px-4 py-3">
                                <p className="text-xs text-muted-foreground">
                                    Showing {records.from}–{records.to} of {records.total}
                                </p>
                                <div className="flex gap-1">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={records.current_page === 1}
                                        onClick={() => goToPage(records.current_page - 1)}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={records.current_page === records.last_page}
                                        onClick={() => goToPage(records.current_page + 1)}
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppSidebarLayout>
    );
}
