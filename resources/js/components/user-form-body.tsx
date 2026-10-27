import { BaseForm, FormDatePicker, FormField, FormFileUpload, FormMultiSelect, FormSelect, FormTextarea } from '@/base-components/base-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ZoneAccessSelect } from '@/components/zone-access-select';
import { useCan } from '@/lib/permissions';
import { Car, CheckCircle, Plus, Users } from 'lucide-react';

export type UserForm = {
    name: string;
    username: string;
    employee_id: string;
    designation: string;
    email: string;
    user_type: string;
    department_id: string;
    zone_access: 'all' | 'specific';
    zone_ids: string[];
    vendor_id: string;
    roles: string[];
    official_phone: string;
    personal_phone: string;
    whatsapp_id: string;
    emergency_phone: string;
    emergency_contact_name: string;
    emergency_contact_relation: string;
    present_address: string;
    permanent_address: string;
    area: string;
    blood_group: string;
    nid_number: string;
    nid_file: File | null;
    passport_number: string;
    driving_license_no: string;
    driving_license_file: File | null;
    license_class: string;
    license_issue_date: string;
    license_expiry_date: string;
    joining_date: string;
    status: string;
    driver_status: string;
    password: string;
    password_confirmation: string;
    image: File | null;
    photo: File | null;
};

interface UserFormBodyProps {
    data: UserForm;
    processing: boolean;
    formCompletion: number;
    showDriverFields: boolean;
    title: string;
    submitLabel: string;
    getFieldError: (field: keyof UserForm) => string | undefined;
    handleFieldChange: (field: keyof UserForm, value: string | File | null) => void;
    handleUserTypeChange: (value: string) => void;
    setData: (field: keyof UserForm, value: string | string[] | File | null) => void;
    onSubmit: () => void;
    onReset: () => void;
    roles: Array<{ id: number; name: string }>;
    userTypes: Array<{ value: string; label: string }>;
    vendorOptions?: Array<{ label: string; value: string }>;
    hideUserTypeAndRoles?: boolean;
    hideUserType?: boolean;
    licenseClasses: Array<{ value: string; label: string }>;
    bloodGroups: Array<{ value: string; label: string }>;
    departmentOptions: Array<{ label: string; value: string }>;
    zoneOptions: Array<{ label: string; value: string }>;
    statusOptions: Array<{ label: string; value: string }>;
    driverStatusOptions: Array<{ label: string; value: string }>;
}

