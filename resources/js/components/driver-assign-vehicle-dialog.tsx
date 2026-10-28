import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { FormEvent, useEffect, useState } from 'react';

interface AssignableVehicle {
    id: number;
    registration_number: string;
    brand: string;
    model: string;
    current_driver_id: number | null;
    current_driver_name: string | null;
}

interface DriverAssignVehicleDialogProps {
    driver: { id: number; name: string };
    onClose: () => void;
}

/**
 * Driver-side "Assign Vehicle" (drivers list and driver page). Render it with
 * key={driver.id} while open. The server asks to confirm moving the driver off
 * another vehicle (confirm_reassign); replacing the chosen vehicle's current
 * driver is confirmed here, since from the vehicle side that is the point.
 */
export function DriverAssignVehicleDialog({ driver, onClose }: DriverAssignVehicleDialogProps) {
    const [vehicles, setVehicles] = useState<AssignableVehicle[]>([]);
    const [loading, setLoading] = useState(true);
    const [confirmReplace, setConfirmReplace] = useState(false);

    const { data, setData, post, processing, errors } = useForm<{ driver_id: string; vehicle_id: string; confirm_reassign: boolean }>({
        driver_id: String(driver.id),
        vehicle_id: '',
        confirm_reassign: false,
    });

    useEffect(() => {
        let cancelled = false;
        axios
            .get<AssignableVehicle[]>(route('drivers.assignable-vehicles', driver.id))
            .then((response) => !cancelled && setVehicles(response.data))
            // Non-critical: the select will just show no options if this fails.
            .catch(() => {})
            .finally(() => !cancelled && setLoading(false));

        return () => {
            cancelled = true;
        };
    }, [driver.id]);

    const selected = vehicles.find((vehicle) => String(vehicle.id) === data.vehicle_id);
    const replacedDriver = selected?.current_driver_id && selected.current_driver_id !== driver.id ? selected.current_driver_name : null;

    const submit = (submitEvent: FormEvent<HTMLFormElement>) => {
        submitEvent.preventDefault();
        if (!data.vehicle_id) return;

        post(route('vehicles.assign-driver', data.vehicle_id), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Assign Vehicle</DialogTitle>
                    <DialogDescription>Choose a vehicle to assign to {driver.name}.</DialogDescription>
                </DialogHeader>

                <form className="space-y-4" onSubmit={submit}>
                    <div className="space-y-2">
                        <Label htmlFor="assign_vehicle_id">Vehicle</Label>
                        <Select
                            value={data.vehicle_id}
                            onValueChange={(value) => {
                                setData('vehicle_id', value);
                                setConfirmReplace(false);
                            }}
                        >
                            <SelectTrigger id="assign_vehicle_id" className="w-full">
                                <SelectValue placeholder={loading ? 'Loading vehicles...' : 'Select a vehicle'} />
                            </SelectTrigger>
                            <SelectContent>
                                {vehicles.map((vehicle) => (
                                    <SelectItem key={vehicle.id} value={String(vehicle.id)}>
                                        {vehicle.registration_number} - {vehicle.brand} {vehicle.model}
                                        {vehicle.current_driver_id && vehicle.current_driver_id !== driver.id
                                            ? ` (currently: ${vehicle.current_driver_name})`
                                            : ''}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.vehicle_id ?? errors.driver_id} />
                    </div>

                    {replacedDriver && (
                        <div className="space-y-2 rounded-md border border-amber-300 bg-amber-50 p-3">
                            <p className="text-sm text-amber-800">
                                {selected?.registration_number} is currently driven by {replacedDriver}. Saving replaces them, and they will have no
                                vehicle.
                            </p>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="confirm_replace_driver"
                                    checked={confirmReplace}
                                    onCheckedChange={(checked) => setConfirmReplace(checked === true)}
                                />
                                <Label htmlFor="confirm_replace_driver" className="text-sm font-normal">
                                    Replace {replacedDriver}
                                </Label>
                            </div>
                        </div>
                    )}

                    {errors.confirm_reassign && (
                        <div className="space-y-2 rounded-md border border-amber-300 bg-amber-50 p-3">
                            <p className="text-sm text-amber-800">{errors.confirm_reassign}</p>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="confirm_reassign_vehicle"
                                    checked={data.confirm_reassign}
                                    onCheckedChange={(checked) => setData('confirm_reassign', checked === true)}
                                />
                                <Label htmlFor="confirm_reassign_vehicle" className="text-sm font-normal">
                                    Confirm reassign
                                </Label>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose} disabled={processing}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing || !data.vehicle_id || (!!replacedDriver && !confirmReplace)}>
                            {processing ? 'Saving...' : 'Save'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
