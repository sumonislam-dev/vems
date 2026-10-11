import { BaseForm, FormField, FormSelect } from '@/base-components/base-form';
import { PageHeader } from '@/base-components/page-header';
import { useFlashMessage } from '@/components/flash-messages';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, Vendor, Zone } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, LayoutGrid, Save, Trash2, UserPlus } from 'lucide-react';

interface EditVendorProps {
    vendor: Vendor;
    zones: Zone[];
}

type ContactPerson = {
    id?: number;
    name: string;
    position: string;
    phone: string;
    email: string;
    is_primary: boolean;
    notes: string;
};

type VendorForm = {
    _method: string;
    name: string;
    address: string;
    phone: string;
    email: string;
    website: string;
    description: string;
    status: string;
    trade_license: string;
    trade_license_file: File | null;
    tin: string;
    tin_file: File | null;
    bin: string;
    bin_file: File | null;
    tax_return: string;
    tax_return_file: File | null;
    bank_details: string;
    zone_id: string;
    contact_persons: ContactPerson[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vendors', href: '/vendors' },
    { title: 'Edit Vendor', href: '#' },
];

export default function EditVendor({ vendor, zones }: EditVendorProps) {
    const { showError } = useFlashMessage();

    const { data, setData, post, processing, errors } = useForm<VendorForm>({
        _method: 'PUT',
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
        contact_persons:
            vendor.contact_persons && vendor.contact_persons.length > 0
                ? vendor.contact_persons.map((contact) => ({
                      id: contact.id,
                      name: contact.name,
                      position: contact.position || '',
                      phone: contact.phone || '',
                      email: contact.email || '',
                      is_primary: contact.is_primary,
                      notes: contact.notes || '',
                  }))
                : [
                      {
                          name: '',
                          position: '',
                          phone: '',
                          email: '',
                          is_primary: true,
                          notes: '',
                      },
                      {
                          name: '',
                          position: '',
                          phone: '',
                          email: '',
                          is_primary: false,
                          notes: '',
                      },
                  ],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        // Only keep contacts with names (valid contacts)
        const validContactPersons = data.contact_persons.filter((c) => c.name.trim() !== '');
        const totalValidContacts = validContactPersons.length;

        if (totalValidContacts < 1) {
            showError('Please provide at least 1 contact person with a name.');
            return;
        }

        // Use method spoofing: POST + _method=PUT in FormData so PHP processes multipart fields.
        post(route('vendors.update', vendor.id), {
            forceFormData: true,
            onError: (errors) => {
                // Only show programmatic error if no validation errors (server error)
                if (Object.keys(errors).length === 0) {
                    showError('Failed to update vendor. Please try again.');
                }
            },
            onSuccess: () => {
                // redirect handled by controller
            },
        });
    };

    const handleFileChange = (field: 'trade_license_file' | 'tin_file' | 'bin_file' | 'tax_return_file', e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            setData(field, e.target.files[0]);
        }
    };

    const statusOptions = [
        { label: 'Active', value: 'active' },
        { label: 'Inactive', value: 'inactive' },
    ];

    const addContactPerson = () => {
        setData('contact_persons', [
            ...data.contact_persons,
            {
                name: `Contact Person ${data.contact_persons.length + 1}`,
                position: '',
                phone: '',
                email: '',
                is_primary: false,
                notes: '',
            },
        ]);
    };

    const removeContactPerson = (index: number) => {
        if (data.contact_persons.length > 1) {
            const newContacts = data.contact_persons.filter((_, i) => i !== index);
            setData('contact_persons', newContacts);
        }
    };

    const updateContactPerson = (index: number, field: keyof ContactPerson, value: string | boolean) => {
        const newContacts = [...data.contact_persons];
        newContacts[index] = { ...newContacts[index], [field]: value };

        // If setting as primary, unset others
        if (field === 'is_primary' && value === true) {
            newContacts.forEach((contact, i) => {
                if (i !== index) {
                    contact.is_primary = false;
                }
            });
        }

        setData('contact_persons', newContacts);
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Vendor - ${vendor.name}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Edit ${vendor.name}`}
                    description="Update vendor information and contact details."
                    actions={[
                        {
                            label: 'Back to Vendors',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: route('vendors.index'),
                            variant: 'outline',
                        },
                    ]}
                />

                <div className="max-w-4xl">
                    <BaseForm onSubmit={submit}>
                        <div className="space-y-6">
                            {/* Basic Information */}
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="flex items-center text-base">
                                        <LayoutGrid className="mr-2 h-4 w-4" />
                                        Basic Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="pt-2">
                                    <div className="grid grid-cols-1 gap-x-4 gap-y-2">
                                        <div className="grid grid-cols-12 gap-x-4 gap-y-2">
                                            <div className="col-span-6">
                                                <FormField
                                                    label="Service Provider Name"
                                                    name="name"
                                                    value={data.name}
                                                    onChange={(value) => setData('name', value)}
                                                    error={errors.name}
                                                    required
                                                    placeholder="Enter provider name"
                                                    className="text-sm"
                                                />
                                            </div>

                                            <div className="col-span-2">
                                                <FormSelect
                                                    label="Status"
                                                    name="status"
                                                    value={data.status}
                                                    onChange={(value) => setData('status', value)}
                                                    options={statusOptions}
                                                    error={errors.status}
                                                    required
                                                    className="w-28 text-xs"
                                                />
                                            </div>

                                            <div className="col-span-4">
                                                <FormField
                                                    label="Phone"
                                                    name="phone"
                                                    value={data.phone}
                                                    onChange={(value) => setData('phone', value)}
                                                    error={errors.phone}
                                                    placeholder="Enter phone number"
                                                    className="text-sm"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                                            <div>
                                                <FormField
                                                    label="Email"
                                                    name="email"
                                                    type="email"
                                                    value={data.email}
                                                    onChange={(value) => setData('email', value)}
                                                    error={errors.email}
                                                    placeholder="Enter email address"
                                                    className="text-sm"
                                                />
                                            </div>

                                            <div>
                                                <FormField
                                                    label="Website"
                                                    name="website"
                                                    type="url"
                                                    value={data.website}
                                                    onChange={(value) => setData('website', value)}
                                                    error={errors.website}
                                                    placeholder="Enter website URL"
                                                    className="text-sm"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                                            <div>
                                                <FormSelect
                                                    label="Zone"
                                                    name="zone_id"
                                                    value={data.zone_id}
                                                    onChange={(value) => setData('zone_id', value)}
                                                    options={zones.map((zone) => ({ label: zone.name, value: zone.id.toString() }))}
                                                    error={errors.zone_id}
                                                    placeholder="Select zone..."
                                                    className="text-sm"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 gap-x-4 gap-y-2 sm:grid-cols-2">
                                            <div>
                                                <FormField
                                                    label="Address"
                                                    name="address"
                                                    value={data.address}
                                                    onChange={(value) => setData('address', value)}
                                                    error={errors.address}
                                                    placeholder="Complete address"
                                                    multiline
                                                    rows={2}
                                                    className="text-sm"
                                                />
                                            </div>

                                            <div>
                                                <FormField
                                                    label="Description"
                                                    name="description"
                                                    value={data.description}
                                                    onChange={(value) => setData('description', value)}
                                                    error={errors.description}
                                                    placeholder="Brief description of services provided"
                                                    multiline
                                                    rows={2}
                                                    className="text-sm"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Documents & Bank Details */}
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="flex items-center text-base">Documents & Bank Details</CardTitle>
                                </CardHeader>
                                <CardContent className="pt-2">
                                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                        <div className="space-y-6">
                                            {/* Trade License */}
                                            <div>
                                                <FormField
                                                    label="Trade License No."
                                                    name="trade_license"
                                                    value={data.trade_license}
                                                    onChange={(value) => setData('trade_license', value)}
                                                    error={errors.trade_license}
                                                    placeholder="Trade license number"
                                                    className="text-sm"
                                                />
                                                <div className="mt-2">
                                                    <input
                                                        type="file"
                                                        onChange={(e) => handleFileChange('trade_license_file', e)}
                                                        className="hidden"
                                                        id="trade_license_file"
                                                    />
                                                    <div className="flex items-center space-x-2">
                                                        <label
                                                            htmlFor="trade_license_file"
                                                            className="inline-flex cursor-pointer items-center rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                                                        >
                                                            Choose File
                                                        </label>
                                                        <span className="text-xs text-gray-500 dark:text-gray-400">
                                                            {data.trade_license_file ? data.trade_license_file.name : 'No file chosen'}
                                                        </span>
                                                        {vendor.trade_license_file && (
                                                            <a
                                                                className="text-xs text-blue-500 hover:underline"
                                                                href={route('vendors.document', [vendor.id, 'trade_license_file'])}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                View Current
                                                            </a>
                                                        )}
                                                    </div>
                                                    {errors.trade_license_file && (
                                                        <p className="mt-1 text-xs text-red-500">{errors.trade_license_file}</p>
                                                    )}
                                                </div>
                                            </div>

                                            {/* TIN Number */}
                                            <div>
                                                <FormField
                                                    label="TIN"
                                                    name="tin"
                                                    value={data.tin}
                                                    onChange={(value) => setData('tin', value)}
                                                    error={errors.tin}
                                                    placeholder="TIN number"
                                                    className="text-sm"
                                                />
                                                <div className="mt-2">
                                                    <input
                                                        type="file"
                                                        onChange={(e) => handleFileChange('tin_file', e)}
                                                        className="hidden"
                                                        id="tin_file"
                                                    />
                                                    <div className="flex items-center space-x-2">
                                                        <label
                                                            htmlFor="tin_file"
                                                            className="inline-flex cursor-pointer items-center rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                                                        >
                                                            Choose File
                                                        </label>
                                                        <span className="text-xs text-gray-500 dark:text-gray-400">
                                                            {data.tin_file ? data.tin_file.name : 'No file chosen'}
                                                        </span>
                                                        {vendor.tin_file && (
                                                            <a
                                                                className="text-xs text-blue-500 hover:underline"
                                                                href={route('vendors.document', [vendor.id, 'tin_file'])}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                View Current
                                                            </a>
                                                        )}
                                                    </div>
                                                    {errors.tin_file && <p className="mt-1 text-xs text-red-500">{errors.tin_file}</p>}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="space-y-6">
                                            {/* BIN Number */}
                                            <div>
                                                <FormField
                                                    label="BIN"
                                                    name="bin"
                                                    value={data.bin}
                                                    onChange={(value) => setData('bin', value)}
                                                    error={errors.bin}
                                                    placeholder="BIN number"
                                                    className="text-sm"
                                                />
                                                <div className="mt-2">
                                                    <input
                                                        type="file"
                                                        onChange={(e) => handleFileChange('bin_file', e)}
                                                        className="hidden"
                                                        id="bin_file"
                                                    />
                                                    <div className="flex items-center space-x-2">
                                                        <label
                                                            htmlFor="bin_file"
                                                            className="inline-flex cursor-pointer items-center rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                                                        >
                                                            Choose File
                                                        </label>
                                                        <span className="text-xs text-gray-500 dark:text-gray-400">
                                                            {data.bin_file ? data.bin_file.name : 'No file chosen'}
                                                        </span>
                                                        {vendor.bin_file && (
                                                            <a
                                                                className="text-xs text-blue-500 hover:underline"
                                                                href={route('vendors.document', [vendor.id, 'bin_file'])}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                View Current
                                                            </a>
                                                        )}
                                                    </div>
                                                    {errors.bin_file && <p className="mt-1 text-xs text-red-500">{errors.bin_file}</p>}
                                                </div>
                                            </div>

                                            {/* Tax Return */}
                                            <div>
                                                <FormField
                                                    label="Tax Return"
                                                    name="tax_return"
                                                    value={data.tax_return}
                                                    onChange={(value) => setData('tax_return', value)}
                                                    error={errors.tax_return}
                                                    placeholder="Last tax return number/reference"
                                                    className="text-sm"
                                                />
                                                <div className="mt-2">
                                                    <input
                                                        type="file"
                                                        onChange={(e) => handleFileChange('tax_return_file', e)}
                                                        className="hidden"
                                                        id="tax_return_file"
                                                    />
                                                    <div className="flex items-center space-x-2">
                                                        <label
                                                            htmlFor="tax_return_file"
                                                            className="inline-flex cursor-pointer items-center rounded-md bg-gray-100 px-3 py-1.5 text-xs font-medium hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                                                        >
                                                            Choose File
                                                        </label>
                                                        <span className="text-xs text-gray-500 dark:text-gray-400">
                                                            {data.tax_return_file ? data.tax_return_file.name : 'No file chosen'}
                                                        </span>
                                                        {vendor.tax_return_file && (
                                                            <a
                                                                className="text-xs text-blue-500 hover:underline"
                                                                href={route('vendors.document', [vendor.id, 'tax_return_file'])}
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                View Current
                                                            </a>
                                                        )}
                                                    </div>
                                                    {errors.tax_return_file && <p className="mt-1 text-xs text-red-500">{errors.tax_return_file}</p>}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Bank Details */}
                                    <div className="mt-6">
                                        <FormField
                                            label="Bank Details"
                                            name="bank_details"
                                            value={data.bank_details}
                                            onChange={(value) => setData('bank_details', value)}
                                            error={errors.bank_details}
                                            placeholder="Bank name, branch, account number, etc."
                                            multiline
                                            rows={2}
                                            className="text-sm"
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Contact Persons */}
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between pb-2">
                                    <CardTitle className="flex items-center text-base">
                                        <UserPlus className="mr-2 h-4 w-4" />
                                        Contact Persons
                                    </CardTitle>
                                    <Button type="button" size="sm" variant="outline" onClick={addContactPerson} className="h-7 text-xs">
                                        Add Contact
                                    </Button>
                                </CardHeader>
                                <CardContent className="pt-2">
                                    {data.contact_persons.map((contact, index) => (
                                        <div
                                            key={index}
                                            className={`mb-4 rounded-md border p-4 ${
                                                contact.is_primary
                                                    ? 'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950'
                                                    : 'border-gray-200 dark:border-gray-800'
                                            }`}
                                        >
                                            <div className="mb-2 flex items-center justify-between">
                                                <div className="flex items-center">
                                                    <h3 className="flex items-center text-sm font-medium">
                                                        Contact Person {index + 1}
                                                        {contact.is_primary && (
                                                            <span className="ml-2 rounded bg-blue-100 px-1.5 py-0.5 text-[10px] text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                                Primary
                                                            </span>
                                                        )}
                                                    </h3>
                                                </div>

                                                <div className="flex items-center space-x-2">
                                                    <label className="flex cursor-pointer items-center space-x-1">
                                                        <input
                                                            type="checkbox"
                                                            checked={contact.is_primary}
                                                            onChange={(e) => updateContactPerson(index, 'is_primary', e.target.checked)}
                                                            className="form-checkbox h-3 w-3 text-blue-600"
                                                        />
                                                        <span className="text-xs">Make Primary</span>
                                                    </label>

                                                    {data.contact_persons.length > 1 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => removeContactPerson(index)}
                                                            className="text-red-500 hover:text-red-700"
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </button>
                                                    )}
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                <FormField
                                                    label="Name"
                                                    name={`contact_persons.${index}.name`}
                                                    value={contact.name}
                                                    onChange={(value) => updateContactPerson(index, 'name', value)}
                                                    error={errors[`contact_persons.${index}.name` as keyof typeof errors]}
                                                    required
                                                    className="text-xs"
                                                />

                                                <FormField
                                                    label="Position"
                                                    name={`contact_persons.${index}.position`}
                                                    value={contact.position}
                                                    onChange={(value) => updateContactPerson(index, 'position', value)}
                                                    error={errors[`contact_persons.${index}.position` as keyof typeof errors]}
                                                    className="text-xs"
                                                />
                                            </div>

                                            <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                                <FormField
                                                    label="Phone"
                                                    name={`contact_persons.${index}.phone`}
                                                    value={contact.phone}
                                                    onChange={(value) => updateContactPerson(index, 'phone', value)}
                                                    error={errors[`contact_persons.${index}.phone` as keyof typeof errors]}
                                                    className="text-xs"
                                                />

                                                <FormField
                                                    label="Email"
                                                    name={`contact_persons.${index}.email`}
                                                    type="email"
                                                    value={contact.email}
                                                    onChange={(value) => updateContactPerson(index, 'email', value)}
                                                    error={errors[`contact_persons.${index}.email` as keyof typeof errors]}
                                                    className="text-xs"
                                                />
                                            </div>

                                            <div className="mt-2">
                                                <FormField
                                                    label="Notes"
                                                    name={`contact_persons.${index}.notes`}
                                                    value={contact.notes}
                                                    onChange={(value) => updateContactPerson(index, 'notes', value)}
                                                    error={errors[`contact_persons.${index}.notes` as keyof typeof errors]}
                                                    multiline
                                                    rows={1}
                                                    className="text-xs"
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>

                            <div className="flex items-center justify-end space-x-2">
                                <Button variant="outline" type="button" onClick={() => window.history.back()}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    Update Vendor
                                </Button>
                            </div>
                        </div>
                    </BaseForm>
                </div>
            </div>
        </AppSidebarLayout>
    );
}
