import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { TripPassenger, TripPassengerEvent } from '@/types';
import { Users } from 'lucide-react';
import type { JSX } from 'react';

type AttendanceMode = 'check_in' | 'check_out' | 'no_show' | 'correct';

export type AttendanceTripPassenger = TripPassenger & {
    passenger_events?: TripPassengerEvent[];
};

interface PassengerAttendanceCardProps {
    passengers: AttendanceTripPassenger[];
    canManageAttendance: boolean;
    canCaptureAttendance: boolean;
    onOpenDialog: (mode: AttendanceMode, passenger: AttendanceTripPassenger) => void;
    getPassengerStatusBadge: (status?: string) => JSX.Element;
    formatDateTime: (value?: string | null) => string;
}

export function PassengerAttendanceCard({
    passengers,
    canManageAttendance,
    canCaptureAttendance,
    onOpenDialog,
    getPassengerStatusBadge,
    formatDateTime,
}: PassengerAttendanceCardProps) {
    if (passengers.length === 0) return null;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Users className="h-5 w-5" />
                    Passenger Attendance
                </CardTitle>
                <CardDescription>Capture real pickup and drop events, then correct mistakes without losing history.</CardDescription>
            </CardHeader>
            <CardContent>
                <div className="space-y-4">
                    {passengers.map((passenger, index) => {
                        const orderedEvents = [...(passenger.passenger_events ?? [])].sort(
                            (left, right) => new Date(right.event_time).getTime() - new Date(left.event_time).getTime(),
                        );
                        const latestEvent = orderedEvents[0];

                        return (
                            <div key={passenger.id} className="rounded-xl border p-4">
                                <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div className="flex items-start gap-3">
                                        <div className="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white">
                                            {index + 1}
                                        </div>
                                        <div className="space-y-2">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="text-sm font-semibold">{passenger.user?.name ?? 'Unnamed passenger'}</p>
                                                {getPassengerStatusBadge(passenger.status)}
                                                <Badge variant="secondary">{orderedEvents.length} events</Badge>
                                            </div>
                                            {passenger.user?.employee_id && (
                                                <p className="text-xs text-gray-500">Employee ID: {passenger.user.employee_id}</p>
                                            )}
                                            <div className="grid grid-cols-1 gap-3 text-sm text-gray-600 md:grid-cols-2">
                                                <p>
                                                    Planned pickup: <span className="font-medium text-gray-900">{passenger.pickup_stop?.name ?? 'Not set'}</span>
                                                </p>
                                                <p>
                                                    Planned dropoff: <span className="font-medium text-gray-900">{passenger.dropoff_stop?.name ?? 'Not set'}</span>
                                                </p>
                                                <p>
                                                    Actual check-in: <span className="font-medium text-gray-900">{formatDateTime(passenger.boarded_at)}</span>
                                                </p>
                                                <p>
                                                    Actual check-out: <span className="font-medium text-gray-900">{formatDateTime(passenger.dropped_at)}</span>
                                                </p>
                                            </div>
                                            {latestEvent && (
                                                <p className="text-xs text-gray-500">
                                                    Latest event: {latestEvent.event_type.replace('_', ' ')} at {formatDateTime(latestEvent.event_time)}
                                                    {latestEvent.area_name ? ` from ${latestEvent.area_name}` : ''}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                    {canManageAttendance && canCaptureAttendance && (
                                        <div className="flex flex-wrap gap-2 lg:max-w-xs lg:justify-end">
                                            <Button size="sm" onClick={() => onOpenDialog('check_in', passenger)}>
                                                Check In
                                            </Button>
                                            <Button size="sm" variant="outline" onClick={() => onOpenDialog('check_out', passenger)}>
                                                Check Out
                                            </Button>
                                            <Button size="sm" variant="outline" onClick={() => onOpenDialog('no_show', passenger)}>
                                                No Show
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </CardContent>
        </Card>
    );
}
