import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Square, Trash2, Wrench } from 'lucide-react';
import { useState } from 'react';

export interface VehicleDowntime {
    id: number;
    starts_on: string;
    ends_on: string | null;
    reason: string;
    reason_label: string;
    replacement_vehicle_id: number | null;
    replacement_vehicle?: { id: number; registration_number: string } | null;
    notes: string | null;
    recorder?: { id: number; name: string } | null;
}

interface Props {
    vehicleId: number;
    downtimes: VehicleDowntime[];
    reasons: { value: string; label: string }[];
    replacementVehicles: { id: number; registration_number: string }[];
    canEdit: boolean;
}

type DowntimeForm = {
    starts_on: string;
    ends_on: string;
    reason: string;
    replacement_vehicle_id: string;
    notes: string;
};

const today = () => new Date().toISOString().slice(0, 10);

const daysBetween = (from: string, to: string | null) => {
    const end = new Date(to ?? today());
    const start = new Date(from);
    return Math.max(1, Math.round((end.getTime() - start.getTime()) / 86_400_000) + 1);
};

const formatDate = (date: string) => new Date(date).toLocaleDateString();

export function VehicleDowntimeCard({ vehicleId, downtimes, reasons, replacementVehicles, canEdit }: Props) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<VehicleDowntime | null>(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm<DowntimeForm>({
        starts_on: today(),
        ends_on: '',
        reason: 'repair',
        replacement_vehicle_id: 'none',
        notes: '',
    });

    const openForm = (downtime: VehicleDowntime | null) => {
        clearErrors();
        setEditing(downtime);
        setData({
            starts_on: downtime?.starts_on ?? today(),
            ends_on: downtime?.ends_on ?? '',
            reason: downtime?.reason ?? 'repair',
            replacement_vehicle_id: downtime?.replacement_vehicle_id ? String(downtime.replacement_vehicle_id) : 'none',
            notes: downtime?.notes ?? '',
        });
        setOpen(true);
    };

    const closeForm = () => {
        setOpen(false);
        setEditing(null);
        reset();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: closeForm };
        if (editing) {
            put(route('vehicles.downtimes.update', [vehicleId, editing.id]), options);
        } else {
            post(route('vehicles.downtimes.store', vehicleId), options);
        }
    };

    const endNow = (downtime: VehicleDowntime) =>
        router.post(route('vehicles.downtimes.end', [vehicleId, downtime.id]), {}, { preserveScroll: true });

    const remove = (downtime: VehicleDowntime) => {
        if (confirm('Delete this downtime record?')) {
            router.delete(route('vehicles.downtimes.destroy', [vehicleId, downtime.id]), { preserveScroll: true });
        }
    };

    const replacementOptions = [
        { label: 'No replacement', value: 'none' },
        ...replacementVehicles.map((v) => ({ label: v.registration_number, value: String(v.id) })),
    ];

    return (
        <Card className="lg:col-span-2">
            <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        <Wrench className="h-4 w-4" />
                        Downtime
                    </CardTitle>
                    <CardDescription>Periods the vehicle was out of service. Used by the Monthly Vehicle Report.</CardDescription>
                </div>
                {canEdit && (
                    <Button size="sm" onClick={() => openForm(null)}>
                        <Plus className="mr-1 h-4 w-4" />
                        Add downtime
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {downtimes.length === 0 ? (
                    <p className="text-sm text-muted-foreground">No downtime recorded.</p>
                ) : (
                    <div className="overflow-x-auto rounded border">
                        <table className="min-w-full text-sm">
                            <thead className="bg-muted/50">
                                <tr>
                                    <th className="px-3 py-2 text-left">From</th>
                                    <th className="px-3 py-2 text-left">To</th>
                                    <th className="px-3 py-2 text-right">Days</th>
                                    <th className="px-3 py-2 text-left">Reason</th>
                                    <th className="px-3 py-2 text-left">Replacement</th>
                                    <th className="px-3 py-2 text-left">Notes</th>
                                    {canEdit && <th className="px-3 py-2" />}
                                </tr>
                            </thead>
                            <tbody>
                                {downtimes.map((d) => (
                                    <tr key={d.id} className="border-t align-top">
                                        <td className="px-3 py-2 whitespace-nowrap">{formatDate(d.starts_on)}</td>
                                        <td className="px-3 py-2 whitespace-nowrap">
                                            {d.ends_on ? (
                                                formatDate(d.ends_on)
                                            ) : (
                                                <Badge className="bg-amber-100 text-amber-800 hover:bg-amber-100 dark:bg-amber-900 dark:text-amber-200">
                                                    Ongoing
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-right">{daysBetween(d.starts_on, d.ends_on)}</td>
                                        <td className="px-3 py-2">{d.reason_label}</td>
                                        <td className="px-3 py-2 font-mono">{d.replacement_vehicle?.registration_number ?? '-'}</td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {d.notes ?? ''}
                                            {d.recorder && <div className="text-xs">by {d.recorder.name}</div>}
                                        </td>
                                        {canEdit && (
                                            <td className="px-3 py-2">
                                                <div className="flex justify-end gap-1">
                                                    {!d.ends_on && (
                                                        <Button size="sm" variant="outline" onClick={() => endNow(d)} title="Vehicle is back in service">
                                                            <Square className="mr-1 h-3 w-3" />
                                                            End now
                                                        </Button>
                                                    )}
                                                    <Button size="icon" variant="ghost" onClick={() => openForm(d)} aria-label="Edit downtime">
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button size="icon" variant="ghost" onClick={() => remove(d)} aria-label="Delete downtime">
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </CardContent>

            <Dialog open={open} onOpenChange={(isOpen) => !isOpen && closeForm()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editing ? 'Edit downtime' : 'Add downtime'}</DialogTitle>
                        <DialogDescription>Leave "To" empty while the vehicle is still out of service.</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div className="space-y-1">
                                <Label htmlFor="starts_on">From</Label>
                                <Input id="starts_on" type="date" value={data.starts_on} onChange={(e) => setData('starts_on', e.target.value)} required />
                                {errors.starts_on && <p className="text-xs text-destructive">{errors.starts_on}</p>}
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="ends_on">To</Label>
                                <Input id="ends_on" type="date" value={data.ends_on} onChange={(e) => setData('ends_on', e.target.value)} />
                                {errors.ends_on && <p className="text-xs text-destructive">{errors.ends_on}</p>}
                            </div>
                        </div>
                        <div className="space-y-1">
                            <Label>Reason</Label>
                            <Select value={data.reason} onValueChange={(value) => setData('reason', value)}>
                                <SelectTrigger aria-label="Reason">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {reasons.map((r) => (
                                        <SelectItem key={r.value} value={r.value}>
                                            {r.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.reason && <p className="text-xs text-destructive">{errors.reason}</p>}
                        </div>
                        <SearchableSelect
                            label="Replacement vehicle"
                            name="replacement_vehicle_id"
                            value={data.replacement_vehicle_id}
                            onChange={(value) => setData('replacement_vehicle_id', value)}
                            options={replacementOptions}
                            error={errors.replacement_vehicle_id}
                            placeholder="Select vehicle..."
                        />
                        <div className="space-y-1">
                            <Label htmlFor="notes">Notes</Label>
                            <Textarea id="notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={2} />
                            {errors.notes && <p className="text-xs text-destructive">{errors.notes}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeForm} disabled={processing}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {editing ? 'Save' : 'Add'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </Card>
    );
}
