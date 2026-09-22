import InputError from '@/components/input-error';
import { AttendanceHistoryCard } from '@/components/trip-attendance-history';
import { TripActionButtons } from '@/components/trip-action-buttons';
import { AttendanceTripPassenger, PassengerAttendanceCard } from '@/components/trip-passenger-attendance-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { CANCELLATION_REASONS, canCancelTrip, canCompleteTrip, canStartTrip } from '@/lib/trip-status';
import { BreadcrumbItem, Trip, TripAuditLog, TripFeedback, TripPassengerEvent, TripRouteAssignment, TripVehicleAssignment, User as UserType } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Ban,
    Building2,
    Calendar,
    Car,
    CheckCircle,
    CheckCircle2,
    ClipboardList,
    Clock,
    FileText,
    Flag,
    MapPin,
    MessageSquare,
    MessageSquareWarning,
    Play,
    Plus,
    User,
    Users,
    XCircle,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Trips', href: '/trips' },
    { title: 'Trip Details', href: '#' },
];

type AttendanceMode = 'check_in' | 'check_out' | 'no_show' | 'correct';

type AttendanceFormShape = {
    event_type: 'check_in' | 'check_out' | 'no_show' | 'manual_override';
    event_time: string;
    stop_id: string;
    latitude: string;
    longitude: string;
    gps_accuracy_meters: string;
    area_name: string;
    device_id: string;
    reason: string;
    idempotency_key: string;
};

type TripFactory = {
    id: number;
    name: string;
};

type TripDepartmentWithHeadcount = {
    id: number;
    name: string;
    pivot?: {
        count?: number;
    };
};

type TripDetails = Trip & {
    factories?: TripFactory[];
    departments?: TripDepartmentWithHeadcount[];
    feedbackEntries?: TripFeedback[];
    cancelled_by_user?: UserType;
};

const getStatusBadge = (status: Trip['status']) => {
    const config = {
        pending: { label: 'Pending', className: 'border-yellow-200 bg-yellow-100 text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-500/15 dark:text-yellow-300', icon: Clock },
        approved: { label: 'Approved', className: 'border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/15 dark:text-blue-300', icon: CheckCircle },
        rejected: { label: 'Rejected', className: 'border-red-200 bg-red-100 text-red-800 dark:border-red-500/30 dark:bg-red-500/15 dark:text-red-300', icon: XCircle },
        assigned: { label: 'Assigned', className: 'border-cyan-200 bg-cyan-100 text-cyan-800 dark:border-cyan-500/30 dark:bg-cyan-500/15 dark:text-cyan-300', icon: CheckCircle },
        in_progress: { label: 'In Progress', className: 'border-violet-200 bg-violet-100 text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/15 dark:text-violet-300', icon: Clock },
        completed: { label: 'Completed', className: 'border-green-200 bg-green-100 text-green-800 dark:border-green-500/30 dark:bg-green-500/15 dark:text-green-300', icon: CheckCircle },
        cancelled: { label: 'Cancelled', className: 'border-slate-200 bg-slate-100 text-slate-800 dark:border-slate-500/30 dark:bg-slate-500/15 dark:text-slate-300', icon: XCircle },
    };
    const { label, className, icon: Icon } = config[status] || config.pending;

    return (
        <Badge className={`${className} flex items-center gap-1.5`}>
            <Icon className="h-3.5 w-3.5" />
            {label}
        </Badge>
    );
};

const getPriorityBadge = (priority: Trip['priority']) => {
    const config = {
        urgent: { label: 'Urgent', className: 'border-red-300 bg-red-100 text-red-800 dark:border-red-500/40 dark:bg-red-500/20 dark:text-red-300', icon: Flag },
        high: { label: 'High', className: 'border-orange-200 bg-orange-100 text-orange-800 dark:border-orange-500/30 dark:bg-orange-500/15 dark:text-orange-300', icon: Flag },
        medium: { label: 'Medium', className: 'border-yellow-200 bg-yellow-100 text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-500/15 dark:text-yellow-300', icon: Flag },
        low: { label: 'Low', className: 'border-slate-200 bg-slate-100 text-slate-800 dark:border-slate-500/30 dark:bg-slate-500/15 dark:text-slate-300', icon: Flag },
    };
    const { label, className, icon: Icon } = config[priority] || config.medium;

    return (
        <Badge variant="outline" className={`${className} flex items-center gap-1.5`}>
            <Icon className="h-3.5 w-3.5" />
            {label}
        </Badge>
    );
};

// Only 'pending' | 'boarded' | 'completed' | 'no_show' are ever actually written to
// TripPassenger.status by the app; 'confirmed'/'cancelled' remain in the DB enum but
// are unreachable in practice, so no badge styling is defined for them.
const getPassengerStatusBadge = (status?: string) => {
    const config = {
        pending: 'border-slate-200 bg-slate-100 text-slate-800 dark:border-slate-500/30 dark:bg-slate-500/15 dark:text-slate-300',
        boarded: 'border-emerald-200 bg-emerald-100 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-300',
        completed: 'border-green-200 bg-green-100 text-green-800 dark:border-green-500/30 dark:bg-green-500/15 dark:text-green-300',
        no_show: 'border-rose-200 bg-rose-100 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/15 dark:text-rose-300',
    } as const;

    const value = (status ?? 'pending') as keyof typeof config;

    return (
        <Badge variant="outline" className={`${config[value] ?? config.pending} capitalize`}>
            {value.replace('_', ' ')}
        </Badge>
    );
};

