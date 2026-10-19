import { AttendanceTripPassenger } from '@/components/trip-passenger-attendance-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { TripPassengerEvent } from '@/types';
import { Filter, History, RefreshCw } from 'lucide-react';
import { useMemo, useState } from 'react';

interface AttendanceHistoryCardProps {
    allAttendanceEvents: Array<{ passenger: AttendanceTripPassenger; event: TripPassengerEvent }>;
    passengers: AttendanceTripPassenger[];
    canManageAttendance: boolean;
    canCorrectAttendance: boolean;
    onCorrectEvent: (passenger: AttendanceTripPassenger, event: TripPassengerEvent) => void;
}

const getEventBadge = (event: TripPassengerEvent) => {
    const config: Record<TripPassengerEvent['event_type'], string> = {
        check_in: 'border-emerald-200 bg-emerald-100 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-300',
        check_out: 'border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/15 dark:text-blue-300',
        no_show: 'border-rose-200 bg-rose-100 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/15 dark:text-rose-300',
        manual_override: 'border-amber-200 bg-amber-100 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-300',
        correction: 'border-violet-200 bg-violet-100 text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/15 dark:text-violet-300',
    };

    return (
        <Badge variant="outline" className={`${config[event.event_type]} capitalize`}>
            {event.event_type.replace('_', ' ')}
        </Badge>
    );
};

const formatDateTime = (value?: string | null) => {
    if (!value) return 'Not captured';
    return new Date(value).toLocaleString();
};

