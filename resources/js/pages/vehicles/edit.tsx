import { PageHeader } from '@/base-components/page-header';
import { VehicleForm, VehicleFormBody } from '@/components/vehicle-form-body';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, User, Vehicle, Vendor, Zone } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

interface EditVehicleProps {
    vehicle: Vehicle;
    vendors: Vendor[];
    drivers: User[];
    zones: Zone[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vehicles', href: '/vehicles' },
    { title: 'Edit Vehicle', href: '#' },
];

export default function EditVehicle({ vehicle, vendors = [], drivers = [], zones = [] }: EditVehicleProps) {
    const vendorValue = typeof vehicle.vendor === 'object' && vehicle.vendor !== null ? vehicle.vendor.name : vehicle.vendor || '';

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
        zone_id: vehicle.zone_id?.toString() || 'none',
        default_route_id: vehicle.default_route_id?.toString() || 'none',
        is_active: vehicle.is_active ?? true,
        status: vehicle.status || 'available',
        parking_address: vehicle.parking_address || '',
        parking_latitude: vehicle.parking_latitude?.toString() || '',
        parking_longitude: vehicle.parking_longitude?.toString() || '',
        tax_token_last_date: vehicle.tax_token_last_date || '',
        tax_token_number: vehicle.tax_token_number || '',
        tax_token_file: null,
        fitness_certificate_last_date: vehicle.fitness_certificate_last_date || '',
        fitness_certificate_number: vehicle.fitness_certificate_number || '',
        fitness_certificate_file: null,
        route_permit_number: vehicle.route_permit_number || '',
        route_permit_last_date: vehicle.route_permit_last_date || '',
        route_permit_file: null,
        insurance_type: vehicle.insurance_type || 'none',
        insurance_last_date: vehicle.insurance_last_date || '',
        insurance_policy_number: vehicle.insurance_policy_number || '',
        insurance_policy_file: null,
        insurance_company: vehicle.insurance_company || '',
        registration_certificate_number: vehicle.registration_certificate_number || '',
        registration_certificate_file: null,
        owner_name: vehicle.owner_name || '',
        owner_address: vehicle.owner_address || '',
        owner_phone: vehicle.owner_phone || '',
        owner_email: vehicle.owner_email || '',
        owner_nid: vehicle.owner_nid || '',
        manufacture_year: vehicle.manufacture_year?.toString() || '',
        engine_number: vehicle.engine_number || '',
        chassis_number: vehicle.chassis_number || '',
        fuel_type: vehicle.fuel_type || 'none',
        tax_token_alert_enabled: vehicle.tax_token_alert_enabled ?? true,
        fitness_alert_enabled: vehicle.fitness_alert_enabled ?? true,
        insurance_alert_enabled: vehicle.insurance_alert_enabled ?? true,
        route_permit_alert_enabled: vehicle.route_permit_alert_enabled ?? true,
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
                    <VehicleFormBody
                        data={data}
                        setData={setData}
                        errors={errors}
                        processing={processing}
                        onSubmit={submit}
                        vendors={vendors}
                        drivers={drivers}
                        zones={zones}
                        vehicle={vehicle}
                        submitLabel="Update Vehicle"
                        submitIcon={<Save className="mr-2 h-4 w-4" />}
                    />
                </div>
            </div>
        </AppSidebarLayout>
    );
}