const formatDateTime = (value?: string | null) => {
    if (!value) {
        return 'Not captured';
    }

    return new Date(value).toLocaleString();
};

const toLocalDateTimeValue = (value?: string | null) => {
    const date = value ? new Date(value) : new Date();
    const normalizedDate = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);

    return normalizedDate.toISOString().slice(0, 16);
};

const getDefaultStopId = (mode: AttendanceMode, passenger: AttendanceTripPassenger, event?: TripPassengerEvent) => {
    if (event?.stop_id) {
        return String(event.stop_id);
    }

    if (mode === 'check_in') {
        return passenger.pickup_stop_id ? String(passenger.pickup_stop_id) : '';
    }

    if (mode === 'check_out') {
        return passenger.dropoff_stop_id ? String(passenger.dropoff_stop_id) : '';
    }

    return '';
};

/**
 * Check if the current user has a specific permission
 */
const checkPermission = (permission: string, permissions: string[] = []): boolean => {
    return permissions.includes(permission);
};

const buildFormDefaults = (mode: AttendanceMode, passenger: AttendanceTripPassenger, event?: TripPassengerEvent): AttendanceFormShape => ({
    event_type: (mode === 'correct' ? event?.event_type : mode) === 'check_out' ? 'check_out' : (mode === 'correct' ? event?.event_type : mode) === 'no_show' ? 'no_show' : 'check_in',
    event_time: toLocalDateTimeValue(event?.event_time),
    stop_id: getDefaultStopId(mode, passenger, event),
    latitude: event?.latitude ? String(event.latitude) : '',
    longitude: event?.longitude ? String(event.longitude) : '',
    gps_accuracy_meters: event?.gps_accuracy_meters ? String(event.gps_accuracy_meters) : '',
    area_name: event?.area_name ?? '',
    device_id: event?.device_id ?? '',
    reason: '',
    idempotency_key: '',
});

type ShowTripProps = {
    trip: TripDetails;
    vehicleAssignments?: TripVehicleAssignment[];
    routeAssignments?: TripRouteAssignment[];
    auditLogs?: TripAuditLog[];
};

const formatAuditAction = (action: string) => action.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());