export function AttendanceHistoryCard({
    allAttendanceEvents,
    passengers,
    canManageAttendance,
    canCorrectAttendance,
    onCorrectEvent,
}: AttendanceHistoryCardProps) {
    const [filterEventTypes, setFilterEventTypes] = useState<string[]>([]);
    const [filterValidity, setFilterValidity] = useState<'all' | 'valid' | 'voided'>('all');
    const [filterSources, setFilterSources] = useState<string[]>([]);
    const [filterPassengerId, setFilterPassengerId] = useState<string>('');
    const [filterDateFrom, setFilterDateFrom] = useState<string>('');
    const [filterDateTo, setFilterDateTo] = useState<string>('');
    const [filterAreaSearch, setFilterAreaSearch] = useState<string>('');
    const [filtersOpen, setFiltersOpen] = useState(false);

    const uniqueSources = useMemo(
        () => [...new Set(allAttendanceEvents.map((e) => e.event.source).filter(Boolean))] as string[],
        [allAttendanceEvents],
    );

    const hasActiveFilters =
        filterEventTypes.length > 0 ||
        filterValidity !== 'all' ||
        filterSources.length > 0 ||
        filterPassengerId !== '' ||
        filterDateFrom !== '' ||
        filterDateTo !== '' ||
        filterAreaSearch !== '';

    const attendanceEvents = useMemo(() => {
        return allAttendanceEvents.filter(({ passenger, event }) => {
            if (filterEventTypes.length > 0 && !filterEventTypes.includes(event.event_type)) return false;
            if (filterValidity === 'valid' && !event.is_valid) return false;
            if (filterValidity === 'voided' && event.is_valid) return false;
            if (filterSources.length > 0 && !filterSources.includes(event.source ?? '')) return false;
            if (filterPassengerId && String(passenger.user?.id ?? passenger.user_id) !== filterPassengerId) return false;
            if (filterAreaSearch && !event.area_name?.toLowerCase().includes(filterAreaSearch.toLowerCase())) return false;
            if (filterDateFrom) {
                const eventDate = new Date(event.event_time);
                const fromDate = new Date(filterDateFrom);
                if (eventDate < fromDate) return false;
            }
            if (filterDateTo) {
                const eventDate = new Date(event.event_time);
                const toDate = new Date(filterDateTo + 'T23:59:59');
                if (eventDate > toDate) return false;
            }
            return true;
        });
    }, [allAttendanceEvents, filterEventTypes, filterValidity, filterSources, filterPassengerId, filterDateFrom, filterDateTo, filterAreaSearch]);

    const resetFilters = () => {
        setFilterEventTypes([]);
        setFilterValidity('all');
        setFilterSources([]);
        setFilterPassengerId('');
        setFilterDateFrom('');
        setFilterDateTo('');
        setFilterAreaSearch('');
    };

    const toggleCheckbox = (list: string[], setList: (v: string[]) => void, value: string) => {
        setList(list.includes(value) ? list.filter((v) => v !== value) : [...list, value]);
    };

    if (allAttendanceEvents.length === 0) return null;

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <History className="h-5 w-5" />
                            Attendance Event History
                            <Badge variant="outline" className="ml-1 font-normal">
                                {attendanceEvents.length}
                                {hasActiveFilters ? ` / ${allAttendanceEvents.length}` : ''}
                            </Badge>
                        </CardTitle>
                        <CardDescription className="mt-1">Append-only history with voided records retained for audit and correction.</CardDescription>
                    </div>
                    <div className="flex items-center gap-2">
                        {hasActiveFilters && (
                            <Button variant="ghost" size="sm" onClick={resetFilters} className="h-8 gap-1 text-xs">
                                <RefreshCw className="h-3 w-3" />
                                Clear
                            </Button>
                        )}
                        <Button
                            variant={filtersOpen ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setFiltersOpen((v) => !v)}
                            className="h-8 gap-1.5"
                        >
                            <Filter className="h-3.5 w-3.5" />
                            Filters
                            {hasActiveFilters && (
                                <span className="flex h-4 w-4 items-center justify-center rounded-full bg-primary-foreground text-[10px] font-bold text-primary">
                                    {filterEventTypes.length +
                                        filterSources.length +
                                        (filterValidity !== 'all' ? 1 : 0) +
                                        (filterPassengerId ? 1 : 0) +
                                        (filterDateFrom ? 1 : 0) +
                                        (filterDateTo ? 1 : 0) +
                                        (filterAreaSearch ? 1 : 0)}
                                </span>
                            )}
                        </Button>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="space-y-0 p-0">
                <div className="flex min-h-0 flex-col lg:flex-row">
                    {filtersOpen && (
                        <aside className="w-full shrink-0 space-y-5 border-b p-5 lg:w-64 lg:border-r lg:border-b-0">
                            <div>
                                <p className="mb-2.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Event Type</p>
                                <div className="space-y-2">
                                    {(['check_in', 'check_out', 'no_show', 'manual_override', 'correction'] as const).map((type) => (
                                        <label key={type} className="flex cursor-pointer items-center gap-2">
                                            <Checkbox
                                                id={`filter_event_${type}`}
                                                name="event_types[]"
                                                checked={filterEventTypes.includes(type)}
                                                onCheckedChange={() => toggleCheckbox(filterEventTypes, setFilterEventTypes, type)}
                                            />
                                            <span className="text-sm capitalize">{type.replace('_', ' ')}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                            <Separator />
                            <div>
                                <p className="mb-2.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Validity</p>
                                <div className="space-y-2">
                                    {(['all', 'valid', 'voided'] as const).map((opt) => (
                                        <label key={opt} className="flex cursor-pointer items-center gap-2">
                                            <input
                                                type="radio"
                                                name="event-validity"
                                                value={opt}
                                                checked={filterValidity === opt}
                                                onChange={() => setFilterValidity(opt)}
                                                className="h-3.5 w-3.5 accent-primary"
                                            />
                                            <span className="text-sm capitalize">{opt}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                            {uniqueSources.length > 0 && (
                                <>
                                    <Separator />
                                    <div>
                                        <p className="mb-2.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Source</p>
                                        <div className="space-y-2">
                                            {uniqueSources.map((src) => (
                                                <label key={src} className="flex cursor-pointer items-center gap-2">
                                                    <Checkbox
                                                        id={`filter_source_${src}`}
                                                        name="sources[]"
                                                        checked={filterSources.includes(src)}
                                                        onCheckedChange={() => toggleCheckbox(filterSources, setFilterSources, src)}
                                                    />
                                                    <span className="text-sm capitalize">{src.replace('_', ' ')}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                </>
                            )}
                            <Separator />
                            <div>
                                <p className="mb-2.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Passenger</p>
                                <select
                                    id="filter_passenger_id"
                                    name="passenger_id"
                                    aria-label="Passenger"
                                    value={filterPassengerId}
                                    onChange={(e) => setFilterPassengerId(e.target.value)}
                                    className="w-full rounded-md border bg-background px-2.5 py-1.5 text-sm focus:ring-1 focus:ring-ring focus:outline-none"
                                >
                                    <option value="">All passengers</option>
                                    {passengers.map((p) => (
                                        <option key={p.id} value={String(p.user?.id ?? p.user_id)}>
                                            {p.user?.name ?? `Passenger ${p.id}`}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <Separator />
                            <div>
                                <p className="mb-2.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Date Range</p>
                                <div className="space-y-2">
                                    <div>
                                        <Label htmlFor="filter_date_from" className="mb-1 text-xs text-muted-foreground">
                                            From
                                        </Label>
                                        <Input
                                            id="filter_date_from"
                                            name="date_from"
                                            type="date"
                                            value={filterDateFrom}
                                            onChange={(e) => setFilterDateFrom(e.target.value)}
                                            className="h-8 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="filter_date_to" className="mb-1 text-xs text-muted-foreground">
                                            To
                                        </Label>
                                        <Input
                                            id="filter_date_to"
                                            name="date_to"
                                            type="date"
                                            value={filterDateTo}
                                            onChange={(e) => setFilterDateTo(e.target.value)}
                                            className="h-8 text-sm"
                                        />
                                    </div>
                                </div>
                            </div>
                            <Separator />
                            <div>
                                <p className="mb-2.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase">Area</p>
                                <Input
                                    id="filter_area"
                                    name="area"
                                    aria-label="Search area name"
                                    type="text"
                                    placeholder="Search area name…"
                                    value={filterAreaSearch}
                                    onChange={(e) => setFilterAreaSearch(e.target.value)}
                                    className="h-8 text-sm"
                                />
                            </div>
                            {hasActiveFilters && (
                                <>
                                    <Separator />
                                    <Button variant="outline" size="sm" className="w-full gap-1.5" onClick={resetFilters}>
                                        <RefreshCw className="h-3.5 w-3.5" />
                                        Reset all filters
                                    </Button>
                                </>
                            )}
                        </aside>
                    )}

                    <div className="flex-1 space-y-3 p-5">
                        {attendanceEvents.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Filter className="mb-3 h-8 w-8 text-muted-foreground/40" />
                                <p className="text-sm font-medium text-muted-foreground">No events match the current filters</p>
                                <Button variant="link" size="sm" className="mt-1" onClick={resetFilters}>
                                    Clear filters
                                </Button>
                            </div>
                        ) : (
                            attendanceEvents.map(({ passenger, event }) => (
                                <div key={event.id} className="rounded-xl border p-4">
                                    <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                        <div className="space-y-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                {getEventBadge(event)}
                                                {!event.is_valid && <Badge variant="destructive">Voided</Badge>}
                                                <span className="text-sm font-medium">{passenger.user?.name ?? 'Unknown passenger'}</span>
                                            </div>
                                            <div className="grid grid-cols-1 gap-2 text-sm text-gray-600 md:grid-cols-2 xl:grid-cols-3">
                                                <p>
                                                    Time: <span className="font-medium text-gray-900">{formatDateTime(event.event_time)}</span>
                                                </p>
                                                <p>
                                                    Area: <span className="font-medium text-gray-900">{event.area_name ?? 'Not captured'}</span>
                                                </p>
                                                <p>
                                                    IP: <span className="font-medium text-gray-900">{event.ip_address ?? 'Not captured'}</span>
                                                </p>
                                                <p>
                                                    Source: <span className="font-medium text-gray-900">{event.source ?? 'Unknown'}</span>
                                                </p>
                                                <p>
                                                    Stop: <span className="font-medium text-gray-900">{event.stop?.name ?? 'Not linked'}</span>
                                                </p>
                                                <p>
                                                    Actor: <span className="font-medium text-gray-900">{event.actor?.name ?? 'System'}</span>
                                                </p>
                                            </div>
                                            {(event.latitude || event.longitude || event.gps_accuracy_meters) && (
                                                <p className="text-xs text-gray-500">
                                                    GPS: {event.latitude ?? '-'}, {event.longitude ?? '-'}
                                                    {event.gps_accuracy_meters ? ` (accuracy ${event.gps_accuracy_meters}m)` : ''}
                                                </p>
                                            )}
                                            {!event.is_valid && event.void_reason && (
                                                <p className="text-xs text-rose-700">Voided reason: {event.void_reason}</p>
                                            )}
                                        </div>
                                        {canManageAttendance && event.is_valid && canCorrectAttendance && (
                                            <Button variant="outline" size="sm" onClick={() => onCorrectEvent(passenger, event)}>
                                                Correct Event
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
