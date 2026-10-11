import { TripPassengerSelection } from '@/components/trip-passenger-selection';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FormTextarea } from '@/base-components/base-form';
import { MultiSelect } from '@/base-components/base-multi-select';
import { SearchableSelect } from '@/components/searchable-select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem } from '@/types';
import { useCan } from '@/lib/permissions';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, X, Calendar, MapPin, User, CalendarDays, Info, Building2, Plus, Minus, Users } from 'lucide-react';
import { useState, useMemo } from 'react';
import * as React from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Trips', href: '/trips' },
    { title: 'Create Trip', href: '/trips/create' },
];

interface Passenger {
    user_id: string;
}

interface DeptHeadcount {
    department_id: string;
    count: number;
}

interface SelectOption {
    value: string;
    label: string;
}

interface VehicleOption extends SelectOption {
    driver?: string | { name?: string };
}

interface EmployeeOption extends SelectOption {
    employee_id?: string | number;
    department?: string | { name?: string };
}

interface GroupOption extends SelectOption {
    user_ids?: (string | number)[];
}

interface CreateTripProps {
    vehicles: VehicleOption[];
    routes: SelectOption[];
    departments: SelectOption[];
    employees: EmployeeOption[];
    factories: SelectOption[];
    userGroups: GroupOption[];
    logistics: SelectOption[];
}

