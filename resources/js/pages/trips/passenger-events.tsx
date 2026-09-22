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
import { CalendarClock, CheckCircle, Filter, RefreshCw, User, X, XCircle } from 'lucide-react';
import { useState } from 'react';

interface PassengerEvent {
    id: number;
    trip_id: number;
    event_type: string;
    event_time: string;
    area_name: string | null;
    source: string | null;
    is_valid: boolean;
    void_reason: string | null;
    trip: { id: number; trip_number: string; scheduled_date: string; status: string } | null;
    user: { id: number; name: string; employee_id: string | null } | null;
    stop: { id: number; name: string } | null;
    actor: { id: number; name: string } | null;
}

interface PaginatedEvents {
    data: PassengerEvent[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface TripOption {
    id: number;
    trip_number: string;
    scheduled_date: string;
}

interface PassengerEventsPageProps {
    events: PaginatedEvents;
    stats: {
        total: number;
        check_in: number;
        check_out: number;
        no_show: number;
        voided: number;
        corrections: number;
    };
    sources: string[];
    trips: TripOption[];
    queryParams: {
        event_type?: string;
        validity?: string;
        source?: string;
        trip_id?: string;
        user_id?: string;
        date_from?: string;
        date_to?: string;
        area?: string;
        sort?: string;
        direction?: string;
        per_page?: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Trips', href: '/trips' },
    { title: 'Passenger Events', href: '/trips/passenger-events' },
];

const EVENT_TYPE_LABELS: Record<string, string> = {
    check_in: 'Check In',
    check_out: 'Check Out',
    no_show: 'No Show',
    correction: 'Correction',
};

const EVENT_TYPE_COLORS: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
    check_in: 'default',
    check_out: 'secondary',
    no_show: 'destructive',
    correction: 'outline',
};

const STATUS_COLORS: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/15 dark:text-yellow-300',
    approved: 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300',
    in_progress: 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300',
    completed: 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
};

function formatDateTime(dt: string) {
    return new Date(dt).toLocaleString(undefined, {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

export default function PassengerEvents({ events, stats, sources, trips, queryParams }: PassengerEventsPageProps) {
    const [filters, setFilters] = useState({
        event_type: queryParams.event_type ?? '',
        validity: queryParams.validity ?? '',
        source: queryParams.source ?? '',
        trip_id: queryParams.trip_id ?? '',
        date_from: queryParams.date_from ?? '',
        date_to: queryParams.date_to ?? '',
        area: queryParams.area ?? '',
    });
    const [filtersOpen, setFiltersOpen] = useState(Object.values(filters).some(Boolean));

    const hasActive = Object.values(filters).some(Boolean);

    function apply() {
        const params: Record<string, string> = {};
        Object.entries(filters).forEach(([k, v]) => { if (v) params[k] = v; });
        router.get('/trips/passenger-events', params, { preserveState: true });
    }

    function reset() {
        const empty = { event_type: '', validity: '', source: '', trip_id: '', date_from: '', date_to: '', area: '' };
        setFilters(empty);
        router.get('/trips/passenger-events', {}, { preserveState: false });
    }

    function goToPage(page: number) {
        const params: Record<string, string> = {};
        Object.entries(filters).forEach(([k, v]) => { if (v) params[k] = v; });
        params.page = String(page);
        router.get('/trips/passenger-events', params, { preserveState: true });
    }

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Passenger Events" />
            <div className="flex flex-col gap-6 p-6">
                <PageHeader
                    title="Passenger Events"
                    description="Cross-trip view of all passenger attendance events"
                />

                {/* Stats */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    {[
                        { label: 'Total', value: stats.total, icon: CalendarClock },
                        { label: 'Check-ins', value: stats.check_in, icon: CheckCircle },
                        { label: 'Check-outs', value: stats.check_out, icon: CheckCircle },
                        { label: 'No-shows', value: stats.no_show, icon: XCircle },
                        { label: 'Corrections', value: stats.corrections, icon: RefreshCw },
                        { label: 'Voided', value: stats.voided, icon: X },
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

                {/* Filters */}
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
                                <Button variant="ghost" size="sm" onClick={() => setFiltersOpen(o => !o)}>
                                    {filtersOpen ? 'Hide' : 'Show'}
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    {filtersOpen && (
                        <CardContent>
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs">Event Type</Label>
                                    <Select value={filters.event_type} onValueChange={v => setFilters(f => ({ ...f, event_type: v === '_all' ? '' : v }))}>
                                        <SelectTrigger><SelectValue placeholder="All types" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="_all">All types</SelectItem>
                                            <SelectItem value="check_in">Check In</SelectItem>
                                            <SelectItem value="check_out">Check Out</SelectItem>
                                            <SelectItem value="no_show">No Show</SelectItem>
                                            <SelectItem value="correction">Correction</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs">Validity</Label>
                                    <Select value={filters.validity} onValueChange={v => setFilters(f => ({ ...f, validity: v === '_all' ? '' : v }))}>
                                        <SelectTrigger><SelectValue placeholder="All" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="_all">All</SelectItem>
                                            <SelectItem value="valid">Valid only</SelectItem>
                                            <SelectItem value="voided">Voided only</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                {sources.length > 0 && (
                                    <div className="flex flex-col gap-1">
                                        <Label className="text-xs">Source</Label>
                                        <Select value={filters.source} onValueChange={v => setFilters(f => ({ ...f, source: v === '_all' ? '' : v }))}>
                                            <SelectTrigger><SelectValue placeholder="All sources" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="_all">All sources</SelectItem>
                                                {sources.map(s => <SelectItem key={s} value={s}>{s}</SelectItem>)}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs">Trip</Label>
                                    <Select value={filters.trip_id} onValueChange={v => setFilters(f => ({ ...f, trip_id: v === '_all' ? '' : v }))}>
                                        <SelectTrigger><SelectValue placeholder="All trips" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="_all">All trips</SelectItem>
                                            {trips.map(t => (
                                                <SelectItem key={t.id} value={String(t.id)}>
                                                    {t.trip_number} ({t.scheduled_date})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs">Date From</Label>
                                    <Input type="date" value={filters.date_from} onChange={e => setFilters(f => ({ ...f, date_from: e.target.value }))} />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs">Date To</Label>
                                    <Input type="date" value={filters.date_to} onChange={e => setFilters(f => ({ ...f, date_to: e.target.value }))} />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <Label className="text-xs">Area Search</Label>
                                    <Input placeholder="Search area…" value={filters.area} onChange={e => setFilters(f => ({ ...f, area: e.target.value }))} />
                                </div>
                            </div>
                            <div className="mt-4 flex justify-end">
                                <Button onClick={apply}>Apply Filters</Button>
                            </div>
                        </CardContent>
                    )}
                </Card>

                {/* Events Table */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-semibold">
                            Events
                            <span className="ml-2 text-muted-foreground font-normal">({events.total} total)</span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/50">
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Trip</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Passenger</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Event</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Time</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Stop / Area</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Source</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Recorded By</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {events.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={8} className="px-4 py-12 text-center text-muted-foreground">
                                                No events found.
                                            </td>
                                        </tr>
                                    ) : events.data.map(event => (
                                        <tr key={event.id} className="border-b hover:bg-muted/30 transition-colors">
                                            <td className="px-4 py-3">
                                                {event.trip ? (
                                                    <div>
                                                        <a href={`/trips/${event.trip_id}`} className="font-medium text-primary hover:underline">
                                                            {event.trip.trip_number}
                                                        </a>
                                                        <div className="text-xs text-muted-foreground">{event.trip.scheduled_date}</div>
                                                        <span className={`inline-block rounded px-1.5 py-0.5 text-xs ${STATUS_COLORS[event.trip.status] ?? 'bg-gray-100 text-gray-800'}`}>
                                                            {event.trip.status.replace('_', ' ')}
                                                        </span>
                                                    </div>
                                                ) : <span className="text-muted-foreground">—</span>}
                                            </td>
                                            <td className="px-4 py-3">
                                                {event.user ? (
                                                    <div className="flex items-center gap-1.5">
                                                        <User className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                                                        <div>
                                                            <p className="font-medium">{event.user.name}</p>
                                                            {event.user.employee_id && (
                                                                <p className="text-xs text-muted-foreground">{event.user.employee_id}</p>
                                                            )}
                                                        </div>
                                                    </div>
                                                ) : <span className="text-muted-foreground">—</span>}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Badge variant={EVENT_TYPE_COLORS[event.event_type] ?? 'outline'}>
                                                    {EVENT_TYPE_LABELS[event.event_type] ?? event.event_type}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-xs">
                                                {formatDateTime(event.event_time)}
                                            </td>
                                            <td className="px-4 py-3 text-xs">
                                                {event.stop?.name ?? event.area_name ?? <span className="text-muted-foreground">—</span>}
                                            </td>
                                            <td className="px-4 py-3 text-xs capitalize">
                                                {event.source ?? <span className="text-muted-foreground">—</span>}
                                            </td>
                                            <td className="px-4 py-3 text-xs">
                                                {event.actor?.name ?? <span className="text-muted-foreground">—</span>}
                                            </td>
                                            <td className="px-4 py-3">
                                                {event.is_valid ? (
                                                    <Badge variant="outline" className="border-green-500 text-green-700 text-xs">Valid</Badge>
                                                ) : (
                                                    <div>
                                                        <Badge variant="destructive" className="text-xs">Voided</Badge>
                                                        {event.void_reason && (
                                                            <p className="mt-0.5 text-xs text-muted-foreground">{event.void_reason}</p>
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {events.last_page > 1 && (
                            <div className="flex items-center justify-between border-t px-4 py-3">
                                <p className="text-xs text-muted-foreground">
                                    Showing {events.from}–{events.to} of {events.total}
                                </p>
                                <div className="flex gap-1">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={events.current_page === 1}
                                        onClick={() => goToPage(events.current_page - 1)}
                                    >
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={events.current_page === events.last_page}
                                        onClick={() => goToPage(events.current_page + 1)}
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
