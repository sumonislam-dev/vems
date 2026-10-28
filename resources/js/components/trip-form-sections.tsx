import { FormTextarea } from '@/base-components/base-form';
import { SearchableMultiSelect } from '@/components/searchable-multi-select';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Building2, Info, MapPin, Minus, Plus, User, Users, X } from 'lucide-react';
import { ReactNode } from 'react';

/**
 * Sections shared by the trip create and edit pages (pages/trips/create.tsx,
 * pages/trips/edit.tsx). Each page keeps what differs: the recurring option
 * and group passenger picker on create, per-passenger stops on edit.
 */

export interface TripOption {
    value: string | number;
    label: string;
}

export interface TripVehicleOption extends TripOption {
    driver?: string | { name?: string } | null;
}

export interface DeptHeadcount {
    department_id: string;
    count: number;
}

/** Fields both trip forms have */
export interface TripFormFields {
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
    factory_ids: (string | number)[];
    logistics_ids: (string | number)[];
    department_slots: DeptHeadcount[];
}

type SetTripField = <K extends keyof TripFormFields>(field: K, value: TripFormFields[K]) => void;
type TripErrors = Partial<Record<string, string>>;

const TRIP_TYPES = [
    { label: 'Inspection', value: 'inspection' },
    { label: 'Pick-up', value: 'pick-up' },
    { label: 'Drop-off', value: 'drop-off' },
    { label: 'Training', value: 'training' },
    { label: 'Complaints', value: 'complaints' },
    { label: 'CVV', value: 'CVV' },
    { label: 'Incident Inspection', value: 'Incident Inspection' },
    { label: 'Officials', value: 'officials' },
    { label: 'Assigned', value: 'Assigned' },
];

const PRIORITIES = [
    { label: 'Low', value: 'low' },
    { label: 'Medium', value: 'medium' },
    { label: 'High', value: 'high' },
    { label: 'Urgent', value: 'urgent' },
];

const toOptions = (options?: TripOption[]) => options?.map((o) => ({ label: o.label, value: o.value.toString() })) ?? [];

const driverName = (driver: TripVehicleOption['driver']) => (typeof driver === 'string' ? driver : (driver?.name ?? 'Unassigned'));

function FieldError({ message }: { message?: string }) {
    return message ? <p className="text-xs text-red-500">{message}</p> : null;
}

function SectionTitle({ icon, children }: { icon?: ReactNode; children: ReactNode }) {
    return (
        <CardTitle className="flex items-center gap-2 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
            {icon}
            {children}
        </CardTitle>
    );
}

