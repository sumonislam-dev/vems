import { PageHeader } from '@/base-components/page-header';
import { VehicleForm, VehicleFormBody } from '@/components/vehicle-form-body';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, User, Vendor, Zone } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';

interface CreateVehicleProps {
    vendors: Vendor[];
    drivers: User[];
    zones: Zone[];
    defaultZoneId: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vehicles', href: '/vehicles' },
    { title: 'Create Vehicle', href: '#' },
];

export default function CreateVehicle({ vendors = [], drivers = [], zones = [], defaultZoneId = null }: CreateVehicleProps) {
    const { data, setData, post, processing, errors } = useForm<VehicleForm>({
        brand: '',
        model: '',
        color: '',
        registration_number: '',
        vehicle_type: 'sedan',
        rental_type: 'own',
        capacity: '',
        vendor: '',
        vendor_id: 'none',
        driver_id: 'none',
        zone_id: defaultZoneId?.toString() || 'none',
        default_route_id: 'none',
        is_active: true,
        status: 'available',
        parking_address: '',
        parking_latitude: '',
        parking_longitude: '',
        tax_token_last_date: '',
        tax_token_number: '',
        tax_token_file: null,
        fitness_certificate_last_date: '',
        fitness_certificate_number: '',
        fitness_certificate_file: null,
        route_permit_number: '',
        route_permit_last_date: '',
        route_permit_file: null,
        insurance_type: 'none',
        insurance_last_date: '',
        insurance_policy_number: '',
        insurance_policy_file: null,
        insurance_company: '',
        registration_certificate_number: '',
        registration_certificate_file: null,
        owner_name: '',
        owner_address: '',
        owner_phone: '',
        owner_email: '',
        owner_nid: '',
        manufacture_year: '',
        engine_number: '',
        chassis_number: '',
        fuel_type: 'none',
        tax_token_alert_enabled: true,
        fitness_alert_enabled: true,
        insurance_alert_enabled: true,
        route_permit_alert_enabled: true,
        alert_days_before: '30',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('vehicles.store'));
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Vehicle" />

            <div className="space-y-6">
                <PageHeader
                    title="Create Vehicle"
                    description="Add a new vehicle to your fleet."
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
                        submitLabel="Create Vehicle"
                        submitIcon={<Plus className="mr-2 h-4 w-4" />}
                    />
                </div>
            </div>
        </AppSidebarLayout>
    );
}
