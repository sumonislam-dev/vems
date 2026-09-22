import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { FormTextarea } from '@/base-components/base-form';
import { MultiSelect } from '@/base-components/base-multi-select';
import { SearchableSelect } from '@/components/searchable-select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, Logistics, Trip } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Minus, X, UserPlus, Calendar, AlertCircle, MapPin, Building2, Users, User } from 'lucide-react';
import { useEffect, useState } from 'react';
import * as React from 'react';

interface Passenger {
    user_id: string;
    pickup_stop_id?: string;
    dropoff_stop_id?: string;
}

interface DeptHeadcount {
    department_id: string;
    count: number;
}

interface SelectOption {
    value: number;
    label: string;
}

interface VehicleOption extends SelectOption {
    driver: string;
}

interface RouteStopOption {
    id: number;
    name: string;
    order: number;
}

interface RouteOption extends SelectOption {
    stops: RouteStopOption[];
}

interface EmployeeOption extends SelectOption {
    employee_id?: string | number | null;
    department?: string | null;
}

interface TripFactory {
    id: number;
    name: string;
}

interface TripDepartmentWithHeadcount {
    id: number;
    name: string;
    pivot?: {
        count?: number;
    };
}

type TripEditData = Trip & {
    factories?: TripFactory[];
    departments?: TripDepartmentWithHeadcount[];
    logistics?: Logistics[];
};

interface EditTripProps {
    trip: TripEditData;
    vehicles: VehicleOption[];
    routes: RouteOption[];
    departments: SelectOption[];
    employees: EmployeeOption[];
    factories: SelectOption[];
    logistics: SelectOption[];
}

interface TripEditForm {
    vehicle_route_id: string;
    vehicle_id: string;
    department_id: string;
    trip_type: string;
    team_number: string;
    remarks: string;
    description: string;
    priority: string;
    scheduled_date: string;
    scheduled_start_time: string;
    scheduled_end_time: string;
    start_location: string;
    end_location: string;
    is_return: boolean;
    notes: string;
    passengers: Passenger[];
    factory_ids: (string | number)[];
    logistics_ids: (string | number)[];
    department_slots: DeptHeadcount[];
}