function TextField({
    id,
    label,
    value,
    onChange,
    placeholder,
    error,
    icon,
}: {
    id: keyof TripFormFields;
    label: string;
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    error?: string;
    icon?: boolean;
}) {
    return (
        <div className="space-y-1">
            <Label htmlFor={id} className="text-xs">
                {label}
            </Label>
            <div className="relative">
                {icon && <MapPin className="absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />}
                <Input
                    id={id}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    className={`h-9 text-sm ${icon ? 'pl-8' : ''}`}
                />
            </div>
            <FieldError message={error} />
        </div>
    );
}

interface TripDetailsCardProps {
    data: TripFormFields;
    setData: SetTripField;
    errors: TripErrors;
    vehicles: TripVehicleOption[];
    routes: TripOption[];
    departments: TripOption[];
    /** Only Logistics (assign-vehicles) picks the vehicle */
    canAssignVehicle: boolean;
}

/** Vehicle, route, type, priority, department, locations, remarks */
export function TripDetailsCard({ data, setData, errors, vehicles, routes, departments, canAssignVehicle }: TripDetailsCardProps) {
    const selectedVehicle = vehicles?.find((v) => v.value.toString() === data.vehicle_id);

    return (
        <Card>
            <CardHeader className="px-4 pt-4 pb-3">
                <SectionTitle>Trip Details</SectionTitle>
            </CardHeader>
            <CardContent className="space-y-3 px-4 pb-4">
                {/* Vehicle & Route */}
                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                    {canAssignVehicle ? (
                        <div className="space-y-1">
                            <SearchableSelect
                                label="Vehicle (optional)"
                                name="vehicle_id"
                                value={data.vehicle_id}
                                onChange={(value) => setData('vehicle_id', value)}
                                options={toOptions(vehicles)}
                                placeholder="Search vehicle..."
                                error={errors.vehicle_id}
                            />
                            {selectedVehicle && (
                                <div className="flex items-center gap-1.5 rounded bg-blue-50 px-2 py-1 text-xs text-muted-foreground dark:bg-blue-900/20">
                                    <User className="h-3 w-3" />
                                    Driver: <strong>{driverName(selectedVehicle.driver)}</strong>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-1">
                            <span className="text-sm font-medium">Vehicle</span>
                            <div className="flex items-start gap-1.5 rounded border border-dashed px-3 py-2 text-xs text-muted-foreground">
                                <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                {selectedVehicle
                                    ? `${selectedVehicle.label} (assigned by Logistics)`
                                    : 'Logistics will assign a vehicle after the request is approved.'}
                            </div>
                        </div>
                    )}
                    <SearchableSelect
                        label="Route (Optional)"
                        name="vehicle_route_id"
                        value={data.vehicle_route_id}
                        onChange={(value) => setData('vehicle_route_id', value)}
                        options={toOptions(routes)}
                        placeholder="Search route..."
                        error={errors.vehicle_route_id}
                    />
                </div>

                {/* Trip Type | Priority | Department */}
                <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <SearchableSelect
                        label="Trip Type"
                        name="trip_type"
                        value={data.trip_type}
                        onChange={(value) => setData('trip_type', value)}
                        options={TRIP_TYPES}
                        placeholder="Trip type..."
                        error={errors.trip_type}
                    />
                    <SearchableSelect
                        label="Priority"
                        name="priority"
                        value={data.priority}
                        onChange={(value) => setData('priority', value)}
                        options={PRIORITIES}
                        placeholder="Priority..."
                        required
                        error={errors.priority}
                    />
                    <SearchableSelect
                        label="Requesting Department"
                        name="department_id"
                        value={data.department_id}
                        onChange={(value) => setData('department_id', value)}
                        options={toOptions(departments)}
                        placeholder="Select department..."
                        error={errors.department_id}
                    />
                </div>

                {/* Team Number | Start Location | End Location */}
                <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <TextField
                        id="team_number"
                        label="Team Number"
                        value={data.team_number}
                        onChange={(value) => setData('team_number', value)}
                        placeholder="e.g. TM-001"
                        error={errors.team_number}
                    />
                    <TextField
                        id="start_location"
                        label="Start Location"
                        value={data.start_location}
                        onChange={(value) => setData('start_location', value)}
                        placeholder="Start location"
                        error={errors.start_location}
                        icon
                    />
                    <TextField
                        id="end_location"
                        label="End Location"
                        value={data.end_location}
                        onChange={(value) => setData('end_location', value)}
                        placeholder="End location"
                        error={errors.end_location}
                        icon
                    />
                </div>

                {/* Remarks | Description */}
                <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
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
                    <Label htmlFor="is_return" className="cursor-pointer text-sm">
                        Return Trip
                    </Label>
                </div>
            </CardContent>
        </Card>
    );
}

/** Searchable multi-select with the chosen items as removable chips */
function ChipPicker({
    title,
    name,
    icon,
    options,
    value,
    onChange,
    placeholder,
    emptyText,
    error,
    chipIcon,
}: {
    title: string;
    name: string;
    icon: ReactNode;
    options?: TripOption[];
    value: (string | number)[];
    onChange: (value: (string | number)[]) => void;
    placeholder: string;
    emptyText: string;
    error?: string;
    chipIcon?: ReactNode;
}) {
    return (
        <Card>
            <CardHeader className="px-4 pt-4 pb-2">
                <SectionTitle icon={icon}>
                    {title}
                    {value.length > 0 && (
                        <Badge variant="secondary" className="px-1.5 py-0 text-xs">
                            {value.length}
                        </Badge>
                    )}
                </SectionTitle>
            </CardHeader>
            <CardContent className="space-y-2 px-4 pb-4">
                <SearchableMultiSelect
                    label={title}
                    name={name}
                    hideLabel
                    options={toOptions(options)}
                    value={value}
                    onChange={onChange}
                    placeholder={placeholder}
                />
                {value.length === 0 ? (
                    <div className="rounded-lg border-2 border-dashed py-4 text-center text-muted-foreground">
                        <Building2 className="mx-auto mb-1.5 h-7 w-7 opacity-25" />
                        <p className="text-xs">{emptyText}</p>
                    </div>
                ) : (
                    <div className="flex flex-wrap gap-1.5">
                        {value.map((id) => {
                            const label = options?.find((o) => o.value.toString() === id.toString())?.label ?? id;
                            return (
                                <div key={id} className="flex items-center gap-1 rounded-full border bg-muted/30 px-2 py-0.5 text-xs">
                                    {chipIcon}
                                    <span className="max-w-[140px] truncate font-medium">{label}</span>
                                    <button
                                        type="button"
                                        onClick={() => onChange(value.filter((v) => v.toString() !== id.toString()))}
                                        aria-label={`Remove ${label}`}
                                        className="shrink-0 text-muted-foreground transition-colors hover:text-destructive"
                                    >
                                        <X className="h-3 w-3" />
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                )}
                <FieldError message={error} />
            </CardContent>
        </Card>
    );
}

/** Pick departments (searchable, several at once), then set each one's headcount */
function HeadcountCard({
    departments,
    slots,
    onChange,
    error,
}: {
    departments: TripOption[];
    slots: DeptHeadcount[];
    onChange: (slots: DeptHeadcount[]) => void;
    error?: string;
}) {
    const total = slots.reduce((sum, d) => sum + d.count, 0);

    // Keep counts already entered; new departments start at 1
    const pick = (ids: string[]) => onChange(ids.map((id) => slots.find((s) => s.department_id === id) ?? { department_id: id, count: 1 }));

    const setCount = (id: string, count: number) =>
        onChange(slots.map((s) => (s.department_id === id ? { ...s, count: Math.min(999, Math.max(1, count)) } : s)));

    return (
        <Card>
            <CardHeader className="px-4 pt-4 pb-2">
                <SectionTitle icon={<Users className="h-3.5 w-3.5" />}>
                    Headcount by Department
                    {total > 0 && (
                        <Badge variant="outline" className="px-1.5 py-0 text-xs">
                            {total} total
                        </Badge>
                    )}
                </SectionTitle>
            </CardHeader>
            <CardContent className="space-y-2 px-4 pb-4">
                <SearchableMultiSelect
                    label="Headcount by Department"
                    name="department_slots"
                    hideLabel
                    options={toOptions(departments)}
                    value={slots.map((s) => s.department_id)}
                    onChange={pick}
                    placeholder="Select departments..."
                />
                {slots.length === 0 ? (
                    <div className="rounded-lg border-2 border-dashed py-4 text-center text-muted-foreground">
                        <Users className="mx-auto mb-1.5 h-7 w-7 opacity-25" />
                        <p className="text-xs">No headcount added yet.</p>
                    </div>
                ) : (
                    <div className="space-y-1.5">
                        {slots.map((d) => {
                            const label = departments?.find((dep) => dep.value.toString() === d.department_id)?.label ?? d.department_id;
                            return (
                                <div key={d.department_id} className="flex items-center gap-2 rounded-md border bg-muted/30 py-1 pr-1 pl-2.5">
                                    <Users className="h-3 w-3 shrink-0 text-muted-foreground" />
                                    <span className="flex-1 truncate text-xs font-medium">{label}</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => setCount(d.department_id, d.count - 1)}
                                        className="h-6 w-6"
                                        aria-label={`Fewer people from ${label}`}
                                    >
                                        <Minus className="h-3 w-3" />
                                    </Button>
                                    <span className="w-6 text-center text-xs font-semibold tabular-nums">{d.count}</span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => setCount(d.department_id, d.count + 1)}
                                        className="h-6 w-6"
                                        aria-label={`More people from ${label}`}
                                    >
                                        <Plus className="h-3 w-3" />
                                    </Button>
                                    <button
                                        type="button"
                                        onClick={() => onChange(slots.filter((s) => s.department_id !== d.department_id))}
                                        aria-label={`Remove ${label}`}
                                        className="px-1 text-muted-foreground transition-colors hover:text-destructive"
                                    >
                                        <X className="h-3 w-3" />
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                )}
                <FieldError message={error} />
            </CardContent>
        </Card>
    );
}

interface TripLinksRowProps {
    data: TripFormFields;
    setData: SetTripField;
    errors: TripErrors;
    factories: TripOption[];
    departments: TripOption[];
    logistics: TripOption[];
}

/** Factories visited, headcount per department, and logistics */
export function TripLinksRow({ data, setData, errors, factories, departments, logistics }: TripLinksRowProps) {
    return (
        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
            <ChipPicker
                title="Factories"
                name="factory_ids"
                icon={<Building2 className="h-3.5 w-3.5" />}
                chipIcon={<Building2 className="h-3 w-3 shrink-0 text-muted-foreground" />}
                options={factories}
                value={data.factory_ids}
                onChange={(ids) => setData('factory_ids', ids)}
                placeholder="Select factories..."
                emptyText="No factories selected."
                error={errors.factory_ids}
            />
            <HeadcountCard
                departments={departments}
                slots={data.department_slots}
                onChange={(slots) => setData('department_slots', slots)}
                error={errors.department_slots}
            />
            <ChipPicker
                title="Logistics"
                name="logistics_ids"
                icon={<Building2 className="h-3.5 w-3.5" />}
                options={logistics}
                value={data.logistics_ids}
                onChange={(ids) => setData('logistics_ids', ids)}
                placeholder="Select logistics..."
                emptyText="No logistics selected."
                error={errors.logistics_ids}
            />
        </div>
    );
}

interface TripTimeFieldsProps {
    data: TripFormFields;
    setData: SetTripField;
    errors: TripErrors;
    /** Recurring trips pick a date range instead (create page) */
    showDate?: boolean;
    /** Label prefix for the times, e.g. "Daily" on recurring trips */
    timePrefix?: string;
}

/** Date (single trips) and start/end time */
export function TripTimeFields({ data, setData, errors, showDate = true, timePrefix = '' }: TripTimeFieldsProps) {
    const label = (text: string) => (timePrefix ? `${timePrefix} ${text}` : text);

    return (
        <>
            {showDate && (
                <div className="space-y-1">
                    <Label htmlFor="scheduled_date" className="text-xs">
                        Date *
                    </Label>
                    <Input
                        id="scheduled_date"
                        type="date"
                        value={data.scheduled_date}
                        onChange={(e) => setData('scheduled_date', e.target.value)}
                        className="h-9 text-sm"
                    />
                    <FieldError message={errors.scheduled_date} />
                </div>
            )}
            <div className="space-y-1">
                <Label htmlFor="scheduled_start_time" className="text-xs">
                    {label('Start Time')} *
                </Label>
                <Input
                    id="scheduled_start_time"
                    type="time"
                    value={data.scheduled_start_time}
                    onChange={(e) => setData('scheduled_start_time', e.target.value)}
                    className="h-9 text-sm"
                />
                <FieldError message={errors.scheduled_start_time} />
            </div>
            <div className="space-y-1">
                <Label htmlFor="scheduled_end_time" className="text-xs">
                    {label('End Time')} *
                </Label>
                <Input
                    id="scheduled_end_time"
                    type="time"
                    value={data.scheduled_end_time}
                    onChange={(e) => setData('scheduled_end_time', e.target.value)}
                    className="h-9 text-sm"
                />
                <FieldError message={errors.scheduled_end_time} />
            </div>
        </>
    );
}
