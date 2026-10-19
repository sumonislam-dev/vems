import { FormTextarea } from '@/base-components/base-form';
import { TripDetailsCard, TripFormFields, TripLinksRow, TripOption, TripTimeFields, TripVehicleOption } from '@/components/trip-form-sections';
import { TripPassengerSelection } from '@/components/trip-passenger-selection';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useCan } from '@/lib/permissions';
import { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Calendar, CalendarDays, Info } from 'lucide-react';
import * as React from 'react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Trips', href: '/trips' },
    { title: 'Create Trip', href: '/trips/create' },
];

interface Passenger {
    user_id: string;
}

interface EmployeeOption extends TripOption {
    employee_id?: string | number;
    department?: string | { name?: string };
}

interface GroupOption extends TripOption {
    user_ids?: (string | number)[];
}

interface CreateTripForm extends TripFormFields {
    is_recurring: boolean;
    recurring_start_date: string;
    recurring_end_date: string;
    group_name: string;
    passengers: Passenger[];
}

interface CreateTripProps {
    vehicles: TripVehicleOption[];
    routes: TripOption[];
    departments: TripOption[];
    employees: EmployeeOption[];
    factories: TripOption[];
    userGroups: GroupOption[];
    logistics: TripOption[];
}

export default function CreateTrip({ vehicles, routes, departments, employees, factories, userGroups, logistics }: CreateTripProps) {
    const [selectedPassengerIds, setSelectedPassengerIds] = useState<(string | number)[]>([]);
    const [selectedGroupId, setSelectedGroupId] = useState<string>('');
    const [isRecurring, setIsRecurring] = useState(false);

    const { data, setData, post, processing, errors } = useForm<CreateTripForm>({
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
        passengers: [],
        factory_ids: [],
        logistics_ids: [],
        department_slots: [],
        team_number: '',
    });

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
        setData(
            'passengers',
            selectedPassengerIds.map((id) => ({ user_id: id.toString() })),
        );
    }, [selectedPassengerIds, setData]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Use appropriate route based on recurring status
        const submitRoute = isRecurring ? route('trips.store-recurring') : route('trips.store');

        post(submitRoute, {
            onSuccess: () => router.visit(route('trips.index')),
        });
    };

    const handleGroupSelect = (groupId: string) => {
        setSelectedGroupId(groupId);
        if (!groupId) return;
        const group = userGroups?.find((g: GroupOption) => g.value.toString() === groupId);
        if (!group) return;
        const newIds = group.user_ids as (string | number)[];
        setSelectedPassengerIds((prev) => {
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
                  department: typeof employee.department === 'string' ? employee.department : String(employee.department?.name ?? ''),
              }
            : { name: 'Unknown', employeeId: '', department: '' };
    };

    const formError = (errors as Record<string, string | undefined>).error;
    const sectionProps = { data, setData, errors };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Trip" />

            <div className="space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-bold">Create New Trip</h1>
                        <p className="mt-0.5 text-xs text-muted-foreground">Fill in the details below to schedule a trip</p>
                    </div>
                    <Button variant="outline" size="sm" onClick={() => router.visit(route('trips.index'))}>
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
                        <CardContent className="space-y-3 px-4 pb-4">
                            {/* Recurring Toggle */}
                            <div className="flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 dark:border-blue-800 dark:bg-blue-900/20">
                                <Checkbox
                                    id="is_recurring"
                                    checked={isRecurring}
                                    onCheckedChange={(checked) => {
                                        setIsRecurring(!!checked);
                                        setData('is_recurring', !!checked);
                                    }}
                                />
                                <Label htmlFor="is_recurring" className="flex cursor-pointer items-center gap-1.5 text-sm">
                                    <CalendarDays className="h-3.5 w-3.5" />
                                    Recurring Trip (Multiple Days)
                                </Label>
                            </div>

                            {isRecurring ? (
                                <div className="space-y-3">
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
                                        <div className="space-y-1">
                                            <Label htmlFor="recurring_start_date" className="text-xs">
                                                Start Date *
                                            </Label>
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
                                            <Label htmlFor="recurring_end_date" className="text-xs">
                                                End Date *
                                            </Label>
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
                                        <TripTimeFields {...sectionProps} showDate={false} timePrefix="Daily" />
                                    </div>
                                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div className="space-y-1">
                                            <Label htmlFor="group_name" className="text-xs">
                                                Group Name (Optional)
                                            </Label>
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
                                            <Alert className="px-3 py-2">
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
                                <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <TripTimeFields {...sectionProps} />
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
                                        ? isRecurring
                                            ? 'Creating Trips...'
                                            : 'Creating Trip...'
                                        : isRecurring
                                          ? `Create ${daysCount} Trip${daysCount !== 1 ? 's' : ''}`
                                          : 'Create Trip'}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppSidebarLayout>
    );
}
