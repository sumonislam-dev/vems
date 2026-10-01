import { BaseForm, FormField, FormSelect } from '@/base-components/base-form';
import { PageHeader } from '@/base-components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, Zone } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2, UserPlus, LayoutGrid } from 'lucide-react';

type ContactPerson = {
    name: string;
    position: string;
    phone: string;
    email: string;
    is_primary: boolean;
    notes: string;
};

type VendorForm = {
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
        contact_persons: [
            {
                name: '',
                position: '',
                phone: '',
                email: '',
                is_primary: true,
                notes: '',
            },
        ],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        post(route('vendors.store'), {
            forceFormData: true,
            preserveScroll: false,
            preserveState: false,
            onError: () => {
                // Inertia binds server errors to form.errors automatically
            },
            onSuccess: () => {
                // redirect handled by controller
            }
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
                name: '',
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
            <Head title="Create Vendor" />

            <div className="space-y-6">
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
                    className="pb-4"
                />

                <div className="max-w-4xl">
                    <BaseForm onSubmit={submit}>
                        <div className="space-y-6">
                            {/* Basic Information */}
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-base flex items-center">
                                        <LayoutGrid className="h-4 w-4 mr-2" />
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
                                                    className="text-xs w-28"
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

                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
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

                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
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

                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
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
                                    <CardTitle className="text-base flex items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 mr-2"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        Documents & Bank Details
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="pt-3">
                                    <div className="grid gap-3">
                                        {/* Document Tabs - Compact Design */}
                                        <div className="border rounded-md overflow-hidden">
                                            {/* Trade License */}
                                            <div className="border-b px-3 py-2.5">
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <div className="flex-1 min-w-[180px]">
                                                        <FormField
                                                            label="Trade License No."
                                                            name="trade_license"
                                                            value={data.trade_license}
                                                            onChange={(value) => setData('trade_license', value)}
                                                            error={errors.trade_license}
                                                            placeholder="Enter license number"
                                                            className="text-sm"
                                                        />
                                                    </div>
                                                    <div className="flex-1 min-w-[220px]">
                                                        <label htmlFor="trade_license_file" className="block text-xs font-medium mb-1">
                                                            Upload Document
                                                        </label>
                                                        <input
                                                            id="trade_license_file"
                                                            name="trade_license_file"
                                                            type="file"
                                                            accept=".pdf,.jpg,.jpeg,.png"
                                                            className="block w-full text-xs border border-input rounded-md file:mr-2 file:py-1 file:px-2 file:border-0 file:text-xs file:font-medium file:bg-secondary file:text-secondary-foreground hover:file:bg-secondary/80"
                                                            onChange={(e) => handleFileChange('trade_license_file', e)}
                                                        />
                                                        {errors.trade_license_file && (
                                                            <p className="text-destructive text-xs mt-1">{errors.trade_license_file}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* TIN */}
                                            <div className="border-b px-3 py-2.5">
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <div className="flex-1 min-w-[180px]">
                                                        <FormField
                                                            label="TIN Number"
                                                            name="tin"
                                                            value={data.tin}
                                                            onChange={(value) => setData('tin', value)}
                                                            error={errors.tin}
                                                            placeholder="Enter TIN number"
                                                            className="text-sm"
                                                        />
                                                    </div>
                                                    <div className="flex-1 min-w-[220px]">
                                                        <label htmlFor="tin_file" className="block text-xs font-medium mb-1">
                                                            Upload Document
                                                        </label>
                                                        <input
                                                            id="tin_file"
                                                            name="tin_file"
                                                            type="file"
                                                            accept=".pdf,.jpg,.jpeg,.png"
                                                            className="block w-full text-xs border border-input rounded-md file:mr-2 file:py-1 file:px-2 file:border-0 file:text-xs file:font-medium file:bg-secondary file:text-secondary-foreground hover:file:bg-secondary/80"
                                                            onChange={(e) => handleFileChange('tin_file', e)}
                                                        />
                                                        {errors.tin_file && (
                                                            <p className="text-destructive text-xs mt-1">{errors.tin_file}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* BIN */}
                                            <div className="border-b px-3 py-2.5">
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <div className="flex-1 min-w-[180px]">
                                                        <FormField
                                                            label="BIN Number"
                                                            name="bin"
                                                            value={data.bin}
                                                            onChange={(value) => setData('bin', value)}
                                                            error={errors.bin}
                                                            placeholder="Enter BIN number"
                                                            className="text-sm"
                                                        />
                                                    </div>
                                                    <div className="flex-1 min-w-[220px]">
                                                        <label htmlFor="bin_file" className="block text-xs font-medium mb-1">
                                                            Upload Document
                                                        </label>
                                                        <input
                                                            id="bin_file"
                                                            name="bin_file"
                                                            type="file"
                                                            accept=".pdf,.jpg,.jpeg,.png"
                                                            className="block w-full text-xs border border-input rounded-md file:mr-2 file:py-1 file:px-2 file:border-0 file:text-xs file:font-medium file:bg-secondary file:text-secondary-foreground hover:file:bg-secondary/80"
                                                            onChange={(e) => handleFileChange('bin_file', e)}
                                                        />
                                                        {errors.bin_file && (
                                                            <p className="text-destructive text-xs mt-1">{errors.bin_file}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Tax Return */}
                                            <div className="px-3 py-2.5">
                                                <div className="flex flex-wrap items-center gap-3">
                                                    <div className="flex-1 min-w-[180px]">
                                                        <FormField
                                                            label="Tax Return Reference"
                                                            name="tax_return"
                                                            value={data.tax_return}
                                                            onChange={(value) => setData('tax_return', value)}
                                                            error={errors.tax_return}
                                                            placeholder="Enter reference number"
                                                            className="text-sm"
                                                        />
                                                    </div>
                                                    <div className="flex-1 min-w-[220px]">
                                                        <label htmlFor="tax_return_file" className="block text-xs font-medium mb-1">
                                                            Upload Document
                                                        </label>
                                                        <input
                                                            id="tax_return_file"
                                                            name="tax_return_file"
                                                            type="file"
                                                            accept=".pdf,.jpg,.jpeg,.png"
                                                            className="block w-full text-xs border border-input rounded-md file:mr-2 file:py-1 file:px-2 file:border-0 file:text-xs file:font-medium file:bg-secondary file:text-secondary-foreground hover:file:bg-secondary/80"
                                                            onChange={(e) => handleFileChange('tax_return_file', e)}
                                                        />
                                                        {errors.tax_return_file && (
                                                            <p className="text-destructive text-xs mt-1">{errors.tax_return_file}</p>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Bank Details */}
                                        <div>
                                            <FormField
                                                label="Bank Details"
                                                name="bank_details"
                                                value={data.bank_details}
                                                onChange={(value) => setData('bank_details', value)}
                                                error={errors.bank_details}
                                                placeholder="Enter bank account details, branch information, etc."
                                                multiline
                                                rows={2}
                                                className="text-sm"
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Contact Persons */}
                            <Card>
                                <CardHeader className="pb-2 flex flex-row items-center justify-between">
                                    <CardTitle className="text-base flex items-center">
                                        <UserPlus className="h-4 w-4 mr-2" />
                                        Contact Persons (Min. 1 Required)
                                    </CardTitle>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="h-8 text-xs"
                                        onClick={addContactPerson}
                                    >
                                        <Plus className="h-3 w-3 mr-1" />
                                        Add Person
                                    </Button>
                                </CardHeader>
                                <CardContent className="pt-3">
                                    <div className="space-y-4">
                                        {data.contact_persons.map((contact, index) => (
                                            <div key={index} className="border rounded-lg p-3">
                                                <div className="flex items-center justify-between mb-3">
                                                    <h4 className="text-sm font-medium flex items-center">
                                                        Contact Person {index + 1}
                                                        {contact.is_primary && (
                                                            <span className="ml-2 text-xs bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded-sm">
                                                                Primary
                                                            </span>
                                                        )}
                                                    </h4>
                                                    {data.contact_persons.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => removeContactPerson(index)}
                                                            className="text-red-600 hover:text-red-700 h-7 w-7 p-0"
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </Button>
                                                    )}
                                                </div>

                                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                    <FormField
                                                        label="Name"
                                                        name={`contact_persons.${index}.name`}
                                                        value={contact.name}
                                                        onChange={(value) => updateContactPerson(index, 'name', value)}
                                                        error={errors[`contact_persons.${index}.name` as keyof typeof errors]}
                                                        required
                                                        placeholder="Contact person name"
                                                        className="text-sm"
                                                    />

                                                    <FormField
                                                        label="Position"
                                                        name={`contact_persons.${index}.position`}
                                                        value={contact.position}
                                                        onChange={(value) => updateContactPerson(index, 'position', value)}
                                                        error={errors[`contact_persons.${index}.position` as keyof typeof errors]}
                                                        placeholder="Job title/position"
                                                        className="text-sm"
                                                    />

                                                    <FormField
                                                        label="Phone"
                                                        name={`contact_persons.${index}.phone`}
                                                        value={contact.phone}
                                                        onChange={(value) => updateContactPerson(index, 'phone', value)}
                                                        error={errors[`contact_persons.${index}.phone` as keyof typeof errors]}
                                                        placeholder="Phone number"
                                                        className="text-sm"
                                                    />

                                                    <FormField
                                                        label="Email"
                                                        name={`contact_persons.${index}.email`}
                                                        type="email"
                                                        value={contact.email}
                                                        onChange={(value) => updateContactPerson(index, 'email', value)}
                                                        error={errors[`contact_persons.${index}.email` as keyof typeof errors]}
                                                        placeholder="Email address"
                                                        className="text-sm"
                                                    />

                                                    <div className="flex items-center space-x-2">
                                                        <input
                                                            type="checkbox"
                                                            id={`primary_${index}`}
                                                            checked={contact.is_primary}
                                                            onChange={(e) => updateContactPerson(index, 'is_primary', e.target.checked)}
                                                            className="h-3.5 w-3.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                        />
                                                        <label htmlFor={`primary_${index}`} className="text-xs font-medium text-gray-700">
                                                            Primary Contact
                                                        </label>
                                                    </div>

                                                    <div className="sm:col-span-2">
                                                        <FormField
                                                            label="Notes"
                                                            name={`contact_persons.${index}.notes`}
                                                            value={contact.notes}
                                                            onChange={(value) => updateContactPerson(index, 'notes', value)}
                                                            error={errors[`contact_persons.${index}.notes` as keyof typeof errors]}
                                                            placeholder="Additional notes about this contact"
                                                            multiline
                                                            rows={1}
                                                            className="text-sm"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <div className="flex gap-2 pt-4">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => window.history.back()}
                                disabled={processing}
                                size="sm"
                                className="h-8 text-xs"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                size="sm"
                                className="h-8 text-xs"
                            >
                                <Plus className="h-3 w-3 mr-1" />
                                Create Vendor
                            </Button>
                        </div>
                    </BaseForm>
                </div>
            </div>
        </AppSidebarLayout>
    );
}
