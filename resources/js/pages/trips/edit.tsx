import { FormTextarea } from '@/base-components/base-form';
import { SearchableSelect } from '@/components/searchable-select';
import { TripDetailsCard, TripFormFields, TripLinksRow, TripOption, TripTimeFields, TripVehicleOption } from '@/components/trip-form-sections';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useCan } from '@/lib/permissions';
import { BreadcrumbItem, Logistics, Trip } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Calendar, Plus, UserPlus, X } from 'lucide-react';
import * as React from 'react';

interface Passenger {
    user_id: string;
    pickup_stop_id?: string;
    dropoff_stop_id?: string;
}

interface RouteStopOption {
    id: number;
    name: string;
    order: number;
}

interface RouteOption extends TripOption {
    stops: RouteStopOption[];
}

interface EmployeeOption extends TripOption {
    employee_id?: string | number | null;
    department?: string | null;
}

interface TripDepartmentWithHeadcount {
    id: number;
    name: string;
    pivot?: { count?: number };
}

type TripEditData = Trip & {
    factories?: { id: number; name: string }[];
    departments?: TripDepartmentWithHeadcount[];
    logistics?: Logistics[];
};

interface EditTripForm extends TripFormFields {
    passengers: Passenger[];
}

interface EditTripProps {
    trip: TripEditData;
    vehicles: TripVehicleOption[];
    routes: RouteOption[];
    departments: TripOption[];
    employees: EmployeeOption[];
    factories: TripOption[];
    logistics: TripOption[];
}

/** Statuses in which a trip can still be edited (UpdateTripRequest::EDITABLE_STATUSES) */
const EDITABLE_STATUSES = ['pending', 'approved'];

