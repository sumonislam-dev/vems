import { BaseForm, FormField, FormFileUpload, FormSelect } from '@/base-components/base-form';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Vendor, Zone } from '@/types';
import { FileText, LayoutGrid, Plus, Trash2, UserPlus } from 'lucide-react';
import { ReactNode } from 'react';

/**
 * The vendor form shared by pages/vendors/create.tsx and edit.tsx; each page
 * keeps only its initial data and submit call.
 */

export type ContactPerson = {
    id?: number;
    name: string;
    position: string;
    phone: string;
    email: string;
    is_primary: boolean;
    notes: string;
};

export type VendorForm = {
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

type DocumentField = 'trade_license_file' | 'tin_file' | 'bin_file' | 'tax_return_file';
type DocumentNumberField = 'trade_license' | 'tin' | 'bin' | 'tax_return';

const DOCUMENTS: { field: DocumentNumberField; file: DocumentField; label: string; placeholder: string }[] = [
    { field: 'trade_license', file: 'trade_license_file', label: 'Trade License No.', placeholder: 'License number' },
    { field: 'tin', file: 'tin_file', label: 'TIN Number', placeholder: 'TIN number' },
    { field: 'bin', file: 'bin_file', label: 'BIN Number', placeholder: 'BIN number' },
    { field: 'tax_return', file: 'tax_return_file', label: 'Tax Return Reference', placeholder: 'Reference number' },
];

const STATUS_OPTIONS = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
];

export const blankContact = (isPrimary = false): ContactPerson => ({
    name: '',
    position: '',
    phone: '',
    email: '',
    is_primary: isPrimary,
    notes: '',
});

function Section({ title, icon, action, children }: { title: string; icon: ReactNode; action?: ReactNode; children: ReactNode }) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between px-4 pt-4 pb-2">
                <CardTitle className="flex items-center gap-2 text-base">
                    {icon}
                    {title}
                </CardTitle>
                {action}
            </CardHeader>
            <CardContent className="px-4 pb-4">{children}</CardContent>
        </Card>
    );
}

interface VendorFormBodyProps {
    data: VendorForm;
    setData: <K extends keyof VendorForm>(field: K, value: VendorForm[K]) => void;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    onSubmit: (e: React.FormEvent) => void;
    zones: Zone[];
    /** The vendor being edited (adds "View current file" links) */
    vendor?: Vendor;
    submitLabel: string;
    submitIcon: ReactNode;
}