export default function CreateTrip({ vehicles, routes, departments, employees, factories, userGroups, logistics }: CreateTripProps) {
    const [selectedPassengerIds, setSelectedPassengerIds] = useState<(string | number)[]>([]);
    const [selectedGroupId, setSelectedGroupId] = useState<string>('');
    const [selectedFactoryIds, setSelectedFactoryIds] = useState<(string | number)[]>([]);
    const [selectedLogisticsIds, setSelectedLogisticsIds] = useState<(string | number)[]>([]);
    const [deptInput, setDeptInput] = useState({ department_id: '', count: 1 });
    const [isRecurring, setIsRecurring] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        vehicle_route_id: '',
        vehicle_id: '',
        department_id: '',
        trip_type: '',
        remarks: '',
        description: '',
        priority: 'medium',
        scheduled_date: '',
        scheduled_start_time: '',
        scheduled_end_time: '',
        start_location: '',
        end_location: '',
        is_return: false,
        is_recurring: false,
        recurring_start_date: '',
        recurring_end_date: '',
        group_name: '',
        notes: '',
        passengers: [] as Passenger[],
        factory_ids: [] as (string | number)[],
        logistics_ids: [] as (string | number)[],
        department_slots: [] as DeptHeadcount[],
        team_number: '',
    });

    const selectedVehicle = vehicles?.find((v) => String(v.value) === String(data.vehicle_id));
    // Only Logistics picks a vehicle up front; other requests get one after approval
    const canAssignVehicle = useCan()('assign-vehicles');

    // Calculate days count for recurring trips
    const daysCount = useMemo(() => {
        if (!isRecurring || !data.recurring_start_date || !data.recurring_end_date) return 0;
        const start = new Date(data.recurring_start_date);
        const end = new Date(data.recurring_end_date);
        const diff = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1;
        return diff > 0 ? diff : 0;
    }, [isRecurring, data.recurring_start_date, data.recurring_end_date]);

    // Sync passengers with form data
    React.useEffect(() => {
        const passengerData = selectedPassengerIds.map(id => ({
            user_id: id.toString(),
        }));
        setData('passengers', passengerData);
    }, [selectedPassengerIds, setData]);

    // Sync factories with form data
    React.useEffect(() => {
        setData('factory_ids', selectedFactoryIds);
    }, [selectedFactoryIds, setData]);

    // Sync logistics with form data
    React.useEffect(() => {
        setData('logistics_ids', selectedLogisticsIds);
    }, [selectedLogisticsIds, setData]);

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

        // Use appropriate route based on recurring status
        const submitRoute = isRecurring ? route('trips.store-recurring') : route('trips.store');

        post(submitRoute, {
            onSuccess: () => router.visit(route('trips.index')),
        });
    };

    const handleRouteChange = (value: string) => {
        setData('vehicle_route_id', value);
    };

    const handleGroupSelect = (groupId: string) => {
        setSelectedGroupId(groupId);
        if (!groupId) return;
        const group = userGroups?.find((g: GroupOption) => g.value.toString() === groupId);
        if (!group) return;
        const newIds = group.user_ids as (string | number)[];
        setSelectedPassengerIds(prev => {
            const existing = new Set(prev.map((id) => id.toString()));
            const toAdd = newIds.filter((id) => !existing.has(id.toString()));
            return [...prev, ...toAdd];
        });
        setSelectedGroupId('');
    };

    const getEmployeeDetails = (userId: string | number) => {
        const employee = employees?.find((emp) => emp.value.toString() === userId.toString());
        return employee
            ? {
                  name: String(employee.label ?? ''),
                  employeeId: employee.employee_id ? String(employee.employee_id) : '',
                  department:
                      typeof employee.department === 'string'
                          ? employee.department
                          : String(employee.department?.name ?? ''),
              }
            : { name: 'Unknown', employeeId: '', department: '' };
    };

    const formError = (errors as Record<string, string | undefined>).error;

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Trip" />

            <div className="space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold">Create New Trip</h1>
                        <p className="text-xs text-muted-foreground mt-0.5">Fill in the details below to schedule a trip</p>
                    </div>
                    <Button variant="outline" size="sm" onClick={() => router.visit(route('trips.index'))}>
                        <ArrowLeft className="mr-1.5 h-3.5 w-3.5" />
                        Back
                    </Button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">

                    {/* Trip Details */}
                    <Card>
                        <CardHeader className="pb-3 pt-4 px-4">
                            <CardTitle className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Trip Details</CardTitle>
                        </CardHeader>
                        <CardContent className="px-4 pb-4 space-y-3">
                            {/* Vehicle & Route */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                {canAssignVehicle ? (
                                <div className="space-y-1">
                                    <SearchableSelect
                                        label="Vehicle (optional)"
                                        name="vehicle_id"
                                        value={data.vehicle_id}
                                        onChange={(value) => setData('vehicle_id', value)}
                                        options={vehicles?.map((v) => ({ label: v.label, value: v.value })) || []}
                                        placeholder="Search vehicle..."
                                        error={errors.vehicle_id}
                                    />
                                    {selectedVehicle && (
                                        <div className="flex items-center gap-1.5 text-xs text-muted-foreground bg-blue-50 dark:bg-blue-900/20 px-2 py-1 rounded">
                                            <User className="h-3 w-3" />
                                            Driver: <strong>{typeof selectedVehicle.driver === 'string' ? selectedVehicle.driver : selectedVehicle.driver?.name ?? 'Unassigned'}</strong>
                                        </div>
                                    )}
                                </div>
                                ) : (
                                    <div className="space-y-1">
                                        <span className="text-sm font-medium">Vehicle</span>
                                        <div className="flex items-start gap-1.5 rounded border border-dashed px-3 py-2 text-xs text-muted-foreground">
                                            <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                            Logistics will assign a vehicle after the request is approved.
                                        </div>
                                    </div>
                                )}
                                <SearchableSelect
                                    label="Route (Optional)"
                                    name="vehicle_route_id"
                                    value={data.vehicle_route_id}
                                    onChange={handleRouteChange}
                                    options={routes?.map((r) => ({ label: r.label, value: r.value })) || []}
                                    placeholder="Search route..."
                                    error={errors.vehicle_route_id}
                                />
                            </div>

                            {/* Trip Type | Priority | Department | Team Number */}
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <SearchableSelect
                                    label="Trip Type"
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
                                    label="Priority"
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
                                    label="Requesting Department"
                                    name="department_id"
                                    value={data.department_id}
                                    onChange={(value) => setData('department_id', value)}
                                    options={departments?.map((d) => ({ label: d.label, value: d.value })) || []}
                                    placeholder="Select department..."
                                    error={errors.department_id}
                                />

                            </div>

                            {/* Start Location | End Location | Team Number */}
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div className="space-y-1">
                                    <Label htmlFor="team_number" className="text-xs">Team Number</Label>
                                    <Input
                                        id="team_number"
                                        value={data.team_number}
                                        onChange={(e) => setData('team_number', e.target.value)}
                                        placeholder="e.g. TM-001"
                                        className="h-9 text-sm"
                                    />
                                    {errors.team_number && <p className="text-xs text-red-500">{errors.team_number}</p>}
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="start_location" className="text-xs">Start Location</Label>
                                    <div className="relative">
                                        <MapPin className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                                        <Input
                                            id="start_location"
                                            value={data.start_location}
                                            onChange={(e) => setData('start_location', e.target.value)}
                                            placeholder="Start location"
                                            className="pl-8 h-9 text-sm"
                                        />
                                    </div>
                                    {errors.start_location && <p className="text-xs text-red-500">{errors.start_location}</p>}
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="end_location" className="text-xs">End Location</Label>
                                    <div className="relative">
                                        <MapPin className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted-foreground" />
                                        <Input
                                            id="end_location"
                                            value={data.end_location}
                                            onChange={(e) => setData('end_location', e.target.value)}
                                            placeholder="End location"
                                            className="pl-8 h-9 text-sm"
                                        />
                                    </div>
                                    {errors.end_location && <p className="text-xs text-red-500">{errors.end_location}</p>}
                                </div>
                            </div>

                            {/* Remarks | Description */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <FormTextarea
                                    label="Remarks"
                                    name="remarks"
                                    value={data.remarks}
                                    onChange={(value) => setData('remarks', value)}
                                    placeholder="Additional remarks"
                                    rows={2}
                                    error={errors.remarks}
                                />
                                <FormTextarea
                                    label="Description"
                                    name="description"
                                    value={data.description}
                                    onChange={(value) => setData('description', value)}
                                    placeholder="Detailed description (optional)"
                                    rows={2}
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
                                <Label htmlFor="is_return" className="cursor-pointer text-sm">Return Trip</Label>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Factories + Headcount + Logistics row */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">

                        {/* Factories */}
                        <Card>
                            <CardHeader className="pb-2 pt-4 px-4">
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
                            <CardContent className="px-4 pb-4 space-y-2">
                                <MultiSelect
                                    options={factories?.map((f) => ({
                                        label: f.label,
                                        value: f.value,
                                    })) || []}
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
                                {errors.factory_ids && <p className="text-xs text-red-500">{errors.factory_ids}</p>}
                            </CardContent>
                        </Card>

                        {/* Headcount by Department */}
                        <Card>
                            <CardHeader className="pb-2 pt-4 px-4">
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
                            <CardContent className="px-4 pb-4 space-y-2">
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
                            <CardHeader className="pb-2 pt-4 px-4">
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
                            <CardContent className="px-4 pb-4 space-y-2">
                                <MultiSelect
                                    options={logistics?.map((l) => ({
                                        label: l.label,
                                        value: l.value,
                                    })) || []}
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
                                {errors.logistics_ids && <p className="text-xs text-red-500">{errors.logistics_ids}</p>}
                            </CardContent>
                        </Card>

                    </div>

                    {/* Schedule */}
                    <Card>
                        <CardHeader className="pb-3 pt-4 px-4">
                            <CardTitle className="text-sm font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-2">
                                <Calendar className="h-3.5 w-3.5" /> Schedule
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="px-4 pb-4 space-y-3">
                            {/* Recurring Toggle */}
                            <div className="flex items-center gap-2 px-3 py-2 bg-blue-50 dark:bg-blue-900/20 rounded-md border border-blue-200 dark:border-blue-800">
                                <Checkbox
                                    id="is_recurring"
                                    checked={isRecurring}
                                    onCheckedChange={(checked) => {
                                        setIsRecurring(!!checked);
                                        setData('is_recurring', !!checked);
                                    }}
                                />
                                <Label htmlFor="is_recurring" className="cursor-pointer flex items-center gap-1.5 text-sm">
                                    <CalendarDays className="h-3.5 w-3.5" />
                                    Recurring Trip (Multiple Days)
                                </Label>
                            </div>

                            {isRecurring ? (
                                <div className="space-y-3">
                                    <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                                        <div className="space-y-1">
                                            <Label htmlFor="recurring_start_date" className="text-xs">Start Date *</Label>
                                            <Input
                                                id="recurring_start_date"
                                                type="date"
                                                value={data.recurring_start_date}
                                                onChange={(e) => setData('recurring_start_date', e.target.value)}
                                                min={new Date().toISOString().split('T')[0]}
                                                className="h-9 text-sm"
                                            />
                                            {errors.recurring_start_date && <p className="text-xs text-red-500">{errors.recurring_start_date}</p>}
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="recurring_end_date" className="text-xs">End Date *</Label>
                                            <Input
                                                id="recurring_end_date"
                                                type="date"
                                                value={data.recurring_end_date}
                                                onChange={(e) => setData('recurring_end_date', e.target.value)}
                                                min={data.recurring_start_date || new Date().toISOString().split('T')[0]}
                                                className="h-9 text-sm"
                                            />
                                            {errors.recurring_end_date && <p className="text-xs text-red-500">{errors.recurring_end_date}</p>}
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="scheduled_start_time" className="text-xs">Daily Start Time *</Label>
                                            <Input
                                                id="scheduled_start_time"
                                                type="time"
                                                value={data.scheduled_start_time}
                                                onChange={(e) => setData('scheduled_start_time', e.target.value)}
                                                className="h-9 text-sm"
                                            />
                                            {errors.scheduled_start_time && <p className="text-xs text-red-500">{errors.scheduled_start_time}</p>}
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="scheduled_end_time" className="text-xs">Daily End Time *</Label>
                                            <Input
                                                id="scheduled_end_time"
                                                type="time"
                                                value={data.scheduled_end_time}
                                                onChange={(e) => setData('scheduled_end_time', e.target.value)}
                                                className="h-9 text-sm"
                                            />
                                            {errors.scheduled_end_time && <p className="text-xs text-red-500">{errors.scheduled_end_time}</p>}
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div className="space-y-1">
                                            <Label htmlFor="group_name" className="text-xs">Group Name (Optional)</Label>
                                            <Input
                                                id="group_name"
                                                value={data.group_name}
                                                onChange={(e) => setData('group_name', e.target.value)}
                                                placeholder="e.g., Daily Office Transport January 2026"
                                                className="h-9 text-sm"
                                            />
                                            {errors.group_name && <p className="text-xs text-red-500">{errors.group_name}</p>}
                                        </div>
                                        {daysCount > 0 && (
                                            <Alert className="py-2 px-3">
                                                <Info className="h-3.5 w-3.5" />
                                                <AlertTitle className="text-xs font-semibold">
                                                    {daysCount} trip{daysCount > 1 ? 's' : ''} will be created
                                                </AlertTitle>
                                                <AlertDescription className="text-xs">
                                                    {data.recurring_start_date} → {data.recurring_end_date}
                                                </AlertDescription>
                                            </Alert>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <div className="space-y-1">
                                        <Label htmlFor="scheduled_date" className="text-xs">Date *</Label>
                                        <Input
                                            id="scheduled_date"
                                            type="date"
                                            value={data.scheduled_date}
                                            onChange={(e) => setData('scheduled_date', e.target.value)}
                                            className="h-9 text-sm"
                                        />
                                        {errors.scheduled_date && <p className="text-xs text-red-500">{errors.scheduled_date}</p>}
                                    </div>
                                    <div className="space-y-1">
                                        <Label htmlFor="scheduled_start_time" className="text-xs">Start Time *</Label>
                                        <Input
                                            id="scheduled_start_time"
                                            type="time"
                                            value={data.scheduled_start_time}
                                            onChange={(e) => setData('scheduled_start_time', e.target.value)}
                                            className="h-9 text-sm"
                                        />
                                        {errors.scheduled_start_time && <p className="text-xs text-red-500">{errors.scheduled_start_time}</p>}
                                    </div>
                                    <div className="space-y-1">
                                        <Label htmlFor="scheduled_end_time" className="text-xs">End Time *</Label>
                                        <Input
                                            id="scheduled_end_time"
                                            type="time"
                                            value={data.scheduled_end_time}
                                            onChange={(e) => setData('scheduled_end_time', e.target.value)}
                                            className="h-9 text-sm"
                                        />
                                        {errors.scheduled_end_time && <p className="text-xs text-red-500">{errors.scheduled_end_time}</p>}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Passengers */}
                    <TripPassengerSelection
                        selectedPassengerIds={selectedPassengerIds}
                        setSelectedPassengerIds={setSelectedPassengerIds}
                        employees={employees}
                        userGroups={userGroups}
                        selectedGroupId={selectedGroupId}
                        onGroupSelect={handleGroupSelect}
                        passengers={data.passengers}
                        getEmployeeDetails={getEmployeeDetails}
                        errors={errors}
                    />

                    {/* Notes + Actions */}
                    <Card>
                        <CardContent className="px-4 py-4 space-y-3">
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
                                    <AlertTitle>Error</AlertTitle>
                                    <AlertDescription>{formError}</AlertDescription>
                                </Alert>
                            )}
                            <div className="flex justify-end gap-2 pt-1">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.visit(route('trips.index'))}
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" size="sm" disabled={processing}>
                                    {processing
                                        ? (isRecurring ? 'Creating Trips...' : 'Creating Trip...')
                                        : (isRecurring ? `Create ${daysCount} Trip${daysCount !== 1 ? 's' : ''}` : 'Create Trip')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppSidebarLayout>
    );
}