export default function EditTrip({ trip, vehicles, routes, departments, employees, factories, logistics }: EditTripProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Trips', href: '/trips' },
        { title: trip.trip_number, href: `/trips/${trip.id}` },
        { title: 'Edit', href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm<EditTripForm>({
        vehicle_route_id: trip.vehicle_route_id?.toString() || '',
        vehicle_id: trip.vehicle_id?.toString() || '',
        department_id: trip.department_id?.toString() || '',
        trip_type: trip.trip_type || '',
        team_number: trip.team_number || '',
        remarks: trip.remarks || '',
        description: trip.description || '',
        priority: trip.priority || 'medium',
        scheduled_date: trip.scheduled_date || '',
        // Stored as HH:MM:SS; the time input and the server use HH:MM
        scheduled_start_time: trip.scheduled_start_time?.slice(0, 5) || '',
        scheduled_end_time: trip.scheduled_end_time?.slice(0, 5) || '',
        start_location: trip.start_location || '',
        end_location: trip.end_location || '',
        is_return: trip.is_return || false,
        notes: trip.notes || '',
        passengers: (trip.passengers ?? []).map((p) => ({
            user_id: p.user_id.toString(),
            pickup_stop_id: p.pickup_stop_id?.toString() || '',
            dropoff_stop_id: p.dropoff_stop_id?.toString() || '',
        })),
        factory_ids: (trip.factories ?? []).map((f) => f.id.toString()),
        logistics_ids: (trip.logistics ?? []).map((l) => l.id.toString()),
        department_slots: ((trip.departments ?? []) as TripDepartmentWithHeadcount[]).map((d) => ({
            department_id: d.id.toString(),
            count: d.pivot?.count || 1,
        })),
    });

    // Only Logistics changes the vehicle; the server ignores it from anyone else
    const canAssignVehicle = useCan()('assign-vehicles');
    const formError = (errors as Record<string, string | undefined>).error;
    const selectedRoute = routes?.find((r) => r.value.toString() === data.vehicle_route_id) ?? null;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('trips.update', trip.id), {
            onSuccess: () => router.visit(route('trips.show', trip.id)),
        });
    };

    const updatePassenger = (index: number, field: keyof Passenger, value: string) => {
        setData(
            'passengers',
            data.passengers.map((p, i) => (i === index ? { ...p, [field]: value } : p)),
        );
    };

    if (!EDITABLE_STATUSES.includes(trip.status)) {
        return (
            <AppSidebarLayout breadcrumbs={breadcrumbs}>
                <Head title={`Edit Trip ${trip.trip_number}`} />
                <div className="space-y-6">
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>This trip cannot be edited in its current status: {trip.status}</AlertDescription>
                    </Alert>
                    <Button onClick={() => router.visit(route('trips.show', trip.id))}>
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Trip Details
                    </Button>
                </div>
            </AppSidebarLayout>
        );
    }

    const sectionProps = { data, setData, errors };
    const stopSelect = (index: number, field: 'pickup_stop_id' | 'dropoff_stop_id', label: string) => (
        <div className="space-y-1">
            <Label htmlFor={`${field}-${index}`} className="text-xs">
                {label}
            </Label>
            <Select value={data.passengers[index][field]} onValueChange={(value) => updatePassenger(index, field, value)}>
                <SelectTrigger id={`${field}-${index}`} className="h-9 text-sm">
                    <SelectValue placeholder="Select stop" />
                </SelectTrigger>
                <SelectContent>
                    {selectedRoute?.stops?.map((stop) => (
                        <SelectItem key={stop.id} value={stop.id.toString()}>
                            {stop.order}. {stop.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Trip ${trip.trip_number}`} />

            <div className="space-y-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold">Edit Trip {trip.trip_number}</h1>
                        <p className="mt-0.5 text-xs text-muted-foreground">Update trip details</p>
                    </div>
                    <Button variant="outline" size="sm" onClick={() => router.visit(route('trips.show', trip.id))}>
                        <ArrowLeft className="mr-1.5 h-3.5 w-3.5" />
                        Back
                    </Button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <TripDetailsCard
                        {...sectionProps}
                        vehicles={vehicles}
                        routes={routes}
                        departments={departments}
                        canAssignVehicle={canAssignVehicle}
                    />

                    <TripLinksRow {...sectionProps} factories={factories} departments={departments} logistics={logistics} />

                    {/* Schedule */}
                    <Card>
                        <CardHeader className="px-4 pt-4 pb-3">
                            <CardTitle className="flex items-center gap-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                <Calendar className="h-3.5 w-3.5" /> Schedule
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-4 pb-4">
                            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <TripTimeFields {...sectionProps} />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Passengers, with pickup/dropoff stops when a route is chosen */}
                    <Card>
                        <CardHeader className="px-4 pt-4 pb-3">
                            <div className="flex items-center justify-between">
                                <CardTitle className="flex items-center gap-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                    <UserPlus className="h-3.5 w-3.5" /> Passengers
                                </CardTitle>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setData('passengers', [...data.passengers, { user_id: '' }])}
                                >
                                    <Plus className="mr-1.5 h-3.5 w-3.5" />
                                    Add Passenger
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3 px-4 pb-4">
                            {data.passengers.length === 0 ? (
                                <div className="rounded-lg border-2 border-dashed py-6 text-center text-muted-foreground">
                                    <UserPlus className="mx-auto mb-1.5 h-7 w-7 opacity-25" />
                                    <p className="text-xs">No passengers added yet. Click "Add Passenger" to add one.</p>
                                </div>
                            ) : (
                                data.passengers.map((passenger, index) => (
                                    <div key={index} className="flex items-start gap-3 rounded-lg border p-3">
                                        <div className="grid flex-1 grid-cols-1 gap-3 md:grid-cols-3">
                                            <SearchableSelect
                                                label="Employee"
                                                name={`passenger-${index}`}
                                                value={passenger.user_id}
                                                onChange={(value) => updatePassenger(index, 'user_id', value)}
                                                options={
                                                    employees?.map((emp) => ({
                                                        label: `${emp.label}${emp.department ? ` (${emp.department})` : ''}`,
                                                        value: emp.value.toString(),
                                                    })) || []
                                                }
                                                placeholder="Search employee..."
                                                required
                                            />
                                            {selectedRoute && (
                                                <>
                                                    {stopSelect(index, 'pickup_stop_id', 'Pickup Stop')}
                                                    {stopSelect(index, 'dropoff_stop_id', 'Dropoff Stop')}
                                                </>
                                            )}
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() =>
                                                setData(
                                                    'passengers',
                                                    data.passengers.filter((_, i) => i !== index),
                                                )
                                            }
                                            className="mt-6"
                                            aria-label="Remove passenger"
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    {/* Notes + Actions */}
                    <Card>
                        <CardContent className="space-y-3 px-4 py-4">
                            <FormTextarea
                                label="Notes"
                                name="notes"
                                value={data.notes}
                                onChange={(value) => setData('notes', value)}
                                placeholder="Any additional notes or instructions for this trip"
                                rows={2}
                                error={errors.notes}
                            />
                            {formError && (
                                <Alert variant="destructive">
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertDescription>{formError}</AlertDescription>
                                </Alert>
                            )}
                            <div className="flex justify-end gap-2 pt-1">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.visit(route('trips.show', trip.id))}
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" size="sm" disabled={processing}>
                                    {processing ? 'Updating...' : 'Update Trip'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppSidebarLayout>
    );
}
