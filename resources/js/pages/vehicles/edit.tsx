import { BaseForm, FormField, FormFileUpload, FormSelect } from '@/base-components/base-form';
import { PageHeader } from '@/base-components/page-header';
import { Button } from '@/components/ui/button';
import { SearchableSelect } from '@/components/searchable-select';
import { FormDateField } from '@/components/date-picker';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, Vehicle, Vendor, User } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

interface EditVehicleProps {
    vehicle: Vehicle;
    vendors: Vendor[];
    drivers: User[];
}

type VehicleForm = {
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
    alert_days_before: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vehicles', href: '/vehicles' },
    { title: 'Edit Vehicle', href: '#' },
];

export default function EditVehicle({ vehicle, vendors, drivers = [] }: EditVehicleProps) {
    const vendorValue = typeof vehicle.vendor === 'object' && vehicle.vendor !== null
        ? vehicle.vendor.name
        : (vehicle.vendor || '');

    const { data, setData, post, transform, processing, errors } = useForm<VehicleForm>({
        brand: vehicle.brand || '',
        model: vehicle.model || '',
        color: vehicle.color || '',
        registration_number: vehicle.registration_number || '',
        vehicle_type: vehicle.vehicle_type || 'sedan',
        rental_type: vehicle.rental_type || 'own',
        capacity: vehicle.capacity?.toString() || '',
        vendor: vendorValue,
        vendor_id: vehicle.vendor_id?.toString() || 'none',
        driver_id: vehicle.driver_id?.toString() || 'none',
        is_active: vehicle.is_active ?? true,
        status: vehicle.status || 'available',
        // Parking Location
        parking_address: vehicle.parking_address || '',
        parking_latitude: vehicle.parking_latitude?.toString() || '',
        parking_longitude: vehicle.parking_longitude?.toString() || '',
        // Tax Token
        tax_token_last_date: vehicle.tax_token_last_date || '',
        tax_token_number: vehicle.tax_token_number || '',
        tax_token_file: null,
        // Fitness Certificate
        fitness_certificate_last_date: vehicle.fitness_certificate_last_date || '',
        fitness_certificate_number: vehicle.fitness_certificate_number || '',
        fitness_certificate_file: null,
        // Insurance
        insurance_type: vehicle.insurance_type || 'none',
        insurance_last_date: vehicle.insurance_last_date || '',
        insurance_policy_number: vehicle.insurance_policy_number || '',
        insurance_policy_file: null,
        insurance_company: vehicle.insurance_company || '',
        // Registration Certificate & Owner Info
        registration_certificate_number: vehicle.registration_certificate_number || '',
        registration_certificate_file: null,
        owner_name: vehicle.owner_name || '',
        owner_address: vehicle.owner_address || '',
        owner_phone: vehicle.owner_phone || '',
        owner_email: vehicle.owner_email || '',
        owner_nid: vehicle.owner_nid || '',
        // Additional Vehicle Info
        manufacture_year: vehicle.manufacture_year?.toString() || '',
        engine_number: vehicle.engine_number || '',
        chassis_number: vehicle.chassis_number || '',
        fuel_type: vehicle.fuel_type || 'none',
        // Alert Settings
        tax_token_alert_enabled: vehicle.tax_token_alert_enabled ?? true,
        fitness_alert_enabled: vehicle.fitness_alert_enabled ?? true,
        insurance_alert_enabled: vehicle.insurance_alert_enabled ?? true,
        alert_days_before: vehicle.alert_days_before?.toString() || '30',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        // PHP does not parse multipart/form-data bodies on PUT requests, so a
        // real PUT with file uploads silently arrives with an empty body.
        // Spoofing the method via POST + _method is the standard workaround.
        transform((formData) => ({ ...formData, _method: 'put' }));
        post(route('vehicles.update', vehicle.id));
    };

    const statusOptions = [
        { label: 'Active', value: true },
        { label: 'Inactive', value: false },
    ];

    const operationalStatusOptions = [
        { label: 'Available', value: 'available' },
        { label: 'Assigned', value: 'assigned' },
        { label: 'In Transit', value: 'in_transit' },
        { label: 'Maintenance', value: 'maintenance' },
        { label: 'Out of Service', value: 'out_of_service' },
    ];

    const driverOptions = [
        { label: 'Select Driver', value: 'none' },
        ...drivers.map(driver => ({
            label: `${driver.name} • ${driver.email}`,
            value: driver.id.toString()
        }))
    ];

    const insuranceTypeOptions = [
        { label: 'Select Insurance Type', value: 'none' },
        { label: '1st Party', value: '1st_party' },
        { label: '3rd Party', value: '3rd_party' },
        { label: 'Comprehensive', value: 'comprehensive' },
    ];

    const fuelTypeOptions = [
        { label: 'Select Fuel Type', value: 'none' },
        { label: 'Petrol', value: 'petrol' },
        { label: 'Diesel', value: 'diesel' },
        { label: 'CNG', value: 'cng' },
        { label: 'Electric', value: 'electric' },
        { label: 'Hybrid', value: 'hybrid' },
    ];

    const vehicleTypeOptions = [
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

    const rentalTypeOptions = [
        { label: 'Own (Company Owned)', value: 'own' },
        { label: 'Pool (Shared Vehicle)', value: 'pool' },
        { label: 'Rental (Leased)', value: 'rental' },
        { label: 'Ad-hoc Rental', value: 'adhoc' },
        { label: 'Support Vehicle', value: 'support' },
    ];

    const vendorOptions = [
        { label: 'Select Vendor', value: 'none' },
        ...vendors.map(vendor => ({
            label: vendor.name,
            value: vendor.id.toString()
        }))
    ];

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Vehicle - ${vehicle.brand} ${vehicle.model}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Edit Vehicle - ${vehicle.brand} ${vehicle.model}`}
                    description="Update vehicle information and settings."
                    actions={[
                        {
                            label: 'Back to Vehicles',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: route('vehicles.index'),
                            variant: 'outline',
                        },
                    ]}
                />

                <div className="max-w-6xl">
                    <BaseForm onSubmit={submit}>
                        {/* Vehicle Information */}
                        <div className="space-y-4">
                            <div>
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Vehicle Information</h3>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <FormField
                                label="Brand"
                                name="brand"
                                value={data.brand}
                                onChange={(value) => setData('brand', value)}
                                error={errors.brand || undefined}
                                required
                                placeholder="Toyota, Honda, Ford"
                            />

                            <FormField
                                label="Model"
                                name="model"
                                value={data.model}
                                onChange={(value) => setData('model', value)}
                                error={errors.model || undefined}
                                required
                                placeholder="Camry, Civic, F-150"
                            />

                                    <FormField
                                        label="Color (Optional)"
                                        name="color"
                                        value={data.color}
                                        onChange={(value) => setData('color', value)}
                                        error={errors.color || undefined}
                                        placeholder="Red, Blue, White"
                                    />

                                    <FormField
                                        label="Registration Number"
                                        name="registration_number"
                                        value={data.registration_number}
                                        onChange={(value) => setData('registration_number', value)}
                                        error={errors.registration_number || undefined}
                                        required
                                        placeholder="ABC-123"
                                    />

                                    <FormSelect
                                        label="Vehicle Type"
                                        name="vehicle_type"
                                        value={data.vehicle_type}
                                        onChange={(value) => setData('vehicle_type', value)}
                                        options={vehicleTypeOptions}
                                        error={errors.vehicle_type || undefined}
                                        required
                                    />

                                    <FormSelect
                                        label="Rental Type"
                                        name="rental_type"
                                        value={data.rental_type}
                                        onChange={(value) => setData('rental_type', value)}
                                        options={rentalTypeOptions}
                                        error={errors.rental_type || undefined}
                                        required
                                    />

                                    <FormField
                                        label="Capacity (Seats)"
                                        name="capacity"
                                        type="number"
                                        value={data.capacity}
                                        onChange={(value) => setData('capacity', value)}
                                        error={errors.capacity || undefined}
                                        placeholder="e.g., 4, 7, 15"
                                    />

                                    <SearchableSelect
                                        label="Service Provider"
                                        name="vendor_id"
                                        value={data.vendor_id}
                                        onChange={(value) => setData('vendor_id', value)}
                                        options={vendorOptions}
                                        error={errors.vendor_id || undefined}
                                        required
                                        placeholder="Select vendor..."
                                    />

                                    <SearchableSelect
                                        label="Driver"
                                        name="driver_id"
                                        value={data.driver_id}
                                        onChange={(value) => setData('driver_id', value)}
                                        options={driverOptions}
                                        error={errors.driver_id || undefined}
                                        required
                                        placeholder="Select driver..."
                                    />
                                </div>
                            </div>

                            {/* Parking Location */}
                            <div>
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Parking Location</h3>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <div className="sm:col-span-2">
                                        <FormField
                                            label="Parking Address"
                                            name="parking_address"
                                            value={data.parking_address}
                                            onChange={(value) => setData('parking_address', value)}
                                            error={errors.parking_address || undefined}
                                            placeholder="Bay A, Gulshan Parking, Dhaka"
                                        />
                                    </div>

                                    <FormField
                                        label="Latitude"
                                        name="parking_latitude"
                                        type="number"
                                        value={data.parking_latitude}
                                        onChange={(value) => setData('parking_latitude', value)}
                                        error={errors.parking_latitude || undefined}
                                        placeholder="23.7809"
                                    />

                                    <FormField
                                        label="Longitude"
                                        name="parking_longitude"
                                        type="number"
                                        value={data.parking_longitude}
                                        onChange={(value) => setData('parking_longitude', value)}
                                        error={errors.parking_longitude || undefined}
                                        placeholder="90.4163"
                                    />
                                </div>
                            </div>

                            {/* Vehicle Details */}
                            <div>
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Vehicle Details</h3>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <FormField
                                        label="Manufacture Year"
                                        name="manufacture_year"
                                        type="number"
                                        value={data.manufacture_year}
                                        onChange={(value) => setData('manufacture_year', value)}
                                        error={errors.manufacture_year || undefined}
                                        placeholder="e.g., 2020"
                                    />

                                    <FormSelect
                                        label="Fuel Type (Optional)"
                                        name="fuel_type"
                                        value={data.fuel_type}
                                        onChange={(value) => setData('fuel_type', value)}
                                        options={fuelTypeOptions}
                                        error={errors.fuel_type || undefined}
                                    />

                                    <FormField
                                        label="Engine Number"
                                        name="engine_number"
                                        value={data.engine_number}
                                        onChange={(value) => setData('engine_number', value)}
                                        error={errors.engine_number || undefined}
                                        placeholder="Engine number"
                                    />

                                    <FormSelect
                                        label="Active Status"
                                        name="is_active"
                                        value={data.is_active.toString()}
                                        onChange={(value) => setData('is_active', value === 'true')}
                                        options={statusOptions.map(option => ({
                                            label: option.label,
                                            value: option.value.toString()
                                        }))}
                                        error={errors.is_active || undefined}
                                        required
                                    />

                                    <FormSelect
                                        label="Operational Status"
                                        name="status"
                                        value={data.status}
                                        onChange={(value) => setData('status', value)}
                                        options={operationalStatusOptions}
                                        error={errors.status || undefined}
                                    />

                                    <FormField
                                        label="Chassis Number"
                                        name="chassis_number"
                                        value={data.chassis_number}
                                        onChange={(value) => setData('chassis_number', value)}
                                        error={errors.chassis_number || undefined}
                                        placeholder="Chassis number"
                                    />
                                </div>
                            </div>

                            {/* Tax Token Information */}
                            <div>
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Tax Token Information</h3>
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <FormField
                                        label="Tax Token Number"
                                        name="tax_token_number"
                                        value={data.tax_token_number}
                                        onChange={(value) => setData('tax_token_number', value)}
                                        error={errors.tax_token_number || undefined}
                                        placeholder="Tax token number"
                                    />

                                    <FormDateField
                                        label="Tax Token Last Date"
                                        name="tax_token_last_date"
                                        value={data.tax_token_last_date}
                                        onChange={(value) => setData('tax_token_last_date', value)}
                                        error={errors.tax_token_last_date || undefined}
                                        helpText="Select the expiry date of the tax token"
                                        placeholder="Select tax token expiry date"
                                    />

                                    <div className="space-y-1">
                                        <FormFileUpload
                                            label="Tax Token File"
                                            name="tax_token_file"
                                            value={data.tax_token_file}
                                            onChange={(file) => setData('tax_token_file', file)}
                                            error={errors.tax_token_file}
                                            accept="image/*,.pdf"
                                            maxSize={2 * 1024 * 1024}
                                        />
                                        {vehicle.tax_token_file && (
                                            <a href={`/storage/${vehicle.tax_token_file}`} target="_blank" rel="noreferrer" className="text-xs text-blue-600 hover:underline">
                                                View current file
                                            </a>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Fitness Certificate Information */}
                            <div>
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Fitness Certificate Information</h3>
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <FormField
                                        label="Fitness Certificate Number"
                                        name="fitness_certificate_number"
                                        value={data.fitness_certificate_number}
                                        onChange={(value) => setData('fitness_certificate_number', value)}
                                        error={errors.fitness_certificate_number || undefined}
                                        placeholder="Fitness certificate number"
                                    />

                                    <FormDateField
                                        label="Fitness Certificate Last Date"
                                        name="fitness_certificate_last_date"
                                        value={data.fitness_certificate_last_date}
                                        onChange={(value) => setData('fitness_certificate_last_date', value)}
                                        error={errors.fitness_certificate_last_date || undefined}
                                        helpText="Select the expiry date of the fitness certificate"
                                        placeholder="Select fitness certificate expiry date"
                                    />

                                    <div className="space-y-1">
                                        <FormFileUpload
                                            label="Fitness Certificate File"
                                            name="fitness_certificate_file"
                                            value={data.fitness_certificate_file}
                                            onChange={(file) => setData('fitness_certificate_file', file)}
                                            error={errors.fitness_certificate_file}
                                            accept="image/*,.pdf"
                                            maxSize={2 * 1024 * 1024}
                                        />
                                        {vehicle.fitness_certificate_file && (
                                            <a href={`/storage/${vehicle.fitness_certificate_file}`} target="_blank" rel="noreferrer" className="text-xs text-blue-600 hover:underline">
                                                View current file
                                            </a>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Insurance Information */}
                            <div>
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Insurance Information</h3>
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                    <FormSelect
                                        label="Insurance Type (Optional)"
                                        name="insurance_type"
                                        value={data.insurance_type}
                                        onChange={(value) => setData('insurance_type', value)}
                                        options={insuranceTypeOptions}
                                        error={errors.insurance_type || undefined}
                                    />

                                    <FormDateField
                                        label="Insurance Last Date"
                                        name="insurance_last_date"
                                        value={data.insurance_last_date}
                                        onChange={(value) => setData('insurance_last_date', value)}
                                        error={errors.insurance_last_date || undefined}
                                        helpText="Select the expiry date of the insurance policy"
                                        placeholder="Select insurance expiry date"
                                    />

                                    <FormField
                                        label="Insurance Policy Number"
                                        name="insurance_policy_number"
                                        value={data.insurance_policy_number}
                                        onChange={(value) => setData('insurance_policy_number', value)}
                                        error={errors.insurance_policy_number || undefined}
                                        placeholder="Policy number"
                                    />

                                    <FormField
                                        label="Insurance Company"
                                        name="insurance_company"
                                        value={data.insurance_company}
                                        onChange={(value) => setData('insurance_company', value)}
                                        error={errors.insurance_company || undefined}
                                        placeholder="Insurance company name"
                                    />

                                    <div className="space-y-1">
                                        <FormFileUpload
                                            label="Insurance Policy File"
                                            name="insurance_policy_file"
                                            value={data.insurance_policy_file}
                                            onChange={(file) => setData('insurance_policy_file', file)}
                                            error={errors.insurance_policy_file}
                                            accept="image/*,.pdf"
                                            maxSize={2 * 1024 * 1024}
                                        />
                                        {vehicle.insurance_policy_file && (
                                            <a href={`/storage/${vehicle.insurance_policy_file}`} target="_blank" rel="noreferrer" className="text-xs text-blue-600 hover:underline">
                                                View current file
                                            </a>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Registration & Owner */}
                            <div>
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Registration & Owner</h3>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <FormField
                                        label="Registration Certificate"
                                        name="registration_certificate_number"
                                        value={data.registration_certificate_number}
                                        onChange={(value) => setData('registration_certificate_number', value)}
                                        error={errors.registration_certificate_number || undefined}
                                        placeholder="Certificate number"
                                    />

                                    <div className="space-y-1">
                                        <FormFileUpload
                                            label="Registration Certificate File"
                                            name="registration_certificate_file"
                                            value={data.registration_certificate_file}
                                            onChange={(file) => setData('registration_certificate_file', file)}
                                            error={errors.registration_certificate_file}
                                            accept="image/*,.pdf"
                                            maxSize={2 * 1024 * 1024}
                                        />
                                        {vehicle.registration_certificate_file && (
                                            <a href={`/storage/${vehicle.registration_certificate_file}`} target="_blank" rel="noreferrer" className="text-xs text-blue-600 hover:underline">
                                                View current file
                                            </a>
                                        )}
                                    </div>

                                    <FormField
                                        label="Owner Name"
                                        name="owner_name"
                                        value={data.owner_name}
                                        onChange={(value) => setData('owner_name', value)}
                                        error={errors.owner_name || undefined}
                                        placeholder="Owner name"
                                    />

                                    <FormField
                                        label="Owner Phone"
                                        name="owner_phone"
                                        value={data.owner_phone}
                                        onChange={(value) => setData('owner_phone', value)}
                                        error={errors.owner_phone || undefined}
                                        placeholder="Phone number"
                                    />

                                    <FormField
                                        label="Owner Email"
                                        name="owner_email"
                                        type="email"
                                        value={data.owner_email}
                                        onChange={(value) => setData('owner_email', value)}
                                        error={errors.owner_email || undefined}
                                        placeholder="Email address"
                                    />

                                    <FormField
                                        label="Owner NID"
                                        name="owner_nid"
                                        value={data.owner_nid}
                                        onChange={(value) => setData('owner_nid', value)}
                                        error={errors.owner_nid || undefined}
                                        placeholder="National ID"
                                    />

                                    <div className="lg:col-span-3">
                                        <FormField
                                            label="Owner Address"
                                            name="owner_address"
                                            value={data.owner_address}
                                            onChange={(value) => setData('owner_address', value)}
                                            error={errors.owner_address || undefined}
                                            placeholder="Complete address"
                                            multiline
                                            rows={2}
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Alert Settings */}
                            <div>
                                <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">Alert Settings</h3>
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    <FormField
                                        label="Alert Days Before Expiry"
                                        name="alert_days_before"
                                        type="number"
                                        value={data.alert_days_before}
                                        onChange={(value) => setData('alert_days_before', value)}
                                        error={errors.alert_days_before || undefined}
                                        placeholder="30 days"
                                    />

                                    <div className="flex items-center space-x-2">
                                        <input
                                            type="checkbox"
                                            id="tax_token_alert_enabled"
                                            checked={data.tax_token_alert_enabled}
                                            onChange={(e) => setData('tax_token_alert_enabled', e.target.checked)}
                                            className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-800 dark:focus:ring-indigo-500"
                                        />
                                        <label htmlFor="tax_token_alert_enabled" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Tax Token Alerts
                                        </label>
                                    </div>

                                    <div className="flex items-center space-x-2">
                                        <input
                                            type="checkbox"
                                            id="fitness_alert_enabled"
                                            checked={data.fitness_alert_enabled}
                                            onChange={(e) => setData('fitness_alert_enabled', e.target.checked)}
                                            className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-800 dark:focus:ring-indigo-500"
                                        />
                                        <label htmlFor="fitness_alert_enabled" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Fitness Alerts
                                        </label>
                                    </div>

                                    <div className="flex items-center space-x-2">
                                        <input
                                            type="checkbox"
                                            id="insurance_alert_enabled"
                                            checked={data.insurance_alert_enabled}
                                            onChange={(e) => setData('insurance_alert_enabled', e.target.checked)}
                                            className="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-800 dark:focus:ring-indigo-500"
                                        />
                                        <label htmlFor="insurance_alert_enabled" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Insurance Alerts
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="flex gap-3 pt-6 border-t">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => window.history.back()}
                                disabled={processing}
                                size="sm"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                size="sm"
                            >
                                <Save className="h-4 w-4 mr-2" />
                                Update Vehicle
                            </Button>
                        </div>
                    </BaseForm>
                </div>
            </div>
        </AppSidebarLayout>
    );
}
