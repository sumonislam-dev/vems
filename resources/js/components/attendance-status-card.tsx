import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { CalendarClock, Clock, Coffee, LogIn, LogOut } from 'lucide-react';
import type { AttendanceStatusSummary, Factory } from '@/types/attendance';

interface AttendanceStatusCardProps {
    status: AttendanceStatusSummary;
    factories: Factory[];
}

function getCurrentPosition(): Promise<{ latitude?: number; longitude?: number; gps_accuracy_meters?: number }> {
    return new Promise((resolve) => {
        if (!navigator.geolocation) {
            resolve({});
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) =>
                resolve({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    gps_accuracy_meters: position.coords.accuracy,
                }),
            () => resolve({}),
            { timeout: 5000 },
        );
    });
}

function formatTime(value: string | null): string {
    if (!value) return '';
    return new Date(value).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

function formatDuration(minutes: number | null): string {
    if (!minutes && minutes !== 0) return '—';
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
}

export function AttendanceStatusCard({ status, factories }: AttendanceStatusCardProps) {
    const [checkoutOpen, setCheckoutOpen] = useState(false);
    const [factoryId, setFactoryId] = useState<string>('');
    const [locationName, setLocationName] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const post = async (url: string, extra: Record<string, unknown> = {}) => {
        setSubmitting(true);
        const gps = await getCurrentPosition();
        router.post(
            url,
            {
                ...gps,
                idempotency_key: crypto.randomUUID(),
                ...extra,
            },
            {
                preserveScroll: true,
                onFinish: () => setSubmitting(false),
            },
        );
    };

    const handleCheckOutSubmit = () => {
        setCheckoutOpen(false);
        post('/attendance/check-out', {
            factory_id: factoryId || null,
            location_name: locationName || null,
        });
        setFactoryId('');
        setLocationName('');
    };

    const tripLabel = status.trip
        ? `Trip ${status.trip.trip_number ?? ''} ${status.trip.stage === 'pickup' ? 'pickup' : 'drop-off'}${
              status.trip.stop_name ? ` — ${status.trip.stop_name}` : ''
          }`
        : null;

    return (
        <Card>
            <CardContent className="flex flex-wrap items-center justify-between gap-4 py-4">
                <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                        {status.status === 'on_break' ? (
                            <Coffee className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                        ) : status.status === 'checked_in' ? (
                            <Clock className="h-5 w-5 text-green-700 dark:text-green-300" />
                        ) : (
                            <CalendarClock className="h-5 w-5 text-slate-600 dark:text-slate-300" />
                        )}
                    </div>
                    <div>
                        <p className="font-semibold text-foreground">
                            {status.status === 'not_checked_in' && 'Not checked in'}
                            {status.status === 'checked_in' && `Checked in since ${formatTime(status.check_in_at)}`}
                            {status.status === 'on_break' && `On break since ${formatTime(status.check_in_at)}`}
                            {status.status === 'checked_out' && `Checked out at ${formatTime(status.check_out_at)}`}
                        </p>
                        <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            {tripLabel && (
                                <Badge variant="outline" className="text-xs">
                                    {tripLabel}
                                </Badge>
                            )}
                            {status.status === 'checked_out' && (
                                <span>
                                    Worked {formatDuration(status.net_minutes)}
                                    {status.overtime_minutes > 0 && ` · Overtime ${status.overtime_minutes}m`}
                                </span>
                            )}
                            {(status.status === 'checked_in' || status.status === 'on_break') && status.break_minutes > 0 && (
                                <span>{status.break_minutes}m break so far</span>
                            )}
                        </div>
                    </div>
                </div>

                <div className="flex gap-2">
                    {status.status === 'not_checked_in' && (
                        <Button disabled={submitting} onClick={() => post('/attendance/check-in')}>
                            <LogIn className="mr-2 h-4 w-4" />
                            Check In
                        </Button>
                    )}
                    {status.status === 'checked_in' && (
                        <>
                            <Button variant="outline" disabled={submitting} onClick={() => post('/attendance/break-start')}>
                                <Coffee className="mr-2 h-4 w-4" />
                                Start Break
                            </Button>
                            <Button disabled={submitting} onClick={() => setCheckoutOpen(true)}>
                                <LogOut className="mr-2 h-4 w-4" />
                                Check Out
                            </Button>
                        </>
                    )}
                    {status.status === 'on_break' && (
                        <Button disabled={submitting} onClick={() => post('/attendance/break-end')}>
                            <Coffee className="mr-2 h-4 w-4" />
                            End Break
                        </Button>
                    )}
                </div>
            </CardContent>

            <Dialog open={checkoutOpen} onOpenChange={setCheckoutOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Check Out</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>Factory visited (optional)</Label>
                            <Select value={factoryId} onValueChange={setFactoryId}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a factory" />
                                </SelectTrigger>
                                <SelectContent>
                                    {factories.map((factory) => (
                                        <SelectItem key={factory.id} value={String(factory.id)}>
                                            {factory.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Location name (optional)</Label>
                            <Input
                                value={locationName}
                                onChange={(e) => setLocationName(e.target.value)}
                                placeholder="e.g. Factory main gate"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCheckoutOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleCheckOutSubmit}>Confirm Check Out</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}