export function UserFormBody({
    data,
    processing,
    formCompletion,
    showDriverFields,
    title,
    submitLabel,
    getFieldError,
    handleFieldChange,
    handleUserTypeChange,
    setData,
    roles,
    userTypes,
    vendorOptions = [],
    hideUserTypeAndRoles = false,
    hideUserType = false,
    licenseClasses,
    bloodGroups,
    departmentOptions,
    zoneOptions,
    statusOptions,
    driverStatusOptions,
    onSubmit,
    onReset,
}: UserFormBodyProps) {
    // Roles can only be chosen with manage-user-roles; the server ignores them otherwise
    const canManageRoles = useCan()('manage-user-roles');

    const handleSubmit = () => {
        onSubmit();
    };

    return (
        <BaseForm onSubmit={handleSubmit} processing={processing}>
            {/* Basic Information */}
            <Card className="shadow-sm">
                <CardHeader className="px-4 pb-3 sm:px-6">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <div className="rounded-md bg-primary/10 p-1.5">
                                <Users className="h-4 w-4 text-primary" />
                            </div>
                            <div>
                                <CardTitle className="text-lg">{title}</CardTitle>
                                <CardDescription className="text-sm">Complete form progress: {formCompletion}%</CardDescription>
                            </div>
                        </div>
                        {/* Compact Progress */}
                        <div className="flex items-center gap-2">
                            {formCompletion === 100 && <CheckCircle className="h-4 w-4 text-green-500" />}
                            <div className="h-1.5 w-12 rounded-full bg-muted">
                                <div
                                    className={`h-1.5 rounded-full transition-all duration-300 ${formCompletion === 100 ? 'bg-green-500' : 'bg-primary'}`}
                                    style={{ width: `${formCompletion}%` }}
                                />
                            </div>
                        </div>
                    </div>
                </CardHeader>

                <CardContent className="space-y-4 px-4 sm:px-6">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 lg:gap-5">
                        <FormField
                            label="Full Name"
                            name="name"
                            value={data.name}
                            onChange={(value) => handleFieldChange('name', value)}
                            error={getFieldError('name')}
                            placeholder="Enter full name"
                            required
                            autoFocus
                        />

                        <FormField
                            label="Username"
                            name="username"
                            value={data.username}
                            onChange={(value) => handleFieldChange('username', value)}
                            error={getFieldError('username')}
                            placeholder="Enter username"
                            required
                        />

                        <FormField
                            label="Employee ID"
                            name="employee_id"
                            value={data.employee_id}
                            onChange={(value) => handleFieldChange('employee_id', value)}
                            error={getFieldError('employee_id')}
                            placeholder="Enter employee ID"
                        />
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 lg:gap-5">
                        <FormField
                            label="Email Address"
                            name="email"
                            type="email"
                            value={data.email}
                            onChange={(value) => handleFieldChange('email', value)}
                            error={getFieldError('email')}
                            placeholder="Enter email address"
                        />

                        <FormField
                            label="Designation"
                            name="designation"
                            value={data.designation}
                            onChange={(value) => handleFieldChange('designation', value)}
                            error={getFieldError('designation')}
                            placeholder="e.g. Senior Engineer"
                        />

                        {!hideUserTypeAndRoles && !hideUserType && (
                            <FormSelect
                                label="User Type"
                                name="user_type"
                                value={data.user_type}
                                onChange={handleUserTypeChange}
                                error={getFieldError('user_type')}
                                options={userTypes}
                                required
                            />
                        )}

                        <FormSelect
                            label="Department"
                            name="department_id"
                            value={data.department_id}
                            onChange={(value) => handleFieldChange('department_id', value)}
                            error={getFieldError('department_id')}
                            options={departmentOptions}
                            placeholder="Select department..."
                        />

                        <ZoneAccessSelect
                            zoneAccess={data.zone_access}
                            zoneIds={data.zone_ids}
                            onChange={(zoneAccess, zoneIds) => {
                                setData('zone_access', zoneAccess);
                                setData('zone_ids', zoneIds);
                            }}
                            zoneOptions={zoneOptions}
                            error={getFieldError('zone_ids')}
                        />

                        {!hideUserTypeAndRoles && canManageRoles && (
                            <FormMultiSelect
                                label="Roles"
                                name="roles"
                                value={data.roles}
                                onChange={(values) => setData('roles', values)}
                                error={getFieldError('roles')}
                                options={roles.map((role) => ({ value: role.id.toString(), label: role.name }))}
                                placeholder="Select roles..."
                                required
                                searchable
                            />
                        )}
                    </div>
                </CardContent>
            </Card>

            {/* Driver Information (conditional) - placed right after Basic Info since it's the required, driver-specific data */}
            {showDriverFields && (
                <Card className="shadow-sm">
                    <CardHeader className="px-4 pb-3 sm:px-6">
                        <div className="flex items-center gap-2">
                            <div className="rounded-md bg-green-100 p-1.5">
                                <Car className="h-4 w-4 text-green-600" />
                            </div>
                            <div>
                                <CardTitle className="text-lg">Driver Information</CardTitle>
                                <CardDescription className="text-sm">License details and driver-specific information</CardDescription>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="space-y-4 px-4 sm:px-6">
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4 lg:gap-5">
                            <div className="sm:col-span-2">
                                <FormSelect
                                    label="Vendor"
                                    name="vendor_id"
                                    value={data.vendor_id}
                                    onChange={(value) => handleFieldChange('vendor_id', value)}
                                    error={getFieldError('vendor_id')}
                                    options={vendorOptions}
                                    placeholder="Select vendor..."
                                    required={showDriverFields}
                                />
                            </div>

                            <FormSelect
                                label="Driver Status"
                                name="driver_status"
                                value={data.driver_status}
                                onChange={(value) => handleFieldChange('driver_status', value)}
                                error={getFieldError('driver_status')}
                                options={driverStatusOptions}
                            />

                            <FormSelect
                                label="License Class"
                                name="license_class"
                                value={data.license_class}
                                onChange={(value) => handleFieldChange('license_class', value)}
                                error={getFieldError('license_class')}
                                options={licenseClasses}
                                placeholder="Select license class..."
                                required={showDriverFields}
                            />
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4 lg:gap-5">
                            <FormField
                                label="Driving License Number"
                                name="driving_license_no"
                                value={data.driving_license_no}
                                onChange={(value) => handleFieldChange('driving_license_no', value)}
                                error={getFieldError('driving_license_no')}
                                placeholder="Enter license number"
                                required={showDriverFields}
                            />

                            <FormDatePicker
                                label="License Issue Date"
                                name="license_issue_date"
                                value={data.license_issue_date}
                                onChange={(value) => handleFieldChange('license_issue_date', value)}
                                error={getFieldError('license_issue_date')}
                            />

                            <FormDatePicker
                                label="License Expiry Date"
                                name="license_expiry_date"
                                value={data.license_expiry_date}
                                onChange={(value) => handleFieldChange('license_expiry_date', value)}
                                error={getFieldError('license_expiry_date')}
                                required={showDriverFields}
                            />

                            <FormFileUpload
                                label="License Scan"
                                name="driving_license_file"
                                value={data.driving_license_file}
                                onChange={(file) => handleFieldChange('driving_license_file', file)}
                                error={getFieldError('driving_license_file')}
                                accept="image/*,.pdf"
                                maxSize={2 * 1024 * 1024}
                            />
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Additional Details */}
            <Card className="shadow-sm">
                <CardHeader className="px-4 pb-3 sm:px-6">
                    <CardTitle className="text-lg">Additional Details</CardTitle>
                    <CardDescription className="text-sm">Contact, identity, and personal information</CardDescription>
                </CardHeader>

                <CardContent className="space-y-4 px-4 sm:px-6">
                    {/* Contact Information */}
                    <div className="space-y-2">
                        <h4 className="text-sm font-medium text-muted-foreground">Contact Information</h4>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 lg:gap-5">
                            <FormField
                                label="Official Phone"
                                name="official_phone"
                                value={data.official_phone}
                                onChange={(value) => handleFieldChange('official_phone', value)}
                                error={getFieldError('official_phone')}
                                placeholder="+880 1711 000000"
                            />

                            <FormField
                                label="Personal Phone"
                                name="personal_phone"
                                value={data.personal_phone}
                                onChange={(value) => handleFieldChange('personal_phone', value)}
                                error={getFieldError('personal_phone')}
                                placeholder="+880 1711 000000"
                            />

                            <FormField
                                label="WhatsApp ID"
                                name="whatsapp_id"
                                value={data.whatsapp_id}
                                onChange={(value) => handleFieldChange('whatsapp_id', value)}
                                error={getFieldError('whatsapp_id')}
                                placeholder="+880 1711 000000"
                            />
                        </div>
                    </div>

                    {/* Identity Documents */}
                    <div className="space-y-2">
                        <h4 className="text-sm font-medium text-muted-foreground">Identity Documents</h4>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 lg:gap-5">
                            <FormField
                                label="National ID Number"
                                name="nid_number"
                                value={data.nid_number}
                                onChange={(value) => handleFieldChange('nid_number', value)}
                                error={getFieldError('nid_number')}
                                placeholder="Enter National ID number"
                            />

                            <FormFileUpload
                                label="NID Scan"
                                name="nid_file"
                                value={data.nid_file}
                                onChange={(file) => handleFieldChange('nid_file', file)}
                                error={getFieldError('nid_file')}
                                accept="image/*,.pdf"
                                maxSize={2 * 1024 * 1024}
                            />

                            <FormField
                                label="Passport Number"
                                name="passport_number"
                                value={data.passport_number}
                                onChange={(value) => handleFieldChange('passport_number', value)}
                                error={getFieldError('passport_number')}
                                placeholder="Enter passport number"
                            />
                        </div>
                    </div>

                    {/* Personal Information */}
                    <div className="space-y-2">
                        <h4 className="text-sm font-medium text-muted-foreground">Personal Information</h4>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 lg:gap-5">
                            <FormSelect
                                label="Blood Group"
                                name="blood_group"
                                value={data.blood_group}
                                onChange={(value) => handleFieldChange('blood_group', value)}
                                error={getFieldError('blood_group')}
                                options={bloodGroups}
                                placeholder="Select blood group..."
                            />

                            <FormDatePicker
                                label="Joining Date"
                                name="joining_date"
                                value={data.joining_date}
                                onChange={(value) => handleFieldChange('joining_date', value)}
                                error={getFieldError('joining_date')}
                            />

                            <FormSelect
                                label="Status"
                                name="status"
                                value={data.status}
                                onChange={(value) => handleFieldChange('status', value)}
                                error={getFieldError('status')}
                                options={statusOptions}
                                required
                            />
                        </div>
                    </div>

                    {/* Emergency Contact */}
                    <div className="space-y-2">
                        <h4 className="text-sm font-medium text-muted-foreground">Emergency Contact</h4>
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 md:grid-cols-3 lg:gap-5">
                            <FormField
                                label="Contact Name"
                                name="emergency_contact_name"
                                value={data.emergency_contact_name}
                                onChange={(value) => handleFieldChange('emergency_contact_name', value)}
                                error={getFieldError('emergency_contact_name')}
                                placeholder="Enter emergency contact name"
                            />

                            <FormField
                                label="Relation"
                                name="emergency_contact_relation"
                                value={data.emergency_contact_relation}
                                onChange={(value) => handleFieldChange('emergency_contact_relation', value)}
                                error={getFieldError('emergency_contact_relation')}
                                placeholder="e.g., Spouse, Parent"
                            />

                            <FormField
                                label="Phone Number"
                                name="emergency_phone"
                                value={data.emergency_phone}
                                onChange={(value) => handleFieldChange('emergency_phone', value)}
                                error={getFieldError('emergency_phone')}
                                placeholder="+880 1711 000000"
                            />
                        </div>
                    </div>

                    {/* Addresses - Compact Layout */}
                    <div className="space-y-2">
                        <h4 className="text-sm font-medium text-muted-foreground">Address Information</h4>
                        <div className="grid grid-cols-1 gap-3 sm:gap-4 md:grid-cols-3 lg:gap-5">
                            <FormField
                                label="Area/Location"
                                name="area"
                                value={data.area}
                                onChange={(value) => handleFieldChange('area', value)}
                                error={getFieldError('area')}
                                placeholder="e.g., Gulshan, Mohakhali, Banani"
                            />

                            <FormTextarea
                                label="Present Address"
                                name="present_address"
                                value={data.present_address}
                                onChange={(value) => handleFieldChange('present_address', value)}
                                error={getFieldError('present_address')}
                                placeholder="Enter current address"
                                rows={2}
                            />

                            <FormTextarea
                                label="Permanent Address"
                                name="permanent_address"
                                value={data.permanent_address}
                                onChange={(value) => handleFieldChange('permanent_address', value)}
                                error={getFieldError('permanent_address')}
                                placeholder="Enter permanent address"
                                rows={2}
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* File Uploads & Security - Compact */}
            <Card className="shadow-sm">
                <CardHeader className="px-4 pb-3 sm:px-6">
                    <CardTitle className="text-lg">Files & Security</CardTitle>
                    <CardDescription className="text-sm">Upload images and set account password</CardDescription>
                </CardHeader>

                <CardContent className="space-y-4 px-4 sm:px-6">
                    {/* File Uploads - Compact */}
                    <div className="grid grid-cols-1 gap-3 sm:gap-4 md:grid-cols-2 lg:gap-5">
                        <FormFileUpload
                            label="Profile Image"
                            name="image"
                            onChange={(file) => handleFieldChange('image', file)}
                            error={getFieldError('image')}
                            accept="image/*"
                            maxSize={2 * 1024 * 1024}
                        />

                        <FormFileUpload
                            label="ID Photo"
                            name="photo"
                            onChange={(file) => handleFieldChange('photo', file)}
                            error={getFieldError('photo')}
                            accept="image/*"
                            maxSize={2 * 1024 * 1024}
                        />
                    </div>

                    {/* Password Section - Compact */}
                    <div className="border-t pt-4">
                        <div className="grid grid-cols-1 gap-3 sm:gap-4 md:grid-cols-2 lg:gap-5">
                            <FormField
                                label="Password"
                                name="password"
                                type="password"
                                value={data.password}
                                onChange={(value) => handleFieldChange('password', value)}
                                error={getFieldError('password')}
                                placeholder="Enter password"
                                required
                            />

                            <FormField
                                label="Confirm Password"
                                name="password_confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(value) => handleFieldChange('password_confirmation', value)}
                                error={getFieldError('password_confirmation')}
                                placeholder="Confirm your password"
                                required
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Compact Form Actions */}
            <div className="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" size="sm" onClick={onReset} disabled={processing}>
                    Reset
                </Button>
                <Button type="button" variant="outline" size="sm" onClick={() => window.history.back()} disabled={processing}>
                    Cancel
                </Button>
                <Button type="submit" size="sm" disabled={formCompletion < 100 || processing}>
                    <Plus className="h-4 w-4" />
                    {submitLabel}
                </Button>
            </div>
        </BaseForm>
    );
}
