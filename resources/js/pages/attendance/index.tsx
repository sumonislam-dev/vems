import { PageHeader } from '@/base-components/page-header';
import { AttendanceStatusCard } from '@/components/attendance-status-card';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem } from '@/types';
import type { AttendanceRecord, AttendanceStatusSummary, Factory } from '@/types/attendance';
import { Head } from '@inertiajs/react';

interface AttendancePageProps {
    attendanceMode: 'biometric' | 'self_service';
    today: AttendanceRecord | null;
    history: AttendanceRecord[];
    factories: Factory[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Attendance', href: '/attendance' },
];

const EVENT_TYPE_LABELS: Record<string, string> = {
    check_in: 'Check In',
    check_out: 'Check Out',
    break_start: 'Break Start',
    break_end: 'Break End',
    correction: 'Correction',
};

function formatTime(value: string | null) {
    if (!value) return '—';
    return new Date(value).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

function formatDuration(minutes: number | null) {
    if (minutes === null) return '—';
    return `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
}

function toStatusSummary(record: AttendanceRecord | null, mode: 'biometric' | 'self_service'): AttendanceStatusSummary {
    return {
        attendance_mode: mode,
        status: record?.status ?? 'not_checked_in',
        check_in_at: record?.check_in_at ?? null,
        check_out_at: record?.check_out_at ?? null,
        break_minutes: record?.break_minutes ?? 0,
        net_minutes: record?.net_minutes ?? null,
        overtime_minutes: record?.overtime_minutes ?? 0,
        trip: record?.trip ? { trip_number: record.trip.trip_number, stage: 'pickup', stop_name: null } : null,
    };
}

export default function AttendanceIndex({ attendanceMode, today, history, factories }: AttendancePageProps) {
    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Attendance" />
            <div className="flex flex-col gap-6 p-6">
                <PageHeader title="Attendance" description="Your daily check-in, breaks, and check-out history" />

                <AttendanceStatusCard status={toStatusSummary(today, attendanceMode)} factories={factories} />

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm font-semibold">Recent history</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b bg-muted/50">
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Date</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Check In</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Check Out</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Break</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Net</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Overtime</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Source</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Trip</th>
                                        <th className="px-4 py-3 text-left font-medium text-muted-foreground">Anomaly</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {history.length === 0 ? (
                                        <tr>
                                            <td colSpan={9} className="px-4 py-12 text-center text-muted-foreground">
                                                No attendance history yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        history.map((record) => (
                                            <tr key={record.id} className="border-b hover:bg-muted/30 transition-colors">
                                                <td className="px-4 py-3 whitespace-nowrap">{record.work_date}</td>
                                                <td className="px-4 py-3">{formatTime(record.check_in_at)}</td>
                                                <td className="px-4 py-3">{formatTime(record.check_out_at)}</td>
                                                <td className="px-4 py-3">{record.break_minutes}m</td>
                                                <td className="px-4 py-3">{formatDuration(record.net_minutes)}</td>
                                                <td className="px-4 py-3">{record.overtime_minutes}m</td>
                                                <td className="px-4 py-3 text-xs capitalize">{record.source ?? '—'}</td>
                                                <td className="px-4 py-3 text-xs">{record.trip?.trip_number ?? '—'}</td>
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
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                {today && today.events.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-semibold">Today's event trail</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {today.events.map((event) => (
                                <div key={event.id} className="flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                                    <div className="flex items-center gap-2">
                                        <Badge variant={event.is_valid ? 'outline' : 'destructive'}>
                                            {EVENT_TYPE_LABELS[event.event_type] ?? event.event_type}
                                        </Badge>
                                        <span>{formatTime(event.event_time)}</span>
                                        {event.location_name && <span className="text-muted-foreground">{event.location_name}</span>}
                                    </div>
                                    {!event.is_valid && event.void_reason && (
                                        <span className="text-xs text-muted-foreground">{event.void_reason}</span>
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppSidebarLayout>
    );
}
