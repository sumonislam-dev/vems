import { Link, usePage } from '@inertiajs/react';
import { CalendarPlus, Car, CheckCircle2, Clock, MessageSquarePlus } from 'lucide-react';
import { AttendanceStatusCard } from '@/components/attendance-status-card';
import { MyComplaintsCard, type MyComplaintsCardProps } from '@/components/my-complaints-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { hasPermission } from '@/lib/permissions';
import type { SharedData } from '@/types';
import type { AttendanceStatusSummary, Factory } from '@/types/attendance';

interface MyTripSummary {
    id: number;
    trip_number: string;
    scheduled_date: string;
    schedule_type: string;
    status: string;
}

export interface EmployeeDashboardProps {
    attendanceStatus: AttendanceStatusSummary | null;
    factories: Factory[];
    myTrips: {
        counts: { pending: number; upcoming: number; in_progress: number };
        upcoming: MyTripSummary[];
    };
    myComplaints: MyComplaintsCardProps['complaints'];
}

const tripStatusColor = (status: string) => {
    switch (status) {
        case 'pending': return 'bg-orange-100 text-orange-800 dark:bg-orange-500/15 dark:text-orange-300';
        case 'approved':
        case 'assigned': return 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-300';
        case 'in_progress': return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-500/15 dark:text-yellow-300';
        case 'completed': return 'bg-green-100 text-green-800 dark:bg-green-500/15 dark:text-green-300';
        default: return 'bg-slate-100 text-slate-800 dark:bg-slate-500/15 dark:text-slate-300';
    }
};

export function EmployeeDashboard({ attendanceStatus, factories, myTrips, myComplaints }: EmployeeDashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const permissions = auth.permissions ?? [];

    return (
        <div className="p-6 space-y-6">
            {attendanceStatus && <AttendanceStatusCard status={attendanceStatus} factories={factories} />}

            <div>
                <h1 className="text-3xl font-bold text-foreground">My Dashboard</h1>
                <p className="text-sm text-muted-foreground">A quick look at your trips, complaints, and attendance.</p>
            </div>

            {/* Quick actions */}
            <div className="flex flex-wrap gap-3">
                {hasPermission(permissions, 'create-trips') && (
                    <Button asChild>
                        <Link href={route('trips.create')}>
                            <CalendarPlus className="w-4 h-4 mr-2" />
                            Request a Trip
                        </Link>
                    </Button>
                )}
                {hasPermission(permissions, 'create-complaints') && (
                    <Button asChild variant="outline">
                        <Link href={route('complaints.create')}>
                            <MessageSquarePlus className="w-4 h-4 mr-2" />
                            Submit Feedback/Complaint
                        </Link>
                    </Button>
                )}
            </div>

            {/* My Trips / My Complaints summary cards */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <Card className="lg:col-span-2">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center">
                                <Car className="w-5 h-5 mr-2" />
                                My Trips
                            </CardTitle>
                            <CardDescription>Trips you requested, are riding, or are driving</CardDescription>
                        </div>
                        <Button asChild variant="ghost" size="sm">
                            <Link href={route('trips.index')}>View all</Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-3 gap-4 text-center">
                            <div className="rounded-lg border p-3">
                                <p className="text-2xl font-bold text-foreground">{myTrips.counts.pending}</p>
                                <p className="text-xs text-muted-foreground">Pending approval</p>
                            </div>
                            <div className="rounded-lg border p-3">
                                <p className="text-2xl font-bold text-foreground">{myTrips.counts.upcoming}</p>
                                <p className="text-xs text-muted-foreground">Upcoming</p>
                            </div>
                            <div className="rounded-lg border p-3">
                                <p className="text-2xl font-bold text-foreground">{myTrips.counts.in_progress}</p>
                                <p className="text-xs text-muted-foreground">In progress</p>
                            </div>
                        </div>

                        {myTrips.upcoming.length > 0 ? (
                            <div className="space-y-2">
                                {myTrips.upcoming.map((trip) => (
                                    <Link
                                        key={trip.id}
                                        href={route('trips.show', trip.id)}
                                        className="flex items-center justify-between p-3 border rounded-lg hover:shadow-sm transition-shadow"
                                    >
                                        <div>
                                            <p className="text-sm font-medium text-foreground">{trip.trip_number}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {trip.scheduled_date} • {trip.schedule_type}
                                            </p>
                                        </div>
                                        <Badge className={tripStatusColor(trip.status)}>
                                            {trip.status.replace('_', ' ')}
                                        </Badge>
                                    </Link>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground flex items-center gap-2">
                                <CheckCircle2 className="w-4 h-4" />
                                No upcoming trips.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <MyComplaintsCard complaints={myComplaints} />
            </div>

            {hasPermission(permissions, 'capture-own-attendance') && !attendanceStatus && (
                <Card>
                    <CardContent className="flex items-center gap-2 py-4 text-sm text-muted-foreground">
                        <Clock className="w-4 h-4" />
                        <Link href={route('attendance.index')} className="underline">
                            Go to Attendance
                        </Link>
                        to check in for today.
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