export function VendorFormBody({ data, setData, errors, processing, onSubmit, zones, vendor, submitLabel, submitIcon }: VendorFormBodyProps) {
    const contacts = data.contact_persons;

    const updateContact = (index: number, field: keyof ContactPerson, value: string | boolean) =>
        setData(
            'contact_persons',
            contacts.map((contact, i) => {
                if (i === index) return { ...contact, [field]: value };
                // Only one primary contact
                return field === 'is_primary' && value === true ? { ...contact, is_primary: false } : contact;
            }),
        );

    const removeContact = (index: number) => {
        const rest = contacts.filter((_, i) => i !== index);
        // Keep a primary contact if the removed one was it
        if (contacts[index].is_primary && rest.length > 0) rest[0] = { ...rest[0], is_primary: true };
        setData('contact_persons', rest);
    };

    const contactError = (index: number, field: keyof ContactPerson) => errors[`contact_persons.${index}.${field}`];

    return (
        <BaseForm onSubmit={onSubmit}>
            <div className="space-y-4">
                <Section title="Basic Information" icon={<LayoutGrid className="h-4 w-4" />}>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="sm:col-span-2">
                            <FormField
                                label="Service Provider Name"
                                name="name"
                                value={data.name}
                                onChange={(value) => setData('name', value)}
                                error={errors.name}
                                required
                                placeholder="Provider name"
                            />
                        </div>
                        <SearchableSelect
                            label="Zone"
                            name="zone_id"
                            value={data.zone_id}
                            onChange={(value) => setData('zone_id', value === 'none' ? '' : value)}
                            options={zones.map((zone) => ({ label: zone.name, value: zone.id.toString() }))}
                            error={errors.zone_id}
                            placeholder="Search zone..."
                        />
                        <FormSelect
                            label="Status"
                            name="status"
                            value={data.status}
                            onChange={(value) => setData('status', value)}
                            options={STATUS_OPTIONS}
                            error={errors.status}
                            required
                        />

                        <FormField
                            label="Phone"
                            name="phone"
                            value={data.phone}
                            onChange={(value) => setData('phone', value)}
                            error={errors.phone}
                            placeholder="Phone number"
                        />
                        <FormField
                            label="Email"
                            name="email"
                            type="email"
                            value={data.email}
                            onChange={(value) => setData('email', value)}
                            error={errors.email}
                            placeholder="Email address"
                        />
                        <div className="sm:col-span-2">
                            <FormField
                                label="Website"
                                name="website"
                                type="url"
                                value={data.website}
                                onChange={(value) => setData('website', value)}
                                error={errors.website}
                                placeholder="https://"
                            />
                        </div>

                        <div className="sm:col-span-2">
                            <FormField
                                label="Address"
                                name="address"
                                value={data.address}
                                onChange={(value) => setData('address', value)}
                                error={errors.address}
                                placeholder="Complete address"
                                multiline
                                rows={2}
                            />
                        </div>
                        <div className="sm:col-span-2">
                            <FormField
                                label="Description"
                                name="description"
                                value={data.description}
                                onChange={(value) => setData('description', value)}
                                error={errors.description}
                                placeholder="Services provided"
                                multiline
                                rows={2}
                            />
                        </div>
                    </div>
                </Section>

                <Section title="Documents & Bank Details" icon={<FileText className="h-4 w-4" />}>
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {DOCUMENTS.map(({ field, file, label, placeholder }) => (
                            <div key={field} className="space-y-2">
                                <FormField
                                    label={label}
                                    name={field}
                                    value={data[field]}
                                    onChange={(value) => setData(field, value)}
                                    error={errors[field]}
                                    placeholder={placeholder}
                                />
                                <FormFileUpload
                                    label={`${label.replace(/ (No\.|Number|Reference)$/, '')} File`}
                                    name={file}
                                    value={data[file]}
                                    onChange={(value) => setData(file, value)}
                                    error={errors[file]}
                                    accept="image/*,.pdf"
                                    maxSize={2 * 1024 * 1024}
                                />
                                {vendor?.[file] && (
                                    <a
                                        href={route('vendors.document', [vendor.id, file])}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-xs text-blue-600 hover:underline"
                                    >
                                        View current file
                                    </a>
                                )}
                            </div>
                        ))}
                        <div className="sm:col-span-2 lg:col-span-4">
                            <FormField
                                label="Bank Details"
                                name="bank_details"
                                value={data.bank_details}
                                onChange={(value) => setData('bank_details', value)}
                                error={errors.bank_details}
                                placeholder="Account name and number, bank, branch, routing number"
                                multiline
                                rows={2}
                            />
                        </div>
                    </div>
                </Section>

                <Section
                    title="Contact Persons"
                    icon={<UserPlus className="h-4 w-4" />}
                    action={
                        <Button type="button" variant="outline" size="sm" onClick={() => setData('contact_persons', [...contacts, blankContact()])}>
                            <Plus className="mr-1 h-3.5 w-3.5" />
                            Add Person
                        </Button>
                    }
                >
                    <div className="space-y-3">
                        {contacts.map((contact, index) => (
                            <div key={contact.id ?? `new-${index}`} className="rounded-lg border p-3">
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <FormField
                                        label="Name"
                                        name={`contact_persons.${index}.name`}
                                        value={contact.name}
                                        onChange={(value) => updateContact(index, 'name', value)}
                                        error={contactError(index, 'name')}
                                        required
                                        placeholder="Contact person name"
                                    />
                                    <FormField
                                        label="Position"
                                        name={`contact_persons.${index}.position`}
                                        value={contact.position}
                                        onChange={(value) => updateContact(index, 'position', value)}
                                        error={contactError(index, 'position')}
                                        placeholder="Job title"
                                    />
                                    <FormField
                                        label="Phone"
                                        name={`contact_persons.${index}.phone`}
                                        value={contact.phone}
                                        onChange={(value) => updateContact(index, 'phone', value)}
                                        error={contactError(index, 'phone')}
                                        placeholder="Phone number"
                                    />
                                    <FormField
                                        label="Email"
                                        name={`contact_persons.${index}.email`}
                                        type="email"
                                        value={contact.email}
                                        onChange={(value) => updateContact(index, 'email', value)}
                                        error={contactError(index, 'email')}
                                        placeholder="Email address"
                                    />
                                    <div className="sm:col-span-2 lg:col-span-3">
                                        <FormField
                                            label="Notes"
                                            name={`contact_persons.${index}.notes`}
                                            value={contact.notes}
                                            onChange={(value) => updateContact(index, 'notes', value)}
                                            error={contactError(index, 'notes')}
                                            placeholder="Additional notes about this contact"
                                        />
                                    </div>
                                    <div className="flex items-end justify-between gap-2">
                                        <label className="flex h-9 cursor-pointer items-center gap-2 text-sm font-medium">
                                            <input
                                                type="checkbox"
                                                id={`primary_${index}`}
                                                checked={contact.is_primary}
                                                onChange={(e) => updateContact(index, 'is_primary', e.target.checked)}
                                                className="h-4 w-4 rounded border-gray-300 text-indigo-600"
                                            />
                                            Primary contact
                                        </label>
                                        {contacts.length > 1 && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => removeContact(index)}
                                                aria-label={`Remove contact ${index + 1}`}
                                                className="h-9 w-9 text-red-600 hover:text-red-700"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                        {errors.contact_persons && <p className="text-sm text-destructive">{errors.contact_persons}</p>}
                    </div>
                </Section>
            </div>

            <div className="flex gap-2 pt-4">
                <Button type="button" variant="outline" size="sm" onClick={() => window.history.back()} disabled={processing}>
                    Cancel
                </Button>
                <Button type="submit" size="sm" disabled={processing}>
                    {submitIcon}
                    {submitLabel}
                </Button>
            </div>
        </BaseForm>
    );
}