export default function ShowTrip({ trip, vehicleAssignments = [], routeAssignments = [], auditLogs = [] }: ShowTripProps) {
    const pageProps = usePage().props as unknown as { auth?: { user?: { id?: number }; permissions?: string[]; roles?: string[] } };
    const permissions = pageProps.auth?.permissions ?? [];
    const currentUserId = pageProps.auth?.user?.id;
    const passengers = (trip.passengers ?? []) as AttendanceTripPassenger[];
    const canManageAttendance = !['cancelled', 'rejected'].includes(trip.status);
    const canCaptureAttendance = checkPermission('capture-passenger-attendance', permissions);
    const canCorrectAttendance = checkPermission('correct-passenger-attendance', permissions);
    const canCreateComplaint = checkPermission('create-complaints', permissions);
    const canEditTrips = checkPermission('edit-trips', permissions);
    const isAssignedDriver = !!currentUserId && trip.vehicle?.driver_id === currentUserId;
    const [dialogState, setDialogState] = useState<{
        mode: AttendanceMode;
        passenger: AttendanceTripPassenger;
        event?: TripPassengerEvent;
    } | null>(null);
    const [startTripOpen, setStartTripOpen] = useState(false);
    const [cancelTripOpen, setCancelTripOpen] = useState(false);
    const [completeTripOpen, setCompleteTripOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<AttendanceFormShape>({
        event_type: 'check_in',
        event_time: toLocalDateTimeValue(),
        stop_id: '',
        latitude: '',
        longitude: '',
        gps_accuracy_meters: '',
        area_name: '',
        device_id: '',
        reason: '',
        idempotency_key: '',
    });
    const {
        data: startTripData,
        setData: setStartTripData,
        post: postStartTrip,
        processing: startTripProcessing,
        errors: startTripErrors,
        reset: resetStartTrip,
        clearErrors: clearStartTripErrors,
    } = useForm<{ odometer_start: string }>({
        odometer_start: '',
    });
    const {
        data: cancelTripData,
        setData: setCancelTripData,
        post: postCancelTrip,
        processing: cancelTripProcessing,
        errors: cancelTripErrors,
        reset: resetCancelTrip,
        clearErrors: clearCancelTripErrors,
    } = useForm<{ cancellation_reason: string; cancellation_notes: string }>({
        cancellation_reason: '',
        cancellation_notes: '',
    });
    const {
        data: completeTripData,
        setData: setCompleteTripData,
        post: postCompleteTrip,
        processing: completeTripProcessing,
        errors: completeTripErrors,
        reset: resetCompleteTrip,
        clearErrors: clearCompleteTripErrors,
    } = useForm<{ odometer_end: string; fuel_consumed: string; fuel_cost: string; other_costs: string; notes: string }>({
        odometer_end: '',
        fuel_consumed: '',
        fuel_cost: '',
        other_costs: '',
        notes: '',
    });

    const attendanceSummary = {
        totalPassengers: passengers.length,
        pending: passengers.filter((passenger) => (passenger.status ?? 'pending') === 'pending').length,
        boarded: passengers.filter((passenger) => passenger.status === 'boarded').length,
        completed: passengers.filter((passenger) => passenger.status === 'completed').length,
        noShow: passengers.filter((passenger) => passenger.status === 'no_show').length,
        totalEvents: passengers.reduce((total, passenger) => total + (passenger.passenger_events?.length ?? 0), 0),
        invalidEvents: passengers.reduce(
            (total, passenger) => total + (passenger.passenger_events?.filter((event) => !event.is_valid).length ?? 0),
            0,
        ),
    };

    const allAttendanceEvents = passengers
        .flatMap((passenger) =>
            (passenger.passenger_events ?? []).map((event) => ({
                passenger,
                event,
            })),
        )
        .sort((left, right) => new Date(right.event.event_time).getTime() - new Date(left.event.event_time).getTime());

    const openAttendanceDialog = (mode: AttendanceMode, passenger: AttendanceTripPassenger, event?: TripPassengerEvent) => {
        setDialogState({ mode, passenger, event });
        setData(buildFormDefaults(mode, passenger, event));
        clearErrors();
    };

    const closeAttendanceDialog = () => {
        setDialogState(null);
        reset();
        clearErrors();
    };

    const submitAttendance = (submitEvent: FormEvent<HTMLFormElement>) => {
        submitEvent.preventDefault();

        if (!dialogState) {
            return;
        }

        let url = '';

        if (dialogState.mode === 'check_in') {
            url = route('trips.passengers.check-in', [trip.id, dialogState.passenger.id]);
        } else if (dialogState.mode === 'check_out') {
            url = route('trips.passengers.check-out', [trip.id, dialogState.passenger.id]);
        } else if (dialogState.mode === 'no_show') {
            url = route('trips.passengers.no-show', [trip.id, dialogState.passenger.id]);
        } else if (dialogState.event) {
            url = route('trips.passengers.events.correct', [trip.id, dialogState.passenger.id, dialogState.event.id]);
        }

        post(url, {
            preserveScroll: true,
            onSuccess: () => closeAttendanceDialog(),
        });
    };

    const openStartTripDialog = () => {
        setStartTripOpen(true);
        clearStartTripErrors();
    };

    const closeStartTripDialog = () => {
        setStartTripOpen(false);
        resetStartTrip();
        clearStartTripErrors();
    };

    const submitStartTrip = (submitEvent: FormEvent<HTMLFormElement>) => {
        submitEvent.preventDefault();

        postStartTrip(route('trips.start', trip.id), {
            preserveScroll: true,
            onSuccess: () => closeStartTripDialog(),
        });
    };

    const openCancelTripDialog = () => {
        setCancelTripOpen(true);
        clearCancelTripErrors();
    };

    const closeCancelTripDialog = () => {
        setCancelTripOpen(false);
        resetCancelTrip();
        clearCancelTripErrors();
    };

    const submitCancelTrip = (submitEvent: FormEvent<HTMLFormElement>) => {
        submitEvent.preventDefault();

        postCancelTrip(route('trips.cancel', trip.id), {
            preserveScroll: true,
            onSuccess: () => closeCancelTripDialog(),
        });
    };

    const openCompleteTripDialog = () => {
        setCompleteTripOpen(true);
        clearCompleteTripErrors();
    };

    const closeCompleteTripDialog = () => {
        setCompleteTripOpen(false);
        resetCompleteTrip();
        clearCompleteTripErrors();
    };

    const submitCompleteTrip = (submitEvent: FormEvent<HTMLFormElement>) => {
        submitEvent.preventDefault();

        postCompleteTrip(route('trips.complete', trip.id), {
            preserveScroll: true,
            onSuccess: () => closeCompleteTripDialog(),
        });
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`Trip ${trip.trip_number}`} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="mb-2 flex items-center gap-3">
                            <h1 className="text-2xl font-bold">{trip.trip_number}</h1>
                            {getStatusBadge(trip.status)}
                            {getPriorityBadge(trip.priority)}
                        </div>
                        {trip.team_number && <p className="text-sm text-gray-600">Team: {trip.team_number}</p>}
                    </div>
                    <TripActionButtons trip={trip} onOpenStartTrip={openStartTripDialog} />
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="h-5 w-5" />
                                    Trip Information
                                </CardTitle>
                                <CardDescription>Basic trip details and schedule</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <label className="text-sm font-medium text-gray-600">Trip Type</label>
                                        <p className="mt-1 text-sm">
                                            {trip.trip_type ? (
                                                <Badge variant="outline" className="capitalize">
                                                    {trip.trip_type.replace('-', ' ')}
                                                </Badge>
                                            ) : (
                                                <span className="text-gray-400">Not specified</span>
                                            )}
                                        </p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-gray-600">Schedule Type</label>
                                        <p className="mt-1 text-sm capitalize">{trip.schedule_type.replace('-', ' ')}</p>
                                    </div>
                                    <div>
                                        <label className="flex items-center gap-1.5 text-sm font-medium text-gray-600">
                                            <Calendar className="h-3.5 w-3.5" />
                                            Scheduled Date
                                        </label>
                                        <p className="mt-1 text-sm font-medium">
                                            {new Date(trip.scheduled_date).toLocaleDateString('en-US', {
                                                weekday: 'long',
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            })}
                                        </p>
                                    </div>
                                    <div>
                                        <label className="flex items-center gap-1.5 text-sm font-medium text-gray-600">
                                            <Clock className="h-3.5 w-3.5" />
                                            Time
                                        </label>
                                        <p className="mt-1 text-sm font-medium">
                                            {trip.scheduled_start_time} - {trip.scheduled_end_time}
                                        </p>
                                    </div>
                                    {(trip.start_location || trip.end_location) && (
                                        <>
                                            <div>
                                                <label className="flex items-center gap-1.5 text-sm font-medium text-gray-600">
                                                    <MapPin className="h-3.5 w-3.5" />
                                                    Start Location
                                                </label>
                                                <p className="mt-1 text-sm">{trip.start_location || '-'}</p>
                                            </div>
                                            <div>
                                                <label className="flex items-center gap-1.5 text-sm font-medium text-gray-600">
                                                    <MapPin className="h-3.5 w-3.5" />
                                                    End Location
                                                </label>
                                                <p className="mt-1 text-sm">{trip.end_location || '-'}</p>
                                            </div>
                                        </>
                                    )}
                                    {trip.department && (
                                        <div>
                                            <label className="text-sm font-medium text-gray-600">Department</label>
                                            <p className="mt-1 text-sm">{trip.department.name}</p>
                                        </div>
                                    )}
                                    {trip.team_number && (
                                        <div>
                                            <label className="text-sm font-medium text-gray-600">Team Number</label>
                                            <p className="mt-1 text-sm font-medium">{trip.team_number}</p>
                                        </div>
                                    )}
                                    <div>
                                        <label className="text-sm font-medium text-gray-600">Return Trip</label>
                                        <p className="mt-1 text-sm">{trip.is_return ? <Badge variant="outline">Yes</Badge> : 'No'}</p>
                                    </div>
                                </div>
                                {trip.description && (
                                    <>
                                        <Separator />
                                        <div>
                                            <label className="text-sm font-medium text-gray-600">Description</label>
                                            <p className="mt-1 text-sm text-gray-700">{trip.description}</p>
                                        </div>
                                    </>
                                )}
                                {trip.remarks && (
                                    <div>
                                        <label className="text-sm font-medium text-gray-600">Remarks</label>
                                        <p className="mt-1 text-sm text-gray-700">{trip.remarks}</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <ClipboardList className="h-5 w-5" />
                                    Attendance Overview
                                </CardTitle>
                                <CardDescription>Operational snapshot and event history health for this trip.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
                                    <div className="rounded-lg border bg-slate-50 p-3">
                                        <p className="text-xs uppercase tracking-wide text-slate-500">Passengers</p>
                                        <p className="mt-2 text-2xl font-semibold">{attendanceSummary.totalPassengers}</p>
                                    </div>
                                    <div className="rounded-lg border bg-emerald-50 p-3">
                                        <p className="text-xs uppercase tracking-wide text-emerald-700">Boarded</p>
                                        <p className="mt-2 text-2xl font-semibold">{attendanceSummary.boarded}</p>
                                    </div>
                                    <div className="rounded-lg border bg-blue-50 p-3">
                                        <p className="text-xs uppercase tracking-wide text-blue-700">Completed</p>
                                        <p className="mt-2 text-2xl font-semibold">{attendanceSummary.completed}</p>
                                    </div>
                                    <div className="rounded-lg border bg-rose-50 p-3">
                                        <p className="text-xs uppercase tracking-wide text-rose-700">No Show</p>
                                        <p className="mt-2 text-2xl font-semibold">{attendanceSummary.noShow}</p>
                                    </div>
                                    <div className="rounded-lg border bg-amber-50 p-3">
                                        <p className="text-xs uppercase tracking-wide text-amber-700">Events</p>
                                        <p className="mt-2 text-2xl font-semibold">{attendanceSummary.totalEvents}</p>
                                    </div>
                                    <div className="rounded-lg border bg-violet-50 p-3">
                                        <p className="text-xs uppercase tracking-wide text-violet-700">Voided</p>
                                        <p className="mt-2 text-2xl font-semibold">{attendanceSummary.invalidEvents}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Car className="h-5 w-5" />
                                    Vehicle & Driver
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {trip.vehicle ? (
                                    <div className="space-y-4">
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100">
                                                <Car className="h-6 w-6 text-blue-600" />
                                            </div>
                                            <div className="flex-1">
                                                <p className="text-sm font-medium text-gray-600">Vehicle</p>
                                                <p className="text-lg font-semibold">{trip.vehicle.registration_number}</p>
                                                <p className="text-sm text-gray-500">{trip.vehicle.brand} {trip.vehicle.model}</p>
                                            </div>
                                        </div>
                                        {trip.driver && (
                                            <>
                                                <Separator />
                                                <div className="flex items-center gap-4">
                                                    <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100">
                                                        <User className="h-6 w-6 text-green-600" />
                                                    </div>
                                                    <div className="flex-1">
                                                        <p className="text-sm font-medium text-gray-600">Driver</p>
                                                        <p className="text-lg font-semibold">{trip.driver.name}</p>
                                                        {trip.driver.official_phone && <p className="text-sm text-gray-500">{trip.driver.official_phone}</p>}
                                                    </div>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                ) : (
                                    <div className="py-8 text-center text-gray-500">
                                        <Car className="mx-auto mb-2 h-12 w-12 opacity-30" />
                                        <p className="text-sm">No vehicle assigned yet</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {passengers.length > 0 && (
                            <PassengerAttendanceCard
                                passengers={passengers}
                                canManageAttendance={canManageAttendance}
                                canCaptureAttendance={canCaptureAttendance}
                                onOpenDialog={openAttendanceDialog}
                                getPassengerStatusBadge={getPassengerStatusBadge}
                                formatDateTime={formatDateTime}
                            />
                        )}

                        <AttendanceHistoryCard
                            allAttendanceEvents={allAttendanceEvents}
                            passengers={passengers}
                            canManageAttendance={canManageAttendance}
                            canCorrectAttendance={canCorrectAttendance}
                            onCorrectEvent={(passenger, event) => openAttendanceDialog('correct', passenger, event)}
                        />

                        {trip.factories && trip.factories.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Building2 className="h-5 w-5" />
                                        Factories ({trip.factories.length})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex flex-wrap gap-2">
                                        {trip.factories.map((factory) => (
                                            <Badge key={factory.id} variant="outline" className="flex items-center gap-1.5">
                                                <Building2 className="h-3 w-3" />
                                                {factory.name}
                                            </Badge>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {trip.departments && trip.departments.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Users className="h-5 w-5" />
                                        Headcount by Department
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex flex-wrap gap-2">
                                        {trip.departments.map((dept: TripDepartmentWithHeadcount) => (
                                            <div key={dept.id} className="flex items-center gap-2 rounded-full border bg-muted/30 px-3 py-1.5">
                                                <Users className="h-3.5 w-3.5 text-muted-foreground" />
                                                <span className="text-sm font-medium">{dept.name}</span>
                                                <span className="text-sm text-muted-foreground">× {dept.pivot?.count}</span>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {trip.logistics && trip.logistics.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Building2 className="h-5 w-5" />
                                        Logistics ({trip.logistics.length})
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex flex-wrap gap-2">
                                        {trip.logistics.map((log) => (
                                            <Badge key={log.id} variant="secondary">
                                                {log.name}
                                            </Badge>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {(trip.comments || trip.notes) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <MessageSquare className="h-5 w-5" />
                                        Comments & Notes
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {trip.comments && (
                                        <div>
                                            <label className="text-sm font-medium text-gray-600">Comments</label>
                                            <p className="mt-1 text-sm text-gray-700">{trip.comments}</p>
                                        </div>
                                    )}
                                    {trip.notes && (
                                        <div>
                                            <label className="text-sm font-medium text-gray-600">Additional Notes</label>
                                            <p className="mt-1 text-sm text-gray-700">{trip.notes}</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Request Info</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <label className="text-xs font-medium text-gray-600">Requested By</label>
                                    <p className="mt-1 text-sm font-medium">{trip.requester?.name}</p>
                                    <p className="text-xs text-gray-500">{new Date(trip.created_at).toLocaleDateString()}</p>
                                </div>
                                {trip.approved_by && trip.approver && (
                                    <>
                                        <Separator />
                                        <div>
                                            <label className="text-xs font-medium text-gray-600">Approved By</label>
                                            <p className="mt-1 text-sm font-medium">{trip.approver.name}</p>
                                        </div>
                                    </>
                                )}
                                {trip.status === 'rejected' && trip.rejection_reason && (
                                    <>
                                        <Separator />
                                        <div>
                                            <label className="text-xs font-medium text-gray-600">Rejection Reason</label>
                                            <p className="mt-1 text-sm text-gray-700">{trip.rejection_reason}</p>
                                        </div>
                                    </>
                                )}
                                {trip.status === 'cancelled' && (
                                    <>
                                        <Separator />
                                        <div className="space-y-1">
                                            <label className="text-xs font-medium text-gray-600">Cancelled By</label>
                                            <p className="mt-1 text-sm font-medium">{trip.cancelled_by_user?.name ?? 'Unknown'}</p>
                                            {trip.cancellation_reason && (
                                                <p className="text-sm capitalize text-gray-700">{trip.cancellation_reason.replace(/_/g, ' ')}</p>
                                            )}
                                            {trip.cancellation_notes && <p className="text-xs text-gray-500">{trip.cancellation_notes}</p>}
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        {(['pending', 'approved', 'assigned', 'in_progress'] as Trip['status'][]).includes(trip.status) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Actions</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {trip.status === 'pending' && (
                                        <>
                                            <Button onClick={() => router.post(route('trips.approve', trip.id))} className="w-full bg-green-600 hover:bg-green-700">
                                                <CheckCircle className="mr-2 h-4 w-4" />
                                                Approve Trip
                                            </Button>
                                            <Button
                                                variant="outline"
                                                className="w-full"
                                                onClick={() => {
                                                    const reason = prompt('Rejection reason:');
                                                    if (reason) {
                                                        router.post(route('trips.reject', trip.id), { rejection_reason: reason });
                                                    }
                                                }}
                                            >
                                                <XCircle className="mr-2 h-4 w-4" />
                                                Reject Trip
                                            </Button>
                                        </>
                                    )}

                                    {canStartTrip(trip.status) && (
                                        <Button
                                            className="w-full bg-green-600 hover:bg-green-700"
                                            onClick={openStartTripDialog}
                                            disabled={startTripProcessing}
                                        >
                                            <Play className="mr-2 h-4 w-4" />
                                            Start Trip
                                        </Button>
                                    )}

                                    {canCompleteTrip(trip.status) && (canEditTrips || isAssignedDriver) && (
                                        <Button
                                            className="w-full bg-green-600 hover:bg-green-700"
                                            onClick={openCompleteTripDialog}
                                            disabled={completeTripProcessing}
                                        >
                                            <CheckCircle2 className="mr-2 h-4 w-4" />
                                            Complete Trip
                                        </Button>
                                    )}

                                    {canCancelTrip(trip.status) && canEditTrips && (
                                        <Button
                                            variant="outline"
                                            className="w-full border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700"
                                            onClick={openCancelTripDialog}
                                            disabled={cancelTripProcessing}
                                        >
                                            <Ban className="mr-2 h-4 w-4" />
                                            Cancel Trip
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <MessageSquareWarning className="h-4 w-4" />
                                    Feedback & Complaints
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {(trip.feedbackEntries ?? []).length === 0 && (
                                    <p className="text-sm text-gray-500">Nothing submitted for this trip yet.</p>
                                )}
                                {(trip.feedbackEntries ?? []).map((entry) => (
                                    <div key={entry.id} className="rounded-md border p-2">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className="text-sm font-medium line-clamp-1">{entry.subject}</span>
                                            <Badge variant="outline" className="shrink-0 text-xs capitalize">{entry.status.replace('_', ' ')}</Badge>
                                        </div>
                                        <p className="mt-1 text-xs capitalize text-gray-500">{entry.type}</p>
                                    </div>
                                ))}
                                {canCreateComplaint && (
                                    <Button
                                        variant="outline"
                                        className="w-full"
                                        onClick={() => router.visit(route('complaints.create', { trip_id: trip.id }))}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        Submit Feedback / Complaint
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {vehicleAssignments.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Vehicle Assignment History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded border">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left">Vehicle</th>
                                            <th className="px-3 py-2 text-left">Assigned At</th>
                                            <th className="px-3 py-2 text-left">Unassigned At</th>
                                            <th className="px-3 py-2 text-left">Assigned By</th>
                                            <th className="px-3 py-2 text-left">Reason</th>
                                            <th className="px-3 py-2 text-left">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {vehicleAssignments.map((a) => (
                                            <tr key={a.id} className="border-t">
                                                <td className="px-3 py-2">{a.vehicle?.registration_number ?? '-'}</td>
                                                <td className="px-3 py-2">{new Date(a.assigned_at).toLocaleString()}</td>
                                                <td className="px-3 py-2">{a.unassigned_at ? new Date(a.unassigned_at).toLocaleString() : '-'}</td>
                                                <td className="px-3 py-2">{a.assigned_by_user?.name ?? '-'}</td>
                                                <td className="px-3 py-2 capitalize">{a.reason ?? '-'}</td>
                                                <td className="px-3 py-2">
                                                    <span className={`inline-flex items-center rounded px-2 py-0.5 text-xs ${a.is_current ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'}`}>
                                                        {a.is_current ? 'Current' : 'Past'}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {routeAssignments.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Route Assignment History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded border">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left">Route</th>
                                            <th className="px-3 py-2 text-left">Assigned At</th>
                                            <th className="px-3 py-2 text-left">Unassigned At</th>
                                            <th className="px-3 py-2 text-left">Assigned By</th>
                                            <th className="px-3 py-2 text-left">Reason</th>
                                            <th className="px-3 py-2 text-left">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {routeAssignments.map((a) => (
                                            <tr key={a.id} className="border-t">
                                                <td className="px-3 py-2">{a.vehicle_route?.name ?? '-'}</td>
                                                <td className="px-3 py-2">{new Date(a.assigned_at).toLocaleString()}</td>
                                                <td className="px-3 py-2">{a.unassigned_at ? new Date(a.unassigned_at).toLocaleString() : '-'}</td>
                                                <td className="px-3 py-2">{a.assigned_by_user?.name ?? '-'}</td>
                                                <td className="px-3 py-2">{a.reason ?? '-'}</td>
                                                <td className="px-3 py-2">
                                                    <span className={`inline-flex items-center rounded px-2 py-0.5 text-xs ${a.is_current ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'}`}>
                                                        {a.is_current ? 'Current' : 'Past'}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {auditLogs.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Audit Log</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded border">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left">When</th>
                                            <th className="px-3 py-2 text-left">Action</th>
                                            <th className="px-3 py-2 text-left">By</th>
                                            <th className="px-3 py-2 text-left">Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {auditLogs.map((log) => (
                                            <tr key={log.id} className="border-t align-top">
                                                <td className="px-3 py-2 whitespace-nowrap">{new Date(log.created_at).toLocaleString()}</td>
                                                <td className="px-3 py-2">{formatAuditAction(log.action)}</td>
                                                <td className="px-3 py-2">{log.user?.name ?? '-'}</td>
                                                <td className="px-3 py-2">{log.reason ?? '-'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

            <Dialog open={startTripOpen} onOpenChange={(open) => !open && closeStartTripDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Start Trip</DialogTitle>
                        <DialogDescription>You can enter current odometer reading now, or skip and add it later.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitStartTrip}>
                        <div className="space-y-2">
                            <Label htmlFor="odometer_start">Odometer start (optional)</Label>
                            <Input
                                id="odometer_start"
                                type="number"
                                min="0"
                                step="0.01"
                                value={startTripData.odometer_start}
                                onChange={(event) => setStartTripData('odometer_start', event.target.value)}
                                placeholder="Leave blank if not available"
                            />
                            <InputError message={startTripErrors.odometer_start} />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeStartTripDialog} disabled={startTripProcessing}>
                                Cancel
                            </Button>
                            <Button type="submit" className="bg-green-600 hover:bg-green-700" disabled={startTripProcessing}>
                                <Play className="mr-2 h-4 w-4" />
                                {startTripProcessing ? 'Starting...' : 'Start Trip'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={completeTripOpen} onOpenChange={(open) => !open && closeCompleteTripDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Complete Trip</DialogTitle>
                        <DialogDescription>Record the ending odometer reading and any costs incurred.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitCompleteTrip}>
                        <div className="space-y-2">
                            <Label htmlFor="odometer_end">Odometer end (optional)</Label>
                            <Input
                                id="odometer_end"
                                type="number"
                                min={trip.odometer_start ?? 0}
                                step="0.01"
                                value={completeTripData.odometer_end}
                                onChange={(event) => setCompleteTripData('odometer_end', event.target.value)}
                                placeholder="Leave blank if not available"
                            />
                            <InputError message={completeTripErrors.odometer_end} />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="fuel_consumed">Fuel consumed</Label>
                                <Input
                                    id="fuel_consumed"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={completeTripData.fuel_consumed}
                                    onChange={(event) => setCompleteTripData('fuel_consumed', event.target.value)}
                                    placeholder="Optional"
                                />
                                <InputError message={completeTripErrors.fuel_consumed} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="fuel_cost">Fuel cost</Label>
                                <Input
                                    id="fuel_cost"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={completeTripData.fuel_cost}
                                    onChange={(event) => setCompleteTripData('fuel_cost', event.target.value)}
                                    placeholder="Optional"
                                />
                                <InputError message={completeTripErrors.fuel_cost} />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="other_costs">Other costs</Label>
                            <Input
                                id="other_costs"
                                type="number"
                                min="0"
                                step="0.01"
                                value={completeTripData.other_costs}
                                onChange={(event) => setCompleteTripData('other_costs', event.target.value)}
                                placeholder="Optional"
                            />
                            <InputError message={completeTripErrors.other_costs} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="complete_notes">Notes</Label>
                            <Textarea
                                id="complete_notes"
                                value={completeTripData.notes}
                                onChange={(event) => setCompleteTripData('notes', event.target.value)}
                                placeholder="Optional"
                                rows={3}
                            />
                            <InputError message={completeTripErrors.notes} />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeCompleteTripDialog} disabled={completeTripProcessing}>
                                Cancel
                            </Button>
                            <Button type="submit" className="bg-green-600 hover:bg-green-700" disabled={completeTripProcessing}>
                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                {completeTripProcessing ? 'Completing...' : 'Complete Trip'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={cancelTripOpen} onOpenChange={(open) => !open && closeCancelTripDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Cancel Trip</DialogTitle>
                        <DialogDescription>This will mark the trip as cancelled. Choose a reason.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitCancelTrip}>
                        <div className="space-y-2">
                            <Label htmlFor="cancellation_reason">Cancellation reason</Label>
                            <Select
                                value={cancelTripData.cancellation_reason}
                                onValueChange={(value) => setCancelTripData('cancellation_reason', value)}
                            >
                                <SelectTrigger id="cancellation_reason" className="w-full">
                                    <SelectValue placeholder="Select a reason" />
                                </SelectTrigger>
                                <SelectContent>
                                    {CANCELLATION_REASONS.map((reason) => (
                                        <SelectItem key={reason.value} value={reason.value}>
                                            {reason.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={cancelTripErrors.cancellation_reason} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="cancellation_notes">Notes</Label>
                            <Textarea
                                id="cancellation_notes"
                                value={cancelTripData.cancellation_notes}
                                onChange={(event) => setCancelTripData('cancellation_notes', event.target.value)}
                                placeholder="Optional"
                                rows={3}
                            />
                            <InputError message={cancelTripErrors.cancellation_notes} />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeCancelTripDialog} disabled={cancelTripProcessing}>
                                Keep Trip
                            </Button>
                            <Button type="submit" variant="destructive" disabled={cancelTripProcessing || !cancelTripData.cancellation_reason}>
                                <Ban className="mr-2 h-4 w-4" />
                                {cancelTripProcessing ? 'Cancelling...' : 'Cancel Trip'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={dialogState !== null} onOpenChange={(open) => !open && closeAttendanceDialog()}>
                <DialogContent className="sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>
                            {dialogState?.mode === 'check_in' && 'Record Check-In'}
                            {dialogState?.mode === 'check_out' && 'Record Check-Out'}
                            {dialogState?.mode === 'no_show' && 'Record No-Show'}
                            {dialogState?.mode === 'correct' && 'Correct Attendance Event'}
                        </DialogTitle>
                        <DialogDescription>
                            {dialogState?.passenger.user?.name ?? 'Passenger'} for trip {trip.trip_number}
                        </DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitAttendance}>
                        {dialogState?.mode === 'correct' && (
                            <div className="space-y-2">
                                <Label htmlFor="event_type">Replacement event type</Label>
                                <select
                                    id="event_type"
                                    value={data.event_type}
                                    onChange={(event) => setData('event_type', event.target.value as AttendanceFormShape['event_type'])}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="check_in">Check In</option>
                                    <option value="check_out">Check Out</option>
                                    <option value="no_show">No Show</option>
                                    <option value="manual_override">Manual Override</option>
                                </select>
                                <InputError message={errors.event_type} />
                            </div>
                        )}

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="event_time">Event time</Label>
                                <Input id="event_time" type="datetime-local" value={data.event_time} onChange={(event) => setData('event_time', event.target.value)} />
                                <InputError message={errors.event_time} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="stop_id">Stop</Label>
                                <select
                                    id="stop_id"
                                    value={data.stop_id}
                                    onChange={(event) => setData('stop_id', event.target.value)}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">No linked stop</option>
                                    {dialogState?.passenger.pickup_stop_id && dialogState.passenger.pickup_stop && (
                                        <option value={dialogState.passenger.pickup_stop_id}>Pickup: {dialogState.passenger.pickup_stop.name}</option>
                                    )}
                                    {dialogState?.passenger.dropoff_stop_id && dialogState.passenger.dropoff_stop && dialogState.passenger.dropoff_stop_id !== dialogState.passenger.pickup_stop_id && (
                                        <option value={dialogState.passenger.dropoff_stop_id}>Dropoff: {dialogState.passenger.dropoff_stop.name}</option>
                                    )}
                                </select>
                                <InputError message={errors.stop_id} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="area_name">Area or landmark</Label>
                                <Input id="area_name" value={data.area_name} onChange={(event) => setData('area_name', event.target.value)} placeholder="Factory gate, terminal, checkpoint" />
                                <InputError message={errors.area_name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="device_id">Device ID</Label>
                                <Input id="device_id" value={data.device_id} onChange={(event) => setData('device_id', event.target.value)} placeholder="Driver phone or scanner ID" />
                                <InputError message={errors.device_id} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="latitude">Latitude</Label>
                                <Input id="latitude" value={data.latitude} onChange={(event) => setData('latitude', event.target.value)} placeholder="23.8103" />
                                <InputError message={errors.latitude} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="longitude">Longitude</Label>
                                <Input id="longitude" value={data.longitude} onChange={(event) => setData('longitude', event.target.value)} placeholder="90.4125" />
                                <InputError message={errors.longitude} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="gps_accuracy_meters">GPS accuracy (meters)</Label>
                                <Input id="gps_accuracy_meters" value={data.gps_accuracy_meters} onChange={(event) => setData('gps_accuracy_meters', event.target.value)} placeholder="15" />
                                <InputError message={errors.gps_accuracy_meters} />
                            </div>
                            {dialogState?.mode !== 'correct' && (
                                <div className="space-y-2">
                                    <Label htmlFor="idempotency_key">Idempotency key</Label>
                                    <Input id="idempotency_key" value={data.idempotency_key} onChange={(event) => setData('idempotency_key', event.target.value)} placeholder="Optional retry key" />
                                    <InputError message={errors.idempotency_key} />
                                </div>
                            )}
                        </div>

                        {dialogState?.mode === 'correct' && (
                            <div className="space-y-2">
                                <Label htmlFor="reason">Correction reason</Label>
                                <Textarea id="reason" value={data.reason} onChange={(event) => setData('reason', event.target.value)} placeholder="Why is the original event being corrected?" rows={4} />
                                <InputError message={errors.reason} />
                            </div>
                        )}

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeAttendanceDialog}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Saving...' : dialogState?.mode === 'correct' ? 'Apply Correction' : 'Save Event'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppSidebarLayout>
    );
}
