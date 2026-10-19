import { PageHeader } from '@/base-components/page-header';
import { blankContact, VendorForm, VendorFormBody } from '@/components/vendor-form-body';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, Zone } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';

interface CreateVendorProps {
    zones: Zone[];
    defaultZoneId: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vendors', href: '/vendors' },
    { title: 'Create Vendor', href: '#' },
];

export default function CreateVendor({ zones, defaultZoneId }: CreateVendorProps) {
    const { data, setData, post, processing, errors } = useForm<VendorForm>({
        name: '',
        address: '',
        phone: '',
        email: '',
        website: '',
        description: '',
        status: 'active',
        zone_id: defaultZoneId?.toString() || '',
        trade_license: '',
        trade_license_file: null,
        tin: '',
        tin_file: null,
        bin: '',
        bin_file: null,
        tax_return: '',
        tax_return_file: null,
        bank_details: '',
        contact_persons: [blankContact(true)],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('vendors.store'), { forceFormData: true });
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Vendor" />

            <div className="space-y-4">
                <PageHeader
                    title="Create Vendor / Service Provider"
                    description="Add a new service provider with contact information"
                    actions={[
                        {
                            label: 'Back',
                            icon: <ArrowLeft className="mr-1 h-4 w-4" />,
                            href: route('vendors.index'),
                            variant: 'outline',
                        },
                    ]}
                />

                <VendorFormBody
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={submit}
                    zones={zones}
                    submitLabel="Create Vendor"
                    submitIcon={<Plus className="mr-1 h-3.5 w-3.5" />}
                />
            </div>
        </AppSidebarLayout>
    );
}
