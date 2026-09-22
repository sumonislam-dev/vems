import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, Save, Users, CheckCircle, Car, User, Shield } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/base-components/page-header';
import {
  BaseForm,
  FormField,
  FormSelect,
  FormTextarea,
  FormFileUpload,
  FormDatePicker
} from '@/base-components/base-form';

/**
 * Edit User Page
 *
 * Features:
 * - Comprehensive form validation
 * - User type specific fields
 * - Driver license validation
 * - File upload for images and documents
 * - Progress tracking
 * - Responsive design
 * - Pre-filled with existing user data
 */

type UserForm = {
  name: string;
  username: string;
  employee_id: string;
  email: string;
  user_type: string;
  department_id: string;
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

interface EditUserProps {
  user: Record<string, unknown>; // User data from the backend
  departments: Array<{ id: number; name: string }>;
  vendors: Array<{ id: number; name: string }>;
  roles: Array<{ id: number; name: string }>;
  userRoles: string[]; // Current user roles
  userTypes: Array<{ value: string; label: string }>;
  licenseClasses: Array<{ value: string; label: string }>;
  bloodGroups: Array<{ value: string; label: string }>;
}

const toDateInputValue = (value: unknown): string => {
  if (!value) {
    return '';
  }

  const raw = String(value);
  return raw.includes('T') ? raw.split('T')[0] : raw;
};

// Enhanced user type options with icons and descriptions
const enhancedUserTypeOptions = [
  {
    label: "Employee",
    value: "employee",
    icon: <User className="h-4 w-4 text-blue-600" />,
    description: "Regular organization staff who can request vehicles"
  },
  {
    label: "Driver",
    value: "driver",
    icon: <Car className="h-4 w-4 text-green-600" />,
    description: "Can drive vehicles and handle trip operations"
  },
  {
    label: "Transport Manager",
    value: "transport_manager",
    icon: <Shield className="h-4 w-4 text-purple-600" />,
    description: "Manages transport operations and approves trips"
  },
  {
    label: "Administrator",
    value: "admin",
    icon: <Shield className="h-4 w-4 text-red-600" />,
    description: "Full system access and user management"
  }
];

export default function EditUser({ user, vendors, userRoles, licenseClasses, bloodGroups }: EditUserProps) {
  const { data, setData, post, transform, processing, errors, reset } = useForm<UserForm>({
    name: (user.name as string) || '',
    username: (user.username as string) || '',
    employee_id: (user.employee_id as string) || '',
    email: (user.email as string) || '',
    user_type: 'driver',
    department_id: (user.department_id as number)?.toString() || '',
    vendor_id: (user.vendor_id as number)?.toString() || '',
    roles: userRoles || [],
    official_phone: (user.official_phone as string) || '',
    personal_phone: (user.personal_phone as string) || '',
    whatsapp_id: (user.whatsapp_id as string) || '',
    emergency_phone: (user.emergency_phone as string) || '',
    emergency_contact_name: (user.emergency_contact_name as string) || '',
    emergency_contact_relation: (user.emergency_contact_relation as string) || '',
    present_address: (user.present_address as string) || '',
    permanent_address: (user.permanent_address as string) || '',
    area: (user.area as string) || '',
    blood_group: (user.blood_group as string) || '',
    nid_number: (user.nid_number as string) || '',
    nid_file: null,
    passport_number: (user.passport_number as string) || '',
    driving_license_no: (user.driving_license_no as string) || '',
    driving_license_file: null,
    license_class: (user.license_class as string) || '',
    license_issue_date: toDateInputValue(user.license_issue_date),
    license_expiry_date: toDateInputValue(user.license_expiry_date),
    joining_date: toDateInputValue(user.joining_date),
    status: (user.status as string) || 'active',
    driver_status: (user.driver_status as string) || 'available',
    password: '',
    password_confirmation: '',
    image: null,
    photo: null,
  });

  // Show driver fields if user type is driver
  const showDriverFields = true;

  const vendorOptions = vendors.map(vendor => ({
    label: vendor.name,
    value: vendor.id.toString()
  }));

  // Status options
  const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
    { label: 'Suspended', value: 'suspended' }
  ];

  // Driver status options
  const driverStatusOptions = [
    { label: 'Available', value: 'available' },
    { label: 'On Trip', value: 'on_trip' },
    { label: 'Off Duty', value: 'off_duty' },
    { label: 'Maintenance', value: 'maintenance' }
  ];

  // Handle field changes
  const handleFieldChange = (field: keyof UserForm, value: string | File | null) => {
    setData(field, value);
  };

  // Form submission
  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    // PHP does not parse multipart/form-data bodies on PUT requests, so a
    // real PUT with file uploads silently arrives with an empty body.
    // Spoofing the method via POST + _method is the standard workaround.
    transform((formData) => ({ ...formData, _method: 'put' }));
    post(route('drivers.update', user.id as number));
  };

  // Calculate form completion (for existing user, assume basic fields are filled)
  const requiredFields = ['name', 'username', 'email', 'user_type'];
  const filledFields = requiredFields.filter(field => data[field as keyof UserForm]).length;
  const formCompletion = Math.round((filledFields / requiredFields.length) * 100);

  return (
    <AppLayout>
      <Head title={`Edit User - ${user.name as string}`} />

      <div className="container mx-auto px-4 py-8 space-y-6">
        <PageHeader
          title={`Edit User - ${user.name as string}`}
          description="Update user information and settings"
          actions={[
            {
              label: "Back to Users",
              icon: <ArrowLeft className="h-4 w-4" />,
              href: route('drivers.index'),
              variant: "outline"
            }
          ]}
        />

        <div className="max-w-4xl xl:max-w-5xl">
          <BaseForm onSubmit={submit} processing={processing}>
            {/* Compact Main Form Card */}
            <Card className="shadow-sm">
              <CardHeader className="pb-3 px-4 sm:px-6">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div className="p-1.5 bg-primary/10 rounded-md">
                      <Users className="h-4 w-4 text-primary" />
                    </div>
                    <div>
                      <CardTitle className="text-lg">Edit User</CardTitle>
                      <CardDescription className="text-sm">Update user information and settings</CardDescription>
                    </div>
                  </div>
                  {/* Compact Progress */}
                  <div className="flex items-center gap-2">
                    {formCompletion === 100 && <CheckCircle className="h-4 w-4 text-green-500" />}
                    <div className="w-12 bg-muted rounded-full h-1.5">
                      <div
                        className={`h-1.5 rounded-full transition-all duration-300 ${
                          formCompletion === 100 ? 'bg-green-500' : 'bg-primary'
                        }`}
                        style={{ width: `${formCompletion}%` }}
                      />
                    </div>
                  </div>
                </div>
              </CardHeader>

              <CardContent className="space-y-5 px-4 sm:px-6">
                {/* Compact 3-Column Grid */}
                <div className="grid gap-3 sm:gap-4 md:gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3">
                  {/* Basic Information */}
                  <FormField
                    label="Full Name"
                    name="name"
                    value={data.name}
                    onChange={(value) => handleFieldChange('name', value)}
                    error={errors.name}
                    placeholder="Enter full name"
                    required
                    autoFocus
                  />

                  <FormField
                    label="Username"
                    name="username"
                    value={data.username}
                    onChange={(value) => handleFieldChange('username', value)}
                    error={errors.username}
                    placeholder="Enter username"
                    required
                  />

                  <FormField
                    label="Employee ID"
                    name="employee_id"
                    value={data.employee_id}
                    onChange={(value) => handleFieldChange('employee_id', value)}
                    error={errors.employee_id}
                    placeholder="Enter employee ID"
                  />
                </div>

                <div className="grid gap-3 sm:gap-4 md:gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3">
                  <FormField
                    label="Email Address"
                    name="email"
                    type="email"
                    value={data.email}
                    onChange={(value) => handleFieldChange('email', value)}
                    error={errors.email}
                    placeholder="Enter email address"
                  />

                  {/* User Type, Department, and Roles are fixed for drivers - Hidden */}
                </div>

                {/* Contact Information */}
                <div className="space-y-2">
                  <h4 className="text-sm font-medium text-muted-foreground">Contact Information</h4>
                  <div className="grid gap-3 sm:gap-4 md:gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3">
                    <FormField
                      label="Official Phone"
                      name="official_phone"
                      value={data.official_phone}
                      onChange={(value) => handleFieldChange('official_phone', value)}
                      error={errors.official_phone}
                      placeholder="+880 1711 000000"
                    />

                    <FormField
                      label="Personal Phone"
                      name="personal_phone"
                      value={data.personal_phone}
                      onChange={(value) => handleFieldChange('personal_phone', value)}
                      error={errors.personal_phone}
                      placeholder="+880 1711 000000"
                      required
                    />

                    <FormField
                      label="WhatsApp ID"
                      name="whatsapp_id"
                      value={data.whatsapp_id}
                      onChange={(value) => handleFieldChange('whatsapp_id', value)}
                      error={errors.whatsapp_id}
                      placeholder="+880 1711 000000"
                      required
                    />
                  </div>
                </div>

                {/* Identity Documents */}
                <div className="space-y-2">
                  <h4 className="text-sm font-medium text-muted-foreground">Identity Documents</h4>
                  <div className="grid gap-3 sm:gap-4 md:gap-6 grid-cols-1 sm:grid-cols-2">
                    <FormField
                      label="National ID Number"
                      name="nid_number"
                      value={data.nid_number}
                      onChange={(value) => handleFieldChange('nid_number', value)}
                      error={errors.nid_number}
                      placeholder="Enter National ID number"
                    />

                    <div className="space-y-1">
                      <FormFileUpload
                        label="NID Scan"
                        name="nid_file"
                        value={data.nid_file}
                        onChange={(file) => handleFieldChange('nid_file', file)}
                        error={errors.nid_file}
                        accept="image/*,.pdf"
                        maxSize={2 * 1024 * 1024}
                      />
                      {(user.nid_file as string | null) && (
                        <a href={`/storage/${user.nid_file}`} target="_blank" rel="noreferrer" className="text-xs text-blue-600 hover:underline">
                          View current file
                        </a>
                      )}
                    </div>

                    <FormField
                      label="Passport Number"
                      name="passport_number"
                      value={data.passport_number}
                      onChange={(value) => handleFieldChange('passport_number', value)}
                      error={errors.passport_number}
                      placeholder="Enter passport number"
                    />
                  </div>
                </div>

                {/* Personal Information */}
                <div className="space-y-2">
                  <h4 className="text-sm font-medium text-muted-foreground">Personal Information</h4>
                  <div className="grid gap-3 sm:gap-4 md:gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3">
                    <FormSelect
                      label="Blood Group"
                      name="blood_group"
                      value={data.blood_group}
                      onChange={(value) => handleFieldChange('blood_group', value)}
                      error={errors.blood_group}
                      options={bloodGroups}
                      placeholder="Select blood group..."
                    />

                    <FormDatePicker
                      label="Joining Date"
                      name="joining_date"
                      value={data.joining_date}
                      onChange={(value) => handleFieldChange('joining_date', value)}
                      error={errors.joining_date}
                    />

                    <FormSelect
                      label="Status"
                      name="status"
                      value={data.status}
                      onChange={(value) => handleFieldChange('status', value)}
                      error={errors.status}
                      options={statusOptions}
                      required
                    />
                  </div>
                </div>

                {/* Emergency Contact */}
                <div className="space-y-2">
                  <h4 className="text-sm font-medium text-muted-foreground">Emergency Contact</h4>
                  <div className="grid gap-3 sm:gap-4 md:gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3">
                    <FormField
                      label="Contact Name"
                      name="emergency_contact_name"
                      value={data.emergency_contact_name}
                      onChange={(value) => handleFieldChange('emergency_contact_name', value)}
                      error={errors.emergency_contact_name}
                      placeholder="Enter emergency contact name"
                    />

                    <FormField
                      label="Relation"
                      name="emergency_contact_relation"
                      value={data.emergency_contact_relation}
                      onChange={(value) => handleFieldChange('emergency_contact_relation', value)}
                      error={errors.emergency_contact_relation}
                      placeholder="e.g., Spouse, Parent"
                    />

                    <FormField
                      label="Phone Number"
                      name="emergency_phone"
                      value={data.emergency_phone}
                      onChange={(value) => handleFieldChange('emergency_phone', value)}
                      error={errors.emergency_phone}
                      placeholder="+880 1711 000000"
                    />
                  </div>
                </div>

                {/* Addresses - Compact Layout */}
                <div className="space-y-2">
                  <h4 className="text-sm font-medium text-muted-foreground">Address Information</h4>
                  <div className="grid gap-3 sm:gap-4 md:gap-6 grid-cols-1 md:grid-cols-2">
                    <FormField
                      label="Area/Location"
                      name="area"
                      value={data.area}
                      onChange={(value) => handleFieldChange('area', value)}
                      error={errors.area}
                      placeholder="e.g., Gulshan, Mohakhali, Banani"
                    />

                    <div className="md:col-span-2 grid gap-3 sm:gap-4 md:gap-6 grid-cols-1 md:grid-cols-2">
                      <FormTextarea
                        label="Present Address"
                        name="present_address"
                        value={data.present_address}
                        onChange={(value) => handleFieldChange('present_address', value)}
                        error={errors.present_address}
                        placeholder="Enter current address"
                        rows={2}
                      />

                      <FormTextarea
                        label="Permanent Address"
                        name="permanent_address"
                        value={data.permanent_address}
                        onChange={(value) => handleFieldChange('permanent_address', value)}
                        error={errors.permanent_address}
                        placeholder="Enter permanent address"
                        rows={2}
                      />
                    </div>
                  </div>
                </div>

                {/* User Type Preview - Compact */}
                {data.user_type && (
                  <div className="p-3 rounded-md bg-muted/30 border-l-2 border-primary">
                    <div className="flex items-center gap-2 text-sm">
                      {enhancedUserTypeOptions.find(opt => opt.value === data.user_type)?.icon}
                      <span className="font-medium">
                        {enhancedUserTypeOptions.find(opt => opt.value === data.user_type)?.label}
                      </span>
                      <span className="text-muted-foreground">-</span>
                      <span className="text-muted-foreground text-xs">
                        {enhancedUserTypeOptions.find(opt => opt.value === data.user_type)?.description}
                      </span>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Driver Information Card (conditional) - Compact */}
            {showDriverFields && (
              <Card className="shadow-sm">
                <CardHeader className="pb-3 px-4 sm:px-6">
                  <div className="flex items-center gap-2">
                    <div className="p-1.5 bg-green-100 rounded-md">
                      <Car className="h-4 w-4 text-green-600" />
                    </div>
                    <div>
                      <CardTitle className="text-lg">Driver Information</CardTitle>
                      <CardDescription className="text-sm">License details and driver-specific information</CardDescription>
                    </div>
                  </div>
                </CardHeader>

                <CardContent className="space-y-4 px-4 sm:px-6">
                  <div className="grid gap-3 sm:gap-4 md:gap-6 grid-cols-1 md:grid-cols-2">
                    <FormSelect
                      label="Vendor"
                      name="vendor_id"
                      value={data.vendor_id}
                      onChange={(value) => handleFieldChange('vendor_id', value)}
                      error={errors.vendor_id}
                      options={vendorOptions}
                      placeholder="Select vendor..."
                      required
                    />

                    <FormField
                      label="Driving License Number"
                      name="driving_license_no"
                      value={data.driving_license_no}
                      onChange={(value) => handleFieldChange('driving_license_no', value)}
                      error={errors.driving_license_no}
                      placeholder="Enter license number"
                      required={showDriverFields}
                    />

                    <div className="space-y-1">
                      <FormFileUpload
                        label="License Scan"
                        name="driving_license_file"
                        value={data.driving_license_file}
                        onChange={(file) => handleFieldChange('driving_license_file', file)}
                        error={errors.driving_license_file}
                        accept="image/*,.pdf"
                        maxSize={2 * 1024 * 1024}
                      />
                      {(user.driving_license_file as string | null) && (
                        <a href={`/storage/${user.driving_license_file}`} target="_blank" rel="noreferrer" className="text-xs text-blue-600 hover:underline">
                          View current file
                        </a>
                      )}
                    </div>

                    <FormSelect
                      label="License Class"
                      name="license_class"
                      value={data.license_class}
                      onChange={(value) => handleFieldChange('license_class', value)}
                      error={errors.license_class}
                      options={licenseClasses}
                      placeholder="Select license class..."
                      required={showDriverFields}
                    />
                  </div>

                  <div className="grid gap-3 sm:gap-4 md:gap-6 grid-cols-1 sm:grid-cols-2 md:grid-cols-3">
                    <FormDatePicker
                      label="License Issue Date"
                      name="license_issue_date"
                      value={data.license_issue_date}
                      onChange={(value) => handleFieldChange('license_issue_date', value)}
                      error={errors.license_issue_date}
                    />

                    <FormDatePicker
                      label="License Expiry Date"
                      name="license_expiry_date"
                      value={data.license_expiry_date}
                      onChange={(value) => handleFieldChange('license_expiry_date', value)}
                      error={errors.license_expiry_date}
                      required={showDriverFields}
                    />

                    <FormSelect
                      label="Driver Status"
                      name="driver_status"
                      value={data.driver_status}
                      onChange={(value) => handleFieldChange('driver_status', value)}
                      error={errors.driver_status}
                      options={driverStatusOptions}
                    />
                  </div>
                </CardContent>
              </Card>
            )}

            {/* File Uploads & Security - Compact */}
            <Card className="shadow-sm">
              <CardHeader className="pb-3 px-4 sm:px-6">
                <CardTitle className="text-lg">Files & Security</CardTitle>
                <CardDescription className="text-sm">Upload images and update password (optional)</CardDescription>
              </CardHeader>

              <CardContent className="space-y-4 px-4 sm:px-6">
                {/* File Uploads - Compact */}
                <div className="grid gap-3 sm:gap-4 md:gap-6 grid-cols-1 md:grid-cols-2">
                  <FormFileUpload
                    label="Profile Image"
                    name="image"
                    onChange={(file) => handleFieldChange('image', file)}
                    error={errors.image}
                    accept="image/*"
                    maxSize={2 * 1024 * 1024}
                  />

                  <FormFileUpload
                    label="ID Photo"
                    name="photo"
                    onChange={(file) => handleFieldChange('photo', file)}
                    error={errors.photo}
                    accept="image/*"
                    maxSize={2 * 1024 * 1024}
                  />
                </div>

                {/* Password Section - Compact */}
                <div className="border-t pt-4">
                  <div className="mb-2">
                    <h5 className="text-sm font-medium text-muted-foreground">Change Password (Optional)</h5>
                    <p className="text-xs text-muted-foreground">Leave blank to keep current password</p>
                  </div>
                  <div className="grid gap-3 sm:gap-4 md:gap-6 grid-cols-1 md:grid-cols-2">
                    <FormField
                      label="New Password"
                      name="password"
                      type="password"
                      value={data.password}
                      onChange={(value) => handleFieldChange('password', value)}
                      error={errors.password}
                      placeholder="Enter new password"
                    />

                    <FormField
                      label="Confirm New Password"
                      name="password_confirmation"
                      type="password"
                      value={data.password_confirmation}
                      onChange={(value) => handleFieldChange('password_confirmation', value)}
                      error={errors.password_confirmation}
                      placeholder="Confirm new password"
                    />
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Compact Form Actions */}
            <div className="flex justify-end gap-2 pt-4">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => reset()}
                disabled={processing}
              >
                Reset
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => window.history.back()}
                disabled={processing}
              >
                Cancel
              </Button>
              <Button
                type="submit"
                size="sm"
                disabled={processing}
              >
                <Save className="h-4 w-4" />
                Update User
              </Button>
            </div>
          </BaseForm>
        </div>
      </div>
    </AppLayout>
  );
}
