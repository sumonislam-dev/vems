import { BaseForm, FormField, FormFileUpload, FormSelect } from '@/base-components/base-form';
import { FormDateField } from '@/components/date-picker';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { User, Vehicle, Vendor, Zone } from '@/types';
import { ReactNode } from 'react';

/**
 * The vehicle form, shared by pages/vehicles/create.tsx and edit.tsx. Selects
 * send 'none' for "no value"; the server turns it into null (VehicleFormRequest).
 */
export type VehicleForm = {
    brand: string;
    model: string;
    color: string;
    registration_number: string;
    vehicle_type: string;
    rental_type: string;
    capacity: string;
    vendor: string;
    vendor_id: string;
    driver_id: string;
    zone_id: string;
    default_route_id: string;
    is_active: boolean;
    status: string;
    // Parking Location
    parking_address: string;
    parking_latitude: string;
    parking_longitude: string;
    // Tax Token
    tax_token_last_date: string;
    tax_token_number: string;
    tax_token_file: File | null;
    // Fitness Certificate
    fitness_certificate_last_date: string;
    fitness_certificate_number: string;
    fitness_certificate_file: File | null;
    // Route Permit
    route_permit_number: string;
    route_permit_last_date: string;
    route_permit_file: File | null;
    // Insurance
    insurance_type: string;
    insurance_last_date: string;
    insurance_policy_number: string;
    insurance_policy_file: File | null;
    insurance_company: string;
    // Registration Certificate & Owner Info
    registration_certificate_number: string;
    registration_certificate_file: File | null;
    owner_name: string;
    owner_address: string;
    owner_phone: string;
    owner_email: string;
    owner_nid: string;
    // Additional Vehicle Info
    manufacture_year: string;
    engine_number: string;
    chassis_number: string;
    fuel_type: string;
    // Alert Settings
    tax_token_alert_enabled: boolean;
    fitness_alert_enabled: boolean;
    insurance_alert_enabled: boolean;
    route_permit_alert_enabled: boolean;
    alert_days_before: string;
};

type DocumentField = 'tax_token_file' | 'fitness_certificate_file' | 'route_permit_file' | 'insurance_policy_file' | 'registration_certificate_file';

type AlertField = 'tax_token_alert_enabled' | 'fitness_alert_enabled' | 'insurance_alert_enabled' | 'route_permit_alert_enabled';

const STATUS_OPTIONS = [
    { label: 'Active', value: 'true' },
    { label: 'Inactive', value: 'false' },
];

const INSURANCE_TYPE_OPTIONS = [
    { label: 'Select Insurance Type', value: 'none' },
    { label: '1st Party', value: '1st_party' },
    { label: '3rd Party', value: '3rd_party' },
    { label: 'Comprehensive', value: 'comprehensive' },
];

const FUEL_TYPE_OPTIONS = [
    { label: 'Select Fuel Type', value: 'none' },
    { label: 'Petrol', value: 'petrol' },
    { label: 'Diesel', value: 'diesel' },
    { label: 'CNG', value: 'cng' },
    { label: 'Electric', value: 'electric' },
    { label: 'Hybrid', value: 'hybrid' },
];

const VEHICLE_TYPE_OPTIONS = [
    { label: 'Sedan', value: 'sedan' },
    { label: 'SUV', value: 'suv' },
    { label: 'Van', value: 'van' },
    { label: 'Microbus', value: 'microbus' },
    { label: 'Coaster', value: 'coaster' },
    { label: 'Bus', value: 'bus' },
    { label: 'Pickup', value: 'pickup' },
    { label: 'Truck', value: 'truck' },
    { label: 'Other', value: 'other' },
];

const RENTAL_TYPE_OPTIONS = [
    { label: 'Own (Company Owned)', value: 'own' },
    { label: 'Pool (Shared Vehicle)', value: 'pool' },
    { label: 'Rental (Leased)', value: 'rental' },
    { label: 'Ad-hoc Rental', value: 'adhoc' },
    { label: 'Support Vehicle', value: 'support' },
];

const ALERT_TOGGLES: { field: AlertField; label: string }[] = [
    { field: 'tax_token_alert_enabled', label: 'Tax Token Alerts' },
    { field: 'fitness_alert_enabled', label: 'Fitness Alerts' },
    { field: 'insurance_alert_enabled', label: 'Insurance Alerts' },
    { field: 'route_permit_alert_enabled', label: 'Route Permit Alerts' },
];

const toOptions = (items: { id: number; name: string }[], label: (item: { id: number; name: string }) => string = (i) => i.name) =>
    items.map((item) => ({ label: label(item), value: item.id.toString() }));