export default function EditTrip({ trip, vehicles, routes, departments, employees, factories, logistics }: EditTripProps) {
    const [selectedRoute, setSelectedRoute] = useState<RouteOption | null>(null);
    const [passengers, setPassengers] = useState<Passenger[]>([]);
    const [selectedFactoryIds, setSelectedFactoryIds] = useState<(string | number)[]>([]);
    const [selectedLogisticsIds, setSelectedLogisticsIds] = useState<(string | number)[]>([]);
    const [deptInput, setDeptInput] = useState({ department_id: '', count: 1 });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Trips', href: '/trips' },
        { title: trip.trip_number, href: `/trips/${trip.id}` },
        { title: 'Edit', href: '#' },
    ];

    const { data, setData, put, processing, errors } = useForm<TripEditForm>({
        vehicle_route_id: trip.vehicle_route_id?.toString() || '',
        vehicle_id: trip.vehicle_id?.toString() || '',
        department_id: trip.department_id?.toString() || '',
        trip_type: trip.trip_type || '',
        team_number: trip.team_number || '',
        remarks: trip.remarks || '',
        description: trip.description || '',
        priority: trip.priority || 'medium',
        scheduled_date: trip.scheduled_date || '',
        scheduled_start_time: trip.scheduled_start_time || '',
        scheduled_end_time: trip.scheduled_end_time || '',
        start_location: trip.start_location || '',
        end_location: trip.end_location || '',
        is_return: trip.is_return || false,
        notes: trip.notes || '',
        passengers: [] as Passenger[],
        factory_ids: [] as (string | number)[],
        logistics_ids: [] as (string | number)[],
        department_slots: [] as DeptHeadcount[],
    });

    useEffect(() => {
        // Initialize existing passengers
        if (trip.passengers && trip.passengers.length > 0) {
            const existingPassengers = trip.passengers.map((p) => ({
                user_id: p.user_id.toString(),
                pickup_stop_id: p.pickup_stop_id?.toString() || '',
                dropoff_stop_id: p.dropoff_stop_id?.toString() || '',
            }));
            setPassengers(existingPassengers);
            setData('passengers', existingPassengers);
        }

        // Initialize selected route
        if (trip.vehicle_route_id) {
            const route = routes?.find((r) => r.value === trip.vehicle_route_id);
            setSelectedRoute(route ?? null);
        }

        // Initialize factories
        if (trip.factories && trip.factories.length > 0) {
            const ids = trip.factories.map((f) => f.id.toString());
            setSelectedFactoryIds(ids);
            setData('factory_ids', ids);
        }

        // Initialize logistics
        if (trip.logistics && trip.logistics.length > 0) {
            const ids = trip.logistics.map((l) => l.id.toString());
            setSelectedLogisticsIds(ids);
            setData('logistics_ids', ids);
        }

        // Initialize department slots
        if (trip.departments && trip.departments.length > 0) {
            const slots = trip.departments.map((d: TripDepartmentWithHeadcount) => ({
                department_id: d.id.toString(),
                count: d.pivot?.count || 1,
            }));
            setData('department_slots', slots);
        }
    }, []);

    const selectedVehicle = vehicles?.find((v) => v.value.toString() === data.vehicle_id);
    const formError = (errors as Record<string, string | undefined>).error;

    // Sync factories with form data
    React.useEffect(() => {
        setData('factory_ids', selectedFactoryIds);
    }, [selectedFactoryIds]);

    // Sync logistics with form data
    React.useEffect(() => {
        setData('logistics_ids', selectedLogisticsIds);
    }, [selectedLogisticsIds]);

    const addDeptHeadcount = () => {
        if (!deptInput.department_id || deptInput.count < 1) return;
        if (data.department_slots.some(d => d.department_id === deptInput.department_id)) return;
        setData('department_slots', [
            ...data.department_slots,
            { department_id: deptInput.department_id, count: deptInput.count },
        ]);
        setDeptInput({ department_id: '', count: 1 });
    };

    const removeDeptHeadcount = (departmentId: string) => {
        setData('department_slots', data.department_slots.filter(d => d.department_id !== departmentId));
    };

    const totalDeptHeadcount = data.department_slots.reduce((sum, d) => sum + d.count, 0);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('trips.update', trip.id), {
            onSuccess: () => router.visit(route('trips.show', trip.id)),
        });
    };

    const addPassenger = () => {
        setPassengers([...passengers, { user_id: '' }]);
    };

    const removePassenger = (index: number) => {
        const updated = passengers.filter((_, i) => i !== index);
        setPassengers(updated);
        setData('passengers', updated);
    };

    const updatePassenger = (index: number, field: keyof Passenger, value: string) => {
        const updated = [...passengers];
        updated[index] = { ...updated[index], [field]: value };
        setPassengers(updated);
        setData('passengers', updated);
    };

    const handleRouteChange = (value: string) => {
        setData('vehicle_route_id', value);
        const route = routes?.find((r) => r.value.toString() === value);
        setSelectedRoute(route ?? null);
    };

    // Check if trip can be edited
    const canEdit = ['pending', 'approved'].includes(trip.status);

    if (!canEdit) {
        return (
            <AppSidebarLayout breadcrumbs={breadcrumbs}>
                <Head title={`Edit Trip ${trip.trip_number}`} />
                <div className="space-y-6">
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            This trip cannot be edited in its current status: {trip.status}
                        </AlertDescription>
                    </Alert>
                    <Button onClick={() => router.visit(route('trips.show', trip.id))}>
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back to Trip Details
                    </Button>
                </div>
            </AppSidebarLayout>
        );
    }

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Trip ${trip.trip_number}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Edit Trip {trip.trip_number}</h1>
                        <p className="text-sm text-gray-500 mt-1">Update trip details</p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => router.visit(route('trips.show', trip.id))}
                    >
                        <ArrowLeft className="mr-2 h-4 w-4" />
                        Back
                    </Button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Trip Information</CardTitle>
                            <CardDescription>Basic details about the trip</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Vehicle & Route */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <SearchableSelect
                                        label="Vehicle *"
                                        name="vehicle_id"
                                        value={data.vehicle_id}
                                        onChange={(value) => setData('vehicle_id', value)}
                                        options={vehicles?.map((v) => ({ label: v.label, value: v.value.toString() })) || []}
                                        placeholder="Search vehicle..."
                                        required
                                        error={errors.vehicle_id}
                                    />
                                    {selectedVehicle && (
                                        <div className="flex items-center gap-1.5 text-xs text-muted-foreground bg-blue-50 px-2 py-1 rounded">
                                            <User className="h-3 w-3" />
                                            Driver: <strong>{selectedVehicle.driver}</strong>
                                        </div>
                                    )}
                                </div>

                                <SearchableSelect
                                    label="Route (Optional)"
                                    name="vehicle_route_id"
                                    value={data.vehicle_route_id}
                                    onChange={handleRouteChange}
                                    options={routes?.map((r) => ({ label: r.label, value: r.value.toString() })) || []}
                                    placeholder="Search route..."
                                    error={errors.vehicle_route_id}
                                />
                            </div>

                            {/* Trip Type | Priority | Department */}
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <SearchableSelect
                                    label="Trip Type *"
                                    name="trip_type"
                                    value={data.trip_type}
                                    onChange={(value) => setData('trip_type', value)}
                                    options={[
                                        { label: 'Inspection', value: 'inspection' },
                                        { label: 'Pick-up', value: 'pick-up' },
                                        { label: 'Drop-off', value: 'drop-off' },
                                        { label: 'Training', value: 'training' },
                                        { label: 'Complaints', value: 'complaints' },
                                        { label: 'CVV', value: 'CVV' },
                                        { label: 'Incident Inspection', value: 'Incident Inspection' },
                                        { label: 'Officials', value: 'officials' },
                                        { label: 'Assigned', value: 'Assigned' },
                                    ]}
                                    placeholder="Trip type..."
                                    required
                                    error={errors.trip_type}
                                />
                                <SearchableSelect
                                    label="Priority *"
                                    name="priority"
                                    value={data.priority}
                                    onChange={(value) => setData('priority', value)}
                                    options={[
                                        { label: 'Low', value: 'low' },
                                        { label: 'Medium', value: 'medium' },
                                        { label: 'High', value: 'high' },
                                        { label: 'Urgent', value: 'urgent' },
                                    ]}
                                    placeholder="Priority..."
                                    required
                                    error={errors.priority}
                                />
                                <SearchableSelect
                                    label="Department"
                                    name="department_id"
                                    value={data.department_id}
                                    onChange={(value) => setData('department_id', value)}
                                    options={departments?.map((d) => ({ label: d.label, value: d.value.toString() })) || []}
                                    placeholder="Select department..."
                                    error={errors.department_id}
                                />
                            </div>

                            {/* Team Number | Start Location | End Location */}
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="team_number">Team Number</Label>
                                    <Input
                                        id="team_number"
                                        value={data.team_number}
                                        onChange={(e) => setData('team_number', e.target.value)}
                                        placeholder="e.g. TM-001"
                                    />
                                    {errors.team_number && <p className="text-sm text-red-500">{errors.team_number}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="start_location">Start Location</Label>
                                    <div className="relative">
                                        <MapPin className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                                        <Input
                                            id="start_location"
                                            value={data.start_location}
                                            onChange={(e) => setData('start_location', e.target.value)}
                                            placeholder="Start location"
                                            className="pl-8"
                                        />
                                    </div>
                                    {errors.start_location && <p className="text-sm text-red-500">{errors.start_location}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="end_location">End Location</Label>
                                    <div className="relative">
                                        <MapPin className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                                        <Input
                                            id="end_location"
                                            value={data.end_location}
                                            onChange={(e) => setData('end_location', e.target.value)}
                                            placeholder="End location"
                                            className="pl-8"
                                        />
                                    </div>
                                    {errors.end_location && <p className="text-sm text-red-500">{errors.end_location}</p>}
                                </div>
                            </div>

                            {/* Remarks | Description */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <FormTextarea
                                    label="Remarks"
                                    name="remarks"
                                    value={data.remarks}
                                    onChange={(value) => setData('remarks', value)}
                                    placeholder="Additional remarks"
                                    rows={3}
                                    error={errors.remarks}
                                />
                                <FormTextarea
                                    label="Description"
                                    name="description"
                                    value={data.description}
                                    onChange={(value) => setData('description', value)}
                                    placeholder="Enter trip description"
                                    rows={3}
                                    error={errors.description}
                                />
                            </div>

                            {/* Return Trip */}
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_return"
                                    checked={data.is_return}
                                    onChange={(e) => setData('is_return', e.target.checked)}
                                    className="rounded border-gray-300 text-primary focus:ring-primary"
                                />
                                <Label htmlFor="is_return" className="cursor-pointer">Return Trip</Label>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Factories + Headcount + Logistics */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {/* Factories */}
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-2">
                                    <Building2 className="h-3.5 w-3.5" />
                                    Factories
                                    {selectedFactoryIds.length > 0 && (
                                        <Badge variant="secondary" className="text-xs px-1.5 py-0">
                                            {selectedFactoryIds.length}
                                        </Badge>
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <MultiSelect
                                    options={factories?.map((f) => ({ label: f.label, value: f.value.toString() })) || []}
                                    value={selectedFactoryIds}
                                    onChange={setSelectedFactoryIds}
                                    placeholder="Select factories..."
                                    searchPlaceholder="Search factory..."
                                />
                                {selectedFactoryIds.length === 0 ? (
                                    <div className="text-center py-4 text-muted-foreground border-2 border-dashed rounded-lg">
                                        <Building2 className="h-7 w-7 mx-auto mb-1.5 opacity-25" />
                                        <p className="text-xs">No factories selected.</p>
                                    </div>
                                ) : (
                                    <div className="flex flex-wrap gap-1.5">
                                        {selectedFactoryIds.map((id) => {
                                            const factory = factories?.find((f) => f.value.toString() === id.toString());
                                            return (
                                                <div key={id} className="flex items-center gap-1 px-2 py-0.5 rounded-full border bg-muted/30 text-xs">
                                                    <Building2 className="h-3 w-3 text-muted-foreground shrink-0" />
                                                    <span className="font-medium truncate max-w-[140px]">{factory?.label ?? id}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => setSelectedFactoryIds(prev => prev.filter(fid => fid.toString() !== id.toString()))}
                                                        className="text-muted-foreground hover:text-destructive transition-colors shrink-0"
                                                    >
                                                        <X className="h-3 w-3" />
                                                    </button>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Headcount by Department */}
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-2">
                                    <Users className="h-3.5 w-3.5" />
                                    Headcount by Department
                                    {totalDeptHeadcount > 0 && (
                                        <Badge variant="outline" className="text-xs px-1.5 py-0">
                                            {totalDeptHeadcount} total
                                        </Badge>
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <div className="space-y-2">
                                    <select
                                        value={deptInput.department_id}
                                        onChange={(e) => setDeptInput(prev => ({ ...prev, department_id: e.target.value }))}
                                        className="w-full h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-1 focus:ring-ring"
                                    >
                                        <option value="">Select department...</option>
                                        {departments?.map((d) => (
                                            <option key={d.value} value={d.value.toString()}>{d.label}</option>
                                        ))}
                                    </select>

                                    <div className="grid grid-cols-[1fr_auto] gap-2">
                                        <div className="h-9 rounded-md border border-input bg-background flex items-center justify-between px-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => setDeptInput(prev => ({ ...prev, count: Math.max(1, prev.count - 1) }))}
                                                className="h-7 w-7"
                                                aria-label="Decrease count"
                                            >
                                                <Minus className="h-3.5 w-3.5" />
                                            </Button>
                                            <span className="text-sm font-semibold tabular-nums text-foreground px-2">
                                                {deptInput.count}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => setDeptInput(prev => ({ ...prev, count: Math.min(999, prev.count + 1) }))}
                                                className="h-7 w-7"
                                                aria-label="Increase count"
                                            >
                                                <Plus className="h-3.5 w-3.5" />
                                            </Button>
                                        </div>

                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            onClick={addDeptHeadcount}
                                            disabled={!deptInput.department_id}
                                            className="h-9 px-3"
                                        >
                                            Add
                                        </Button>
                                    </div>
                                </div>
                                {data.department_slots.length === 0 ? (
                                    <div className="text-center py-4 text-muted-foreground border-2 border-dashed rounded-lg">
                                        <Users className="h-7 w-7 mx-auto mb-1.5 opacity-25" />
                                        <p className="text-xs">No headcount added yet.</p>
                                    </div>
                                ) : (
                                    <div className="flex flex-wrap gap-2">
                                        {data.department_slots.map((d) => {
                                            const dept = departments?.find((dep) => dep.value.toString() === d.department_id);
                                            return (
                                                <div key={d.department_id} className="flex items-center gap-1.5 px-2.5 py-1 rounded-full border bg-muted/30">
                                                    <Users className="h-3 w-3 text-muted-foreground" />
                                                    <span className="text-xs font-medium">{dept?.label ?? d.department_id}</span>
                                                    <span className="text-xs text-muted-foreground">× {d.count}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeDeptHeadcount(d.department_id)}
                                                        className="text-muted-foreground hover:text-destructive transition-colors"
                                                    >
                                                        <X className="h-3 w-3" />
                                                    </button>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Logistics */}
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-2">
                                    <Building2 className="h-3.5 w-3.5" />
                                    Logistics
                                    {selectedLogisticsIds.length > 0 && (
                                        <Badge variant="secondary" className="text-xs px-1.5 py-0">
                                            {selectedLogisticsIds.length}
                                        </Badge>
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <MultiSelect
                                    options={logistics?.map((l) => ({ label: l.label, value: l.value.toString() })) || []}
                                    value={selectedLogisticsIds}
                                    onChange={setSelectedLogisticsIds}
                                    placeholder="Select logistics..."
                                    searchPlaceholder="Search logistics..."
                                />
                                {selectedLogisticsIds.length === 0 ? (
                                    <div className="text-center py-4 text-muted-foreground border-2 border-dashed rounded-lg">
                                        <Building2 className="h-7 w-7 mx-auto mb-1.5 opacity-25" />
                                        <p className="text-xs">No logistics selected.</p>
                                    </div>
                                ) : (
                                    <div className="flex flex-wrap gap-1.5">
                                        {selectedLogisticsIds.map((id) => {
                                            const log = logistics?.find((l) => l.value.toString() === id.toString());
                                            return (
                                                <div key={id} className="flex items-center gap-1 px-2 py-0.5 rounded-full border bg-muted/30 text-xs">
                                                    <span className="font-medium truncate max-w-[140px]">{log?.label ?? id}</span>
                                                    <button
                                                        type="button"
                                                        onClick={() => setSelectedLogisticsIds(prev => prev.filter(lid => lid.toString() !== id.toString()))}
                                                        className="text-muted-foreground hover:text-destructive transition-colors shrink-0"
                                                    >
                                                        <X className="h-3 w-3" />
                                                    </button>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Schedule */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Calendar className="h-5 w-5" />
                                Schedule
                            </CardTitle>
                            <CardDescription>When should this trip take place?</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="scheduled_date">Date *</Label>
                                    <Input
                                        id="scheduled_date"
                                        type="date"
                                        value={data.scheduled_date}
                                        onChange={(e) => setData('scheduled_date', e.target.value)}
                                    />
                                    {errors.scheduled_date && <p className="text-sm text-red-500">{errors.scheduled_date}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="scheduled_start_time">Start Time *</Label>
                                    <Input
                                        id="scheduled_start_time"
                                        type="time"
                                        value={data.scheduled_start_time}
                                        onChange={(e) => setData('scheduled_start_time', e.target.value)}
                                    />
                                    {errors.scheduled_start_time && <p className="text-sm text-red-500">{errors.scheduled_start_time}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="scheduled_end_time">End Time *</Label>
                                    <Input
                                        id="scheduled_end_time"
                                        type="time"
                                        value={data.scheduled_end_time}
                                        onChange={(e) => setData('scheduled_end_time', e.target.value)}
                                    />
                                    {errors.scheduled_end_time && <p className="text-sm text-red-500">{errors.scheduled_end_time}</p>}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Passengers */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <UserPlus className="h-5 w-5" />
                                        Passengers
                                    </CardTitle>
                                    <CardDescription>Manage passengers for this trip</CardDescription>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addPassenger}
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Passenger
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {passengers.length === 0 ? (
                                <div className="text-center py-8 text-gray-500">
                                    <UserPlus className="h-12 w-12 mx-auto mb-2 opacity-50" />
                                    <p>No passengers added yet</p>
                                    <p className="text-sm">Click "Add Passenger" to add passengers to this trip</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {passengers.map((passenger, index) => (
                                        <div key={index} className="flex gap-3 items-start p-4 border rounded-lg">
                                            <div className="flex-1 grid grid-cols-1 md:grid-cols-3 gap-3">
                                                <SearchableSelect
                                                    label="Employee"
                                                    name={`passenger-${index}`}
                                                    value={passenger.user_id}
                                                    onChange={(value) => updatePassenger(index, 'user_id', value)}
                                                    options={employees?.map((emp) => ({
                                                        label: `${emp.label}${emp.department ? ` (${emp.department})` : ''}`,
                                                        value: emp.value.toString()
                                                    })) || []}
                                                    placeholder="Search employee..."
                                                    required
                                                />

                                                {selectedRoute && (
                                                    <>
                                                        <div className="space-y-2">
                                                            <Label htmlFor={`pickup-${index}`}>Pickup Stop</Label>
                                                            <Select
                                                                value={passenger.pickup_stop_id}
                                                                onValueChange={(value) => updatePassenger(index, 'pickup_stop_id', value)}
                                                            >
                                                                <SelectTrigger id={`pickup-${index}`}>
                                                                    <SelectValue placeholder="Select stop" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {selectedRoute.stops?.map((stop) => (
                                                                        <SelectItem key={stop.id} value={stop.id.toString()}>
                                                                            {stop.order}. {stop.name}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>

                                                        <div className="space-y-2">
                                                            <Label htmlFor={`dropoff-${index}`}>Dropoff Stop</Label>
                                                            <Select
                                                                value={passenger.dropoff_stop_id}
                                                                onValueChange={(value) => updatePassenger(index, 'dropoff_stop_id', value)}
                                                            >
                                                                <SelectTrigger id={`dropoff-${index}`}>
                                                                    <SelectValue placeholder="Select stop" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    {selectedRoute.stops?.map((stop) => (
                                                                        <SelectItem key={stop.id} value={stop.id.toString()}>
                                                                            {stop.order}. {stop.name}
                                                                        </SelectItem>
                                                                    ))}
                                                                </SelectContent>
                                                            </Select>
                                                        </div>
                                                    </>
                                                )}
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removePassenger(index)}
                                                className="mt-8"
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Additional Notes */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Additional Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FormTextarea
                                label="Notes"
                                name="notes"
                                value={data.notes}
                                onChange={(value) => setData('notes', value)}
                                placeholder="Any additional notes or instructions for this trip"
                                rows={4}
                                error={errors.notes}
                            />
                        </CardContent>
                    </Card>

                    {/* Error Display */}
                    {formError && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{formError}</AlertDescription>
                        </Alert>
                    )}

                    {/* Submit Actions */}
                    <div className="flex justify-end gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.visit(route('trips.show', trip.id))}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Updating...' : 'Update Trip'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppSidebarLayout>
    );
}
