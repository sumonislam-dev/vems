import { AttendanceStatusCard } from '@/components/attendance-status-card';
import { MyComplaintsCard, type MyComplaintsCardProps } from '@/components/my-complaints-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { hasPermission } from '@/lib/permissions';
import type { SharedData } from '@/types';
import type { AttendanceStatusSummary, Factory } from '@/types/attendance';
import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle, Car, CheckCircle2, ListChecks, MessageSquarePlus } from 'lucide-react';

interface DriverTripSummary {
    id: number;
    trip_number: string;
    scheduled_date: string;
    scheduled_start_time: string | null;
    schedule_type: string;
    status: string;
}

export interface DriverDashboardProps {
    attendanceStatus: AttendanceStatusSummary | null;
    factories: Factory[];
    myTrips: {
        counts: { today: number; in_progress: number; completed_today: number };
        today_trips: DriverTripSummary[];
        upcoming: DriverTripSummary[];
    };
    myComplaints: MyComplaintsCardProps['complaints'];
    license: { status: 'valid' | 'expiring_soon' | 'expired' | 'not_provided'; expiry_date: string | null };
    driverStatus: string;
}

const tripStatusColor = (status: string) => {
    switch (status) {
        case 'pending':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-500/15 dark:text-orange-300';
        case 'approved':
        case 'assigned':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300';
        case 'in_progress':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/15 dark:text-yellow-300';
        case 'completed':
            return 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300';
        default:
            return 'bg-slate-100 text-slate-800 dark:bg-slate-500/15 dark:text-slate-300';
    }
};

const driverStatusColor = (status: string) => {
    switch (status) {
        case 'available':
            return 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300';
        case 'on_trip':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300';
        case 'on_leave':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-500/15 dark:text-orange-300';
        default:
            return 'bg-slate-100 text-slate-800 dark:bg-slate-500/15 dark:text-slate-300';
    }
};

function TripListItem({ trip }: { trip: DriverTripSummary }) {
    return (
        <Link
            href={route('trips.show', trip.id)}
            className="flex items-center justify-between rounded-lg border p-3 transition-shadow hover:shadow-sm"
        >
            <div>
                <p className="text-sm font-medium text-foreground">{trip.trip_number}</p>
                <p className="text-xs text-muted-foreground">
                    {trip.scheduled_date}
                    {trip.scheduled_start_time ? ` • ${trip.scheduled_start_time}` : ''} • {trip.schedule_type}
                </p>
            </div>
            <Badge className={tripStatusColor(trip.status)}>{trip.status.replace('_', ' ')}</Badge>
        </Link>
    );
}

export function DriverDashboard({ attendanceStatus, factories, myTrips, myComplaints, license, driverStatus }: DriverDashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const permissions = auth.permissions ?? [];

    return (
        <div className="space-y-6 p-6">
            {attendanceStatus && <AttendanceStatusCard status={attendanceStatus} factories={factories} />}

            {license.status !== 'valid' && (
                <Card className="border-orange-300 dark:border-orange-500/40">
                    <CardContent className="flex items-center gap-2 py-4 text-sm text-orange-700 dark:text-orange-300">
                        <AlertTriangle className="h-4 w-4 shrink-0" />
                        {license.status === 'not_provided' && "Your driving license details haven't been provided yet."}
                        {license.status === 'expiring_soon' && `Your driving license expires soon (${license.expiry_date}).`}
                        {license.status === 'expired' && `Your driving license expired on ${license.expiry_date}.`}
                    </CardContent>
                </Card>
            )}

            <div className="flex items-center justify-between">
                <div>
                    <h1 className="text-3xl font-bold text-foreground">My Dashboard</h1>
                    <p className="text-sm text-muted-foreground">Your assigned trips, attendance, and complaints.</p>
                </div>
                <Badge variant="outline" className={driverStatusColor(driverStatus)}>
                    {driverStatus.replace('_', ' ')}
                </Badge>
            </div>

            <div className="flex flex-wrap gap-3">
                <Button asChild>
                    <Link href={route('trips.index')}>
                        <Car className="mr-2 h-4 w-4" />
                        View My Trips
                    </Link>
                </Button>
                {hasPermission(permissions, 'create-complaints') && (
                    <Button asChild variant="outline">
                        <Link href={route('complaints.create')}>
                            <MessageSquarePlus className="mr-2 h-4 w-4" />
                            Submit Feedback/Complaint
                        </Link>
                    </Button>
                )}
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center">
                                <ListChecks className="mr-2 h-5 w-5" />
                                Today's Trips
                            </CardTitle>
                            <CardDescription>Trips assigned to your vehicle today</CardDescription>
                        </div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={route('trips.index')}>View all</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-3 gap-4 text-center">
                            <div className="rounded-lg border p-3">
                                <p className="text-2xl font-bold text-foreground">{myTrips.counts.today}</p>
                                <p className="text-xs text-muted-foreground">Today</p>
                            </div>
                            <div className="rounded-lg border p-3">
                                <p className="text-2xl font-bold text-foreground">{myTrips.counts.in_progress}</p>
                                <p className="text-xs text-muted-foreground">In progress</p>
                            </div>
                            <div className="rounded-lg border p-3">
                                <p className="text-2xl font-bold text-foreground">{myTrips.counts.completed_today}</p>
                                <p className="text-xs text-muted-foreground">Completed today</p>
                            </div>
                        </div>

                        {myTrips.today_trips.length > 0 ? (
                            <div className="space-y-2">
                                {myTrips.today_trips.map((trip) => (
                                    <TripListItem key={trip.id} trip={trip} />
                                ))}
                            </div>
                        ) : (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <CheckCircle2 className="h-4 w-4" />
                                No trips scheduled for today.
                            </p>
                        )}

                        {myTrips.upcoming.length > 0 && (
                            <div className="pt-2">
                                <p className="mb-2 text-xs font-medium text-muted-foreground">Upcoming</p>
                                <div className="space-y-2">
                                    {myTrips.upcoming.map((trip) => (
                                        <TripListItem key={trip.id} trip={trip} />
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <MyComplaintsCard complaints={myComplaints} />
            </div>
        </div>
    );
}
