import { ExportButton } from '@/base-components/base-export-button';
import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { FormMultiSelect } from '@/base-components/base-form';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { hasPermission } from '@/lib/permissions';
import { cn } from '@/lib/utils';
import { BreadcrumbItem, SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, CalendarClock, Eye, Filter, Pencil, SlidersHorizontal, Truck, Users } from 'lucide-react';
import { Fragment, ReactNode, useEffect, useMemo, useState } from 'react';

interface AttendanceEventRow {
    id: number;
    event_type: 'check_in' | 'break_start' | 'break_end' | 'check_out' | 'correction';
    event_time: string;
    latitude: number | string | null;
    longitude: number | string | null;
    location_name: string | null;
    factory: { id: number; name: string; address: string | null } | null;
}

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
    trip_passenger_event: {
        trip: { id: number; trip_number: string; trip_type: string | null; driver: { id: number; name: string } | null } | null;
    } | null;
    events: AttendanceEventRow[];
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
    users: Array<{ id: number; name: string; employee_id: string | null }>;
    queryParams: {
        date_from?: string;
        date_to?: string;
        user_id?: string;
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

function formatLocation(event: AttendanceEventRow | undefined | null) {
    if (!event) return '—';
    if (event.factory?.name) return event.factory.name;
    if (event.location_name) return event.location_name;
    if (event.latitude != null && event.longitude != null) {
        const lat = Number(event.latitude);
        const lng = Number(event.longitude);
        if (!Number.isNaN(lat) && !Number.isNaN(lng)) return `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    }
    return '—';
}

function eventsOfType(events: AttendanceEventRow[], type: AttendanceEventRow['event_type']) {
    return events.filter((e) => e.event_type === type);
}

const EVENT_TYPE_LABELS: Record<AttendanceEventRow['event_type'], string> = {
    check_in: 'Check In',
    break_start: 'Break Start',
    break_end: 'Break End',
    check_out: 'Check Out',
    correction: 'Correction',
};

function toDateTimeLocal(value: string) {
    const date = new Date(value);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

// There's no dedicated "breaks" table — a break is just a break_start/break_end
// pair of rows on attendance_events, matched up by position within the day.
function breakEventAt(record: AttendanceReportRow, index: number, edge: 'start' | 'end') {
    const events = record.events ?? [];
    const list = eventsOfType(events, edge === 'start' ? 'break_start' : 'break_end');
    return list[index];
}

const ALL_COLUMN_KEYS = [
    'employee',
    'date',
    'check_in_time',
    'check_in_location',
    'check_out_time',
    'check_out_location',
    'factory_name',
    'factory_address',
    'work_hour',
    'ot_hour',
    'trip',
    'driver_name',
    'inspection_type',
    'total_break_time',
    'break1_start_time',
    'break1_start_location',
    'break1_end_time',
    'break2_start_time',
    'break2_start_location',
    'break2_end_time',
    'break3_start_time',
    'break3_start_location',
    'break3_end_time',
    'source',
    'anomaly',
    'actions',
] as const;

type ColumnKey = (typeof ALL_COLUMN_KEYS)[number];

// Employee and Actions stay visible at all times so the table can never be
// collapsed into something with no way to identify a row or open its details.
const LOCKED_COLUMNS: ColumnKey[] = ['employee', 'actions'];

// Per-break breakdown columns are opt-in: real data never exceeds 3 breaks/day,
// and the "View" details dialog already lists every break with no limit, so
// these are a convenience for table-scanning, not the only way to see them.
const DEFAULT_HIDDEN_COLUMNS: ColumnKey[] = [
    'break1_start_time',
    'break1_start_location',
    'break1_end_time',
    'break2_start_time',
    'break2_start_location',
    'break2_end_time',
    'break3_start_time',
    'break3_start_location',
    'break3_end_time',
];

const DEFAULT_VISIBLE_COLUMNS: ColumnKey[] = ALL_COLUMN_KEYS.filter((key) => !DEFAULT_HIDDEN_COLUMNS.includes(key));

const COLUMN_VISIBILITY_STORAGE_KEY = 'attendance-reports-visible-columns';

function loadVisibleColumns(): Set<ColumnKey> {
    if (typeof window === 'undefined') return new Set(DEFAULT_VISIBLE_COLUMNS);

    try {
        const saved = window.localStorage.getItem(COLUMN_VISIBILITY_STORAGE_KEY);
        if (!saved) return new Set(DEFAULT_VISIBLE_COLUMNS);

        const parsed: unknown = JSON.parse(saved);
        if (!Array.isArray(parsed)) return new Set(DEFAULT_VISIBLE_COLUMNS);

        const known = parsed.filter((key): key is ColumnKey => (ALL_COLUMN_KEYS as readonly string[]).includes(key));
        LOCKED_COLUMNS.forEach((key) => {
            if (!known.includes(key)) known.push(key);
        });

        return new Set(known);
    } catch {
        // Corrupt or inaccessible (private browsing, blocked storage) — fall back to the defaults.
        return new Set(DEFAULT_VISIBLE_COLUMNS);
    }
}

export default function AttendanceReports({ records, stats, users, queryParams }: ReportsPageProps) {
    const { auth } = usePage<SharedData>().props;
    const canManageAttendance = hasPermission(auth.permissions ?? [], 'manage-attendance');

    const userOptions = users.map((u) => ({
        value: String(u.id),
        label: u.employee_id ? `${u.name} (${u.employee_id})` : u.name,
    }));

    const [filters, setFilters] = useState({
        date_from: queryParams.date_from ?? '',
        date_to: queryParams.date_to ?? '',
        user_id: queryParams.user_id ?? '',
        source: queryParams.source ?? '',
        used_transport: queryParams.used_transport ?? '',
        has_anomaly: queryParams.has_anomaly ?? '',
    });
    const [filtersOpen, setFiltersOpen] = useState(Object.values(filters).some(Boolean));
    const hasActive = Object.values(filters).some(Boolean);

    const [reviewRecord, setReviewRecord] = useState<AttendanceReportRow | null>(null);
    const [correctingEventId, setCorrectingEventId] = useState<number | null>(null);
    const [correctionForm, setCorrectionForm] = useState({ event_time: '', void_reason: '' });
    const [submittingCorrection, setSubmittingCorrection] = useState(false);

    const [visibleColumns, setVisibleColumns] = useState<Set<ColumnKey>>(loadVisibleColumns);

    useEffect(() => {
        try {
            window.localStorage.setItem(COLUMN_VISIBILITY_STORAGE_KEY, JSON.stringify(Array.from(visibleColumns)));
        } catch {
            // localStorage unavailable — the toggle still works for this session, it just won't persist.
        }
    }, [visibleColumns]);

    function toggleColumn(key: ColumnKey) {
        if (LOCKED_COLUMNS.includes(key)) return;
        setVisibleColumns((prev) => {
            const next = new Set(prev);
            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }
            return next;
        });
    }

    const columns = useMemo<
        Array<{ key: ColumnKey; label: string; group?: string; className?: string; render: (record: AttendanceReportRow) => ReactNode }>
    >(
        () => [
            {
                key: 'employee',
                label: 'Employee',
                render: (record) => (
                    <>
                        <p className="font-medium">{record.user?.name ?? '—'}</p>
                        <p className="text-xs text-muted-foreground">
                            {record.user?.employee_id} {record.user?.department?.name && `· ${record.user.department.name}`}
                        </p>
                    </>
                ),
            },
            { key: 'date', label: 'Date', className: 'whitespace-nowrap', render: (record) => formatDate(record.work_date) },
            { key: 'check_in_time', label: 'Check In Time', render: (record) => formatTime(record.check_in_at) },
            {
                key: 'check_in_location',
                label: 'Check In Location',
                className: 'text-xs',
                render: (record) => formatLocation(eventsOfType(record.events ?? [], 'check_in')[0]),
            },
            { key: 'check_out_time', label: 'Check Out Time', render: (record) => formatTime(record.check_out_at) },
            {
                key: 'check_out_location',
                label: 'Check Out Location',
                className: 'text-xs',
                render: (record) => {
                    const checkOutEvents = eventsOfType(record.events ?? [], 'check_out');
                    return formatLocation(checkOutEvents[checkOutEvents.length - 1]);
                },
            },
            {
                key: 'factory_name',
                label: 'Factory Name',
                className: 'text-xs',
                render: (record) => {
                    const checkOutEvents = eventsOfType(record.events ?? [], 'check_out');
                    return checkOutEvents[checkOutEvents.length - 1]?.factory?.name ?? '—';
                },
            },
            {
                key: 'factory_address',
                label: 'Factory Address',
                className: 'text-xs',
                render: (record) => {
                    const checkOutEvents = eventsOfType(record.events ?? [], 'check_out');
                    return checkOutEvents[checkOutEvents.length - 1]?.factory?.address ?? '—';
                },
            },
            { key: 'work_hour', label: 'Work Hour', render: (record) => formatDuration(record.net_minutes) },
            { key: 'ot_hour', label: 'OT Hour', render: (record) => `${record.overtime_minutes}m` },
            { key: 'trip', label: 'Trip', className: 'text-xs', render: (record) => record.trip_passenger_event?.trip?.trip_number ?? '—' },
            {
                key: 'driver_name',
                label: 'Driver Name',
                className: 'text-xs',
                render: (record) => record.trip_passenger_event?.trip?.driver?.name ?? '—',
            },
            {
                key: 'inspection_type',
                label: 'Inspection Type',
                className: 'text-xs',
                render: (record) => record.trip_passenger_event?.trip?.trip_type ?? '—',
            },
            { key: 'total_break_time', label: 'Total Break Time', render: (record) => formatDuration(record.break_minutes) },
            ...([0, 1, 2] as const).flatMap((i) => {
                const n = i + 1;
                const group = `Break ${n}`;
                return [
                    {
                        key: `break${n}_start_time` as ColumnKey,
                        label: `Break ${n} Start Time`,
                        group,
                        render: (record: AttendanceReportRow) => formatTime(breakEventAt(record, i, 'start')?.event_time ?? null),
                    },
                    {
                        key: `break${n}_start_location` as ColumnKey,
                        label: `Break ${n} Start Location`,
                        group,
                        className: 'text-xs',
                        render: (record: AttendanceReportRow) => formatLocation(breakEventAt(record, i, 'start')),
                    },
                    {
                        key: `break${n}_end_time` as ColumnKey,
                        label: `Break ${n} End Time`,
                        group,
                        render: (record: AttendanceReportRow) => formatTime(breakEventAt(record, i, 'end')?.event_time ?? null),
                    },
                ];
            }),
            { key: 'source', label: 'Source', className: 'text-xs capitalize', render: (record) => record.source ?? '—' },
            {
                key: 'anomaly',
                label: 'Anomaly',
                render: (record) =>
                    record.has_anomaly ? (
                        <Badge variant="destructive" className="text-xs">
                            Anomaly
                        </Badge>
                    ) : (
                        <span className="text-muted-foreground">—</span>
                    ),
            },
            {
                key: 'actions',
                label: 'Actions',
                render: (record) => (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 w-8 p-0"
                        title="View attendance details"
                        onClick={() => setReviewRecord(record)}
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                ),
            },
        ],
        [],
    );

    const visibleColumnDefs = columns.filter((column) => visibleColumns.has(column.key));

    function startCorrection(event: AttendanceEventRow) {
        setCorrectingEventId(event.id);
        setCorrectionForm({ event_time: toDateTimeLocal(event.event_time), void_reason: '' });
    }

    function submitCorrection(event: AttendanceEventRow) {
        setSubmittingCorrection(true);
        router.post(
            `/attendance/events/${event.id}/correct`,
            {
                event_type: event.event_type,
                event_time: correctionForm.event_time,
                void_reason: correctionForm.void_reason,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCorrectingEventId(null);
                    setReviewRecord(null);
                },
                onFinish: () => setSubmittingCorrection(false),
            },
        );
    }

    function apply() {
        const params: Record<string, string> = {};
        Object.entries(filters).forEach(([k, v]) => {
            if (v) params[k] = v;
        });
        router.get('/attendance/reports', params, { preserveState: true });
    }

    function reset() {
        const empty = { date_from: '', date_to: '', user_id: '', source: '', used_transport: '', has_anomaly: '' };
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
                <div className="flex items-start justify-between gap-4">
                    <PageHeader title="Attendance Reports" description="Cross-employee check-in/check-out history" />
                    <ExportButton exportUrl="/attendance/reports/export" queryParams={queryParams} />
                </div>

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
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                                <FormMultiSelect
                                    label="User"
                                    name="user_id"
                                    placeholder="All users"
                                    options={userOptions}
                                    value={filters.user_id ? [filters.user_id] : []}
                                    onChange={(values) => setFilters((f) => ({ ...f, user_id: values[0] || '' }))}
                                    searchable
                                />
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
                        <div className="flex items-center justify-between">
                            <CardTitle className="text-sm font-semibold">
                                Records
                                <span className="ml-2 text-muted-foreground font-normal">({records.total} total)</span>
                            </CardTitle>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="sm" className="gap-2">
                                        <SlidersHorizontal className="h-4 w-4" />
                                        Columns
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-64 max-h-96 overflow-y-auto">
                                    <DropdownMenuLabel>Toggle columns</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {columns.map((column, index) => {
                                        const showGroupHeader = column.group && column.group !== columns[index - 1]?.group;

                                        return (
                                            <Fragment key={column.key}>
                                                {showGroupHeader && (
                                                    <>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                                                            {column.group}
                                                        </DropdownMenuLabel>
                                                    </>
                                                )}
                                                <DropdownMenuCheckboxItem
                                                    checked={visibleColumns.has(column.key)}
                                                    disabled={LOCKED_COLUMNS.includes(column.key)}
                                                    onSelect={(e) => e.preventDefault()}
                                                    onCheckedChange={() => toggleColumn(column.key)}
                                                >
                                                    {column.label}
                                                </DropdownMenuCheckboxItem>
                                            </Fragment>
                                        );
                                    })}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/50">
                                        {visibleColumnDefs.map((column) => (
                                            <th key={column.key} className="px-4 py-3 text-left font-medium text-muted-foreground">
                                                {column.label}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {records.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={visibleColumnDefs.length} className="px-4 py-12 text-center text-muted-foreground">
                                                No attendance records found.
                                            </td>
                                        </tr>
                                    ) : (
                                        records.data.map((record) => (
                                            <tr key={record.id} className="border-b hover:bg-muted/30 transition-colors">
                                                {visibleColumnDefs.map((column) => (
                                                    <td key={column.key} className={cn('px-4 py-3', column.className)}>
                                                        {column.render(record)}
                                                    </td>
                                                ))}
                                            </tr>
                                        ))
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

            <Dialog
                open={!!reviewRecord}
                onOpenChange={(open) => {
                    if (!open) {
                        setReviewRecord(null);
                        setCorrectingEventId(null);
                    }
                }}
            >
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            Review — {reviewRecord?.user?.name ?? '—'} · {reviewRecord ? formatDate(reviewRecord.work_date) : ''}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="flex flex-col gap-2">
                        {(reviewRecord?.events ?? []).map((event) => (
                            <div key={event.id} className="rounded-md border p-3">
                                <div className="flex items-center justify-between gap-2">
                                    <div>
                                        <p className="text-sm font-medium">{EVENT_TYPE_LABELS[event.event_type]}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatTime(event.event_time)} · {formatLocation(event)}
                                        </p>
                                    </div>
                                    {canManageAttendance && correctingEventId !== event.id && (
                                        <Button variant="ghost" size="sm" className="gap-1" onClick={() => startCorrection(event)}>
                                            <Pencil className="h-3 w-3" />
                                            Correct
                                        </Button>
                                    )}
                                </div>

                                {correctingEventId === event.id && (
                                    <div className="mt-3 flex flex-col gap-2 border-t pt-3">
                                        <div className="flex flex-col gap-1">
                                            <Label className="text-xs">Corrected time</Label>
                                            <Input
                                                type="datetime-local"
                                                value={correctionForm.event_time}
                                                onChange={(e) => setCorrectionForm((f) => ({ ...f, event_time: e.target.value }))}
                                            />
                                        </div>
                                        <div className="flex flex-col gap-1">
                                            <Label className="text-xs">Reason</Label>
                                            <Textarea
                                                value={correctionForm.void_reason}
                                                onChange={(e) => setCorrectionForm((f) => ({ ...f, void_reason: e.target.value }))}
                                                placeholder="Why is this being corrected?"
                                                rows={2}
                                            />
                                        </div>
                                        <div className="flex justify-end gap-2">
                                            <Button variant="ghost" size="sm" onClick={() => setCorrectingEventId(null)}>
                                                Cancel
                                            </Button>
                                            <Button
                                                size="sm"
                                                disabled={submittingCorrection || !correctionForm.event_time || !correctionForm.void_reason}
                                                onClick={() => submitCorrection(event)}
                                            >
                                                Save Correction
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}

                        {(reviewRecord?.events ?? []).length === 0 && <p className="text-sm text-muted-foreground">No events recorded for this day.</p>}
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setReviewRecord(null)}>
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppSidebarLayout>
    );
}
