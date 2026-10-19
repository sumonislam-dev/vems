import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { Car } from 'lucide-react';
import { useState } from 'react';

export interface AssignableVehicleOption {
    value: number;
    label: string;
    driver: string;
}

interface Props {
    tripId: number;
    hasVehicle: boolean;
    vehicles: AssignableVehicleOption[];
}

const REASONS = [
    { value: 'replacement', label: 'Replacement' },
    { value: 'breakdown', label: 'Breakdown' },
    { value: 'damaged', label: 'Damaged' },
    { value: 'maintenance', label: 'Maintenance' },
    { value: 'other', label: 'Other' },
];

/**
 * Logistics assigns a vehicle to a trip requested without one, or changes it
 * (a change needs a reason; the first assignment doesn't).
 */
export function TripAssignVehicleDialog({ tripId, hasVehicle, vehicles }: Props) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        vehicle_id: '',
        reason: hasVehicle ? 'replacement' : '',
        notes: '',
    });

    const close = () => {
        setOpen(false);
        reset();
        clearErrors();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('trips.reassign-vehicle', tripId), { preserveScroll: true, onSuccess: close });
    };

    const selected = vehicles.find((v) => String(v.value) === data.vehicle_id);

    return (
        <>
            <Button size="sm" variant={hasVehicle ? 'outline' : 'default'} onClick={() => setOpen(true)}>
                <Car className="mr-2 h-4 w-4" />
                {hasVehicle ? 'Change vehicle' : 'Assign vehicle'}
            </Button>

            <Dialog open={open} onOpenChange={(isOpen) => !isOpen && close()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{hasVehicle ? 'Change vehicle' : 'Assign vehicle'}</DialogTitle>
                        <DialogDescription>
                            {hasVehicle ? 'Pick the replacement vehicle and say why it changed.' : 'Pick the vehicle for this trip.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="space-y-1">
                            <SearchableSelect
                                label="Vehicle"
                                name="vehicle_id"
                                value={data.vehicle_id}
                                onChange={(value) => setData('vehicle_id', value)}
                                options={vehicles.map((v) => ({ label: v.label, value: String(v.value) }))}
                                placeholder="Search vehicle..."
                                required
                                error={errors.vehicle_id}
                            />
                            {selected && <p className="text-xs text-muted-foreground">Driver: {selected.driver}</p>}
                        </div>
                        {hasVehicle && (
                            <div className="space-y-1">
                                <Label htmlFor="assign_vehicle_reason">Reason</Label>
                                <Select name="reason" value={data.reason} onValueChange={(value) => setData('reason', value)}>
                                    <SelectTrigger id="assign_vehicle_reason">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {REASONS.map((r) => (
                                            <SelectItem key={r.value} value={r.value}>
                                                {r.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.reason && <p className="text-xs text-destructive">{errors.reason}</p>}
                            </div>
                        )}
                        <div className="space-y-1">
                            <Label htmlFor="assign-notes">Notes</Label>
                            <Textarea id="assign-notes" value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={2} />
                            {errors.notes && <p className="text-xs text-destructive">{errors.notes}</p>}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={close} disabled={processing}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing || !data.vehicle_id}>
                                {hasVehicle ? 'Change' : 'Assign'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
