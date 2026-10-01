import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, Plus, Building2 } from 'lucide-react';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { PageHeader } from '@/base-components/page-header';
import { BaseForm, FormField, FormSelect, FormTextarea } from '@/base-components/base-form';
import { useFormValidation, commonValidationRules, ValidationRules } from '@/hooks/use-form-validation';
import { BreadcrumbItem, Zone } from '@/types';

type FactoryForm = {
    account_id: string;
    name: string;
    status: string;
    address: string;
    city: string;
    latitude: string;
    longitude: string;
    mileage_km: string;
    zone_id: string;
};

interface CreateFactoryProps {
    zones: Zone[];
    defaultZoneId: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Factories', href: '/factories' },
    { title: 'Create', href: '/factories/create' },
];

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
];

export default function CreateFactory({ zones, defaultZoneId }: CreateFactoryProps) {
    const {
        data,
        setData,
        post,
        processing,
        errors: serverErrors,
    } = useForm<Required<FactoryForm>>({
        account_id: '',
        name: '',
        status: 'active',
        address: '',
        city: '',
        zone_id: defaultZoneId?.toString() || '',
        latitude: '',
        longitude: '',
        mileage_km: '',
    });

    // Define validation rules
    const validationRules: ValidationRules<FactoryForm> = {
        account_id: [
            commonValidationRules.required('Account ID is required'),
            commonValidationRules.maxLength(255, 'Account ID must be less than 255 characters'),
        ],
        name: [
            commonValidationRules.required('Factory name is required'),
            commonValidationRules.minLength(2, 'Factory name must be at least 2 characters'),
            commonValidationRules.maxLength(255, 'Factory name must be less than 255 characters'),
        ],
        status: [
            commonValidationRules.required('Status is required'),
            commonValidationRules.oneOf(['active', 'inactive'], 'Invalid status selected'),
        ],
        address: [commonValidationRules.maxLength(1000, 'Address must be less than 1000 characters')],
        city: [commonValidationRules.maxLength(255, 'City must be less than 255 characters')],
        latitude: [
            (value) => {
                if (!value) return undefined;
                const numValue = parseFloat(value);
                if (isNaN(numValue)) return 'Latitude must be a valid number';
                if (numValue < -90 || numValue > 90) return 'Latitude must be between -90 and 90';
                return undefined;
            },
        ],
        longitude: [
            (value) => {
                if (!value) return undefined;
                const numValue = parseFloat(value);
                if (isNaN(numValue)) return 'Longitude must be a valid number';
                if (numValue < -180 || numValue > 180) return 'Longitude must be between -180 and 180';
                return undefined;
            },
        ],
        mileage_km: [
            (value) => {
                if (!value) return undefined;
                const numValue = parseFloat(value);
                if (isNaN(numValue)) return 'Mileage must be a valid number';
                if (numValue < 0) return 'Mileage must be a positive number';
                if (numValue > 99999.99) return 'Mileage cannot exceed 99,999.99 km';
                return undefined;
            },
        ],
    };

    // Initialize validation hook
    const { errors: clientErrors, validate, clearError, hasErrors } = useFormValidation(validationRules);

    // Combine server and client errors (server errors take precedence)
    const getFieldError = (field: keyof FactoryForm): string | undefined => {
        return serverErrors[field] || clientErrors[field];
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Run client-side validation
        const validationErrors = validate(data);

        if (hasErrors(validationErrors)) {
            return;
        }

        // Submit the form
        post(route('factories.store'), {
            onSuccess: () => {
                // Redirect will be handled by the controller
            },
            onError: () => {
                // Inertia binds server errors to form.errors automatically
            },
        });
    };

    // Clear client error when user starts typing
    const handleFieldChange = (field: keyof FactoryForm, value: string) => {
        setData(field, value);
        clearError(field);
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Factory" />

            <div className="space-y-6">
                <PageHeader
                    title="Create Factory"
                    description="Add a new factory to the system"
                    actions={[
                        {
                            label: 'Back to Factories',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: route('factories.index'),
                            variant: 'outline',
                        },
                    ]}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center">
                            <Building2 className="mr-2 h-5 w-5" />
                            Factory Details
                        </CardTitle>
                        <CardDescription>Enter the factory information below</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <BaseForm onSubmit={submit}>
                            <div className="grid gap-6 md:grid-cols-2">
                                <FormField
                                    label="Account ID"
                                    name="account_id"
                                    value={data.account_id}
                                    onChange={(e) => handleFieldChange('account_id', e)}
                                    error={getFieldError('account_id')}
                                    required
                                    placeholder="e.g., 9178"
                                    description="Unique identifier for this factory"
                                />

                                <FormField
                                    label="Factory Name"
                                    name="name"
                                    value={data.name}
                                    onChange={(e) => handleFieldChange('name', e)}
                                    error={getFieldError('name')}
                                    required
                                    placeholder="e.g., A.K.M. KNIT WEAR LTD."
                                />

                                <FormSelect
                                    label="Status"
                                    name="status"
                                    value={data.status}
                                    onChange={(e) => handleFieldChange('status', e)}
                                    options={statusOptions}
                                    error={getFieldError('status')}
                                    required
                                />

                                <FormField
                                    label="City"
                                    name="city"
                                    value={data.city}
                                    onChange={(e) => handleFieldChange('city', e)}
                                    error={getFieldError('city')}
                                    placeholder="e.g., Dhaka"
                                />

                                <FormSelect
                                    label="Zone"
                                    name="zone_id"
                                    value={data.zone_id}
                                    onChange={(e) => handleFieldChange('zone_id', e)}
                                    options={zones.map((zone) => ({ label: zone.name, value: zone.id.toString() }))}
                                    error={getFieldError('zone_id')}
                                    placeholder="Select zone..."
                                />
                            </div>

                            <FormTextarea
                                label="Address"
                                name="address"
                                value={data.address}
                                onChange={(e) => handleFieldChange('address', e)}
                                error={getFieldError('address')}
                                placeholder="e.g., 14 No. Genda, Karnopara, Ulail, Savar"
                                rows={3}
                                description="Full street address"
                            />

                            <div className="grid gap-6 md:grid-cols-3">
                                <FormField
                                    label="Latitude"
                                    name="latitude"
                                    type="number"
                                    value={data.latitude}
                                    onChange={(e) => handleFieldChange('latitude', e)}
                                    error={getFieldError('latitude')}
                                    placeholder="e.g., 23.82743"
                                    description="Decimal degrees (-90 to 90)"
                                />

                                <FormField
                                    label="Longitude"
                                    name="longitude"
                                    type="number"
                                    value={data.longitude}
                                    onChange={(e) => handleFieldChange('longitude', e)}
                                    error={getFieldError('longitude')}
                                    placeholder="e.g., 90.25867"
                                    description="Decimal degrees (-180 to 180)"
                                />

                                <FormField
                                    label="Mileage (km)"
                                    name="mileage_km"
                                    type="number"
                                    value={data.mileage_km}
                                    onChange={(e) => handleFieldChange('mileage_km', e)}
                                    error={getFieldError('mileage_km')}
                                    placeholder="e.g., 24.00"
                                    description="Single way distance"
                                />
                            </div>

                            <div className="flex justify-end space-x-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => window.history.back()}
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    {processing ? 'Creating...' : 'Create Factory'}
                                </Button>
                            </div>
                        </BaseForm>
                    </CardContent>
                </Card>
            </div>
        </AppSidebarLayout>
    );
}