function Section({ title, columns = 4, children }: { title: string; columns?: 3 | 4; children: ReactNode }) {
    return (
        <div>
            <h3 className="mb-3 text-lg font-medium text-gray-900 dark:text-gray-100">{title}</h3>
            <div className={`grid grid-cols-1 gap-4 sm:grid-cols-2 ${columns === 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-4'}`}>{children}</div>
        </div>
    );
}

interface VehicleFormBodyProps {
    data: VehicleForm;
    setData: <K extends keyof VehicleForm>(field: K, value: VehicleForm[K]) => void;
    errors: Partial<Record<keyof VehicleForm, string>>;
    processing: boolean;
    onSubmit: (e: React.FormEvent) => void;
    vendors: Vendor[];
    drivers: User[];
    zones: Zone[];
    /** The vehicle being edited (adds "View current file" links) */
    vehicle?: Vehicle;
    submitLabel: string;
    submitIcon: ReactNode;
}

export function VehicleFormBody({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    vendors,
    drivers,
    zones,
    vehicle,
    submitLabel,
    submitIcon,
}: VehicleFormBodyProps) {
    // Ad-hoc hires may be saved before the real car is known; the server fills
    // blank identity fields with placeholders (see Vehicle::PLACEHOLDER_*).
    const isAdhoc = data.rental_type === 'adhoc';

    const text = (field: keyof VehicleForm, label: string, placeholder: string, extra: Partial<Parameters<typeof FormField>[0]> = {}) => (
        <FormField
            label={label}
            name={field}
            value={data[field] as string}
            onChange={(value) => setData(field, value as never)}
            error={errors[field] || undefined}
            placeholder={placeholder}
            {...extra}
        />
    );

    const date = (field: keyof VehicleForm, label: string) => (
        <FormDateField
            label={label}
            name={field}
            value={data[field] as string}
            onChange={(value) => setData(field, value as never)}
            error={errors[field] || undefined}
            placeholder="Select expiry date"
        />
    );

    const file = (field: DocumentField, label: string) => (
        <div className="space-y-1">
            <FormFileUpload
                label={label}
                name={field}
                value={data[field]}
                onChange={(value) => setData(field, value)}
                error={errors[field]}
                accept="image/*,.pdf"
                maxSize={2 * 1024 * 1024}
                compact
            />
            {vehicle?.[field] && (
                <a
                    href={route('vehicles.document', [vehicle.id, field])}
                    target="_blank"
                    rel="noreferrer"
                    className="text-xs text-blue-600 hover:underline"
                >
                    View current file
                </a>
            )}
        </div>
    );

    return (
        <BaseForm onSubmit={onSubmit}>
            <div className="space-y-4">
                <Section title="Vehicle Information">
                    {text('brand', 'Brand', isAdhoc ? 'Ad-hoc (auto if blank)' : 'Toyota, Honda, Ford', { required: !isAdhoc })}
                    {text('model', 'Model', isAdhoc ? 'TBD (auto if blank)' : 'Camry, Civic, F-150', { required: !isAdhoc })}
                    {text('color', 'Color (Optional)', 'Red, Blue, White')}
                    {text('registration_number', 'Registration Number', isAdhoc ? 'ADHOC-001 (auto if blank)' : 'ABC-123', {
                        required: !isAdhoc,
                        description: isAdhoc
                            ? vehicle
                                ? 'Leave blank to keep the current number'
                                : 'Leave blank to auto-generate (ADHOC-001, ...)'
                            : undefined,
                    })}

                    <FormSelect
                        label="Vehicle Type"
                        name="vehicle_type"
                        value={data.vehicle_type}
                        onChange={(value) => setData('vehicle_type', value)}
                        options={VEHICLE_TYPE_OPTIONS}
                        error={errors.vehicle_type || undefined}
                        required
                    />
                    <FormSelect
                        label="Rental Type"
                        name="rental_type"
                        value={data.rental_type}
                        onChange={(value) => setData('rental_type', value)}
                        options={RENTAL_TYPE_OPTIONS}
                        error={errors.rental_type || undefined}
                        required
                    />
                    {text('capacity', 'Capacity (Seats)', 'e.g., 4, 7, 15', { type: 'number' })}

                    <SearchableSelect
                        label="Service Provider"
                        name="vendor_id"
                        value={data.vendor_id}
                        onChange={(value) => setData('vendor_id', value)}
                        options={toOptions(vendors)}
                        error={errors.vendor_id || undefined}
                        required={!isAdhoc}
                        placeholder="Search vendor..."
                    />
                    <SearchableSelect
                        label="Driver"
                        name="driver_id"
                        value={data.driver_id}
                        onChange={(value) => setData('driver_id', value)}
                        options={toOptions(drivers, (d) => `${d.name} • ${(d as User).email}`)}
                        error={errors.driver_id || undefined}
                        required={!isAdhoc}
                        placeholder="Search driver..."
                    />
                    <SearchableSelect
                        label="Zone"
                        name="zone_id"
                        value={data.zone_id}
                        onChange={(value) => setData('zone_id', value)}
                        options={toOptions(zones)}
                        error={errors.zone_id || undefined}
                        required
                        placeholder="Search zone..."
                    />

                    <FormSelect
                        label="Active Status"
                        name="is_active"
                        value={data.is_active.toString()}
                        onChange={(value) => setData('is_active', value === 'true')}
                        options={STATUS_OPTIONS}
                        error={errors.is_active || undefined}
                        required
                    />
                </Section>

                <Section title="Parking Location">
                    <div className="sm:col-span-2">{text('parking_address', 'Parking Address', 'Bay A, Gulshan Parking, Dhaka')}</div>
                    {text('parking_latitude', 'Latitude', '23.7809', { type: 'number' })}
                    {text('parking_longitude', 'Longitude', '90.4163', { type: 'number' })}
                </Section>

                <Section title="Vehicle Details">
                    {text('manufacture_year', 'Manufacture Year', 'e.g., 2020', { type: 'number' })}
                    <FormSelect
                        label="Fuel Type (Optional)"
                        name="fuel_type"
                        value={data.fuel_type}
                        onChange={(value) => setData('fuel_type', value)}
                        options={FUEL_TYPE_OPTIONS}
                        error={errors.fuel_type || undefined}
                    />
                    {text('engine_number', 'Engine Number', 'Engine number')}
                    {text('chassis_number', 'Chassis Number', 'Chassis number')}
                </Section>

                <Section title="Documentation" columns={3}>
                    {text('tax_token_number', 'Tax Token Number', 'Tax token number')}
                    {date('tax_token_last_date', 'Tax Token Expiry')}
                    {file('tax_token_file', 'Tax Token File')}
                    {text('fitness_certificate_number', 'Fitness Certificate Number', 'Fitness certificate number')}
                    {date('fitness_certificate_last_date', 'Fitness Certificate Expiry')}
                    {file('fitness_certificate_file', 'Fitness Certificate File')}
                </Section>

                <Section title="Insurance" columns={3}>
                    <FormSelect
                        label="Insurance Type (Optional)"
                        name="insurance_type"
                        value={data.insurance_type}
                        onChange={(value) => setData('insurance_type', value)}
                        options={INSURANCE_TYPE_OPTIONS}
                        error={errors.insurance_type || undefined}
                    />
                    {text('insurance_policy_number', 'Policy Number', 'Policy number')}
                    {text('insurance_company', 'Insurance Company', 'Company name')}
                    {date('insurance_last_date', 'Insurance Expiry')}
                    {file('insurance_policy_file', 'Insurance Policy File')}
                </Section>

                <Section title="Route Permit" columns={3}>
                    {text('route_permit_number', 'Route Permit Number', 'Route permit number')}
                    {date('route_permit_last_date', 'Route Permit Expiry')}
                    {file('route_permit_file', 'Route Permit File')}
                </Section>

                <Section title="Registration & Owner">
                    {text('registration_certificate_number', 'Registration Certificate', 'Certificate number')}
                    {file('registration_certificate_file', 'Registration Certificate File')}
                    {text('owner_name', 'Owner Name', 'Owner name')}
                    {text('owner_phone', 'Owner Phone', 'Phone number')}
                    {text('owner_email', 'Owner Email', 'Email address', { type: 'email' })}
                    {text('owner_nid', 'Owner NID', 'National ID')}
                    <div className="sm:col-span-2">{text('owner_address', 'Owner Address', 'Complete address')}</div>
                </Section>

                {/* One row: the days box, then the four switches lined up with it */}
                <div>
                    <h3 className="mb-3 text-lg font-medium text-gray-900 dark:text-gray-100">Alert Settings</h3>
                    <div className="flex flex-wrap items-end gap-x-8 gap-y-3">
                        <div className="w-full sm:w-48">{text('alert_days_before', 'Alert Days Before Expiry', '30 days', { type: 'number' })}</div>
                        {ALERT_TOGGLES.map(({ field, label }) => (
                            <div key={field} className="flex h-9 items-center space-x-2">
                                <input
                                    type="checkbox"
                                    id={field}
                                    checked={data[field]}
                                    onChange={(e) => setData(field, e.target.checked)}
                                    className="focus:ring-opacity-50 rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 dark:border-gray-600 dark:bg-gray-800 dark:focus:ring-indigo-500"
                                />
                                <label htmlFor={field} className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {label}
                                </label>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <div className="flex gap-3 border-t pt-6">
                <Button type="button" variant="outline" onClick={() => window.history.back()} disabled={processing} size="sm">
                    Cancel
                </Button>
                <Button type="submit" disabled={processing} size="sm">
                    {submitIcon}
                    {submitLabel}
                </Button>
            </div>
        </BaseForm>
    );
}
