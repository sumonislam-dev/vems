import { PageHeader } from '@/base-components/page-header';
import { blankContact, VendorForm, VendorFormBody } from '@/components/vendor-form-body';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, Vendor, Zone } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

interface EditVendorProps {
    vendor: Vendor;
    zones: Zone[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vendors', href: '/vendors' },
    { title: 'Edit Vendor', href: '#' },
];

export default function EditVendor({ vendor, zones }: EditVendorProps) {
    const { data, setData, post, transform, processing, errors } = useForm<VendorForm>({
        name: vendor.name,
        address: vendor.address || '',
        phone: vendor.phone || '',
        email: vendor.email || '',
        website: vendor.website || '',
        description: vendor.description || '',
        status: vendor.status,
        zone_id: vendor.zone_id?.toString() || '',
        trade_license: vendor.trade_license || '',
        trade_license_file: null,
        tin: vendor.tin || '',
        tin_file: null,
        bin: vendor.bin || '',
        bin_file: null,
        tax_return: vendor.tax_return || '',
        tax_return_file: null,
        bank_details: vendor.bank_details || '',
        contact_persons: vendor.contact_persons?.length
            ? vendor.contact_persons.map((contact) => ({
                  id: contact.id,
                  name: contact.name,
                  position: contact.position || '',
                  phone: contact.phone || '',
                  email: contact.email || '',
                  is_primary: contact.is_primary,
                  notes: contact.notes || '',
              }))
            : [blankContact(true)],
    });

    // POST with _method=PUT so PHP reads the multipart file fields
    transform((form) => ({ ...form, _method: 'PUT' }));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('vendors.update', vendor.id), { forceFormData: true });
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Vendor - ${vendor.name}`} />

            <div className="space-y-4">
                <PageHeader
                    title={`Edit ${vendor.name}`}
                    description="Update vendor information and contact details."
                    actions={[
                        {
                            label: 'Back to Vendors',
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
                    vendor={vendor}
                    submitLabel="Update Vendor"
                    submitIcon={<Save className="mr-1 h-3.5 w-3.5" />}
                />
            </div>
        </AppSidebarLayout>
    );
}
