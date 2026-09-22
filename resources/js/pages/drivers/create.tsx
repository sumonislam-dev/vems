import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/base-components/page-header';
import { useFormValidation, commonValidationRules, ValidationRules } from '@/hooks/use-form-validation';
import { UserForm, UserFormBody } from '@/components/user-form-body';

interface CreateDriverProps {
  departments: Array<{ id: number; name: string }>;
  vendors: Array<{ id: number; name: string }>;
  roles: Array<{ id: number; name: string }>;
  userTypes: Array<{ value: string; label: string }>;
  licenseClasses: Array<{ value: string; label: string }>;
  bloodGroups: Array<{ value: string; label: string }>;
  defaults: {
    user_type: string;
    department_id?: number;
    role_id?: number;
    driver_status: string;
  };
}

export default function CreateDriver({ departments, vendors, roles, userTypes, licenseClasses, bloodGroups, defaults }: CreateDriverProps) {
  const { data, setData, post, processing, errors: serverErrors, reset } = useForm<UserForm>({
    name: '',
    username: '',
    employee_id: '',
    email: '',
    user_type: defaults.user_type || 'driver',
    department_id: defaults.department_id?.toString() || '',
    vendor_id: '',
    roles: defaults.role_id ? [defaults.role_id.toString()] : [],
    official_phone: '',
    personal_phone: '',
    whatsapp_id: '',
    emergency_phone: '',
    emergency_contact_name: '',
    emergency_contact_relation: '',
    present_address: '',
    permanent_address: '',
    area: '',
    blood_group: '',
    nid_number: '',
    nid_file: null,
    passport_number: '',
    driving_license_no: '',
    driving_license_file: null,
    license_class: '',
    license_issue_date: '',
    license_expiry_date: '',
    joining_date: '',
    status: 'active',
    driver_status: defaults.driver_status || 'available',
    password: '',
    password_confirmation: '',
    image: null,
    photo: null,
  });

  const [showDriverFields, setShowDriverFields] = useState(true);

  const validationRules: ValidationRules<UserForm> = {
    name: [
      commonValidationRules.required('Full name is required'),
      commonValidationRules.minLength(2, 'Name must be at least 2 characters'),
      commonValidationRules.maxLength(255, 'Name must be less than 255 characters'),
    ],
    username: [
      commonValidationRules.required('Username is required'),
      commonValidationRules.minLength(3, 'Username must be at least 3 characters'),
      commonValidationRules.maxLength(50, 'Username must be less than 50 characters'),
    ],
    email: [commonValidationRules.email('Please enter a valid email address')],
    password: [
      commonValidationRules.required('Password is required'),
      commonValidationRules.minLength(8, 'Password must be at least 8 characters'),
    ],
    password_confirmation: [
      commonValidationRules.required('Password confirmation is required'),
      (value) => {
        if (value !== data.password) return 'Passwords do not match';
        return undefined;
      },
    ],
    whatsapp_id: [commonValidationRules.pattern(/^\+?[0-9\s-()]+$/, 'Please enter a valid WhatsApp number')],
    nid_number: [
      commonValidationRules.pattern(/^[0-9]{10,17}$/, 'NID number must be 10-17 digits'),
      (value) => {
        if (value && value.length !== 10 && value.length !== 13 && value.length !== 17) {
          return 'NID number must be 10, 13, or 17 digits';
        }
        return undefined;
      },
    ],
    passport_number: [
      commonValidationRules.pattern(/^[A-Z0-9]{6,9}$/, 'Passport number must be 6-9 alphanumeric characters'),
      commonValidationRules.maxLength(9, 'Passport number must be less than 9 characters'),
    ],
    driving_license_no: showDriverFields ? [commonValidationRules.required('Driving license number is required for drivers')] : [],
    license_class: showDriverFields ? [commonValidationRules.required('License class is required for drivers')] : [],
    license_expiry_date: showDriverFields ? [commonValidationRules.required('License expiry date is required for drivers')] : [],
    vendor_id: showDriverFields ? [commonValidationRules.required('Vendor is required for drivers')] : [],
  };

  const { errors: clientErrors, validate, clearError, hasErrors } = useFormValidation(validationRules);

  const getFieldError = (field: keyof UserForm): string | undefined => {
    return serverErrors[field] || clientErrors[field];
  };

  const handleFieldChange = (field: keyof UserForm, value: string | File | null) => {
    if (field === 'image' || field === 'photo' || field === 'nid_file' || field === 'driving_license_file') {
      setData(field, value as File | null);
    } else {
      setData(field, value as string);
    }
    clearError(field);
  };

  const handleUserTypeChange = (value: string) => {
    handleFieldChange('user_type', value);
    setShowDriverFields(['driver', 'transport_manager'].includes(value));

    if (!['driver', 'transport_manager'].includes(value)) {
      setData((prev) => ({
        ...prev,
        driving_license_no: '',
        license_class: '',
        license_issue_date: '',
        license_expiry_date: '',
        driver_status: '',
      }));
    } else {
      setData('driver_status', 'available');
    }
  };

  const getFormCompletion = () => {
    const requiredFields = ['name', 'username', 'vendor_id', 'password', 'password_confirmation'];
    if (showDriverFields) {
      requiredFields.push('driving_license_no', 'license_class', 'license_expiry_date');
    }
    const completedFields = requiredFields.filter((field) => {
      const value = data[field as keyof UserForm];
      return value && value.toString().trim() !== '';
    });
    return Math.round((completedFields.length / requiredFields.length) * 100);
  };

  const formCompletion = getFormCompletion();

  const departmentOptions = departments.map((dept) => ({ label: dept.name, value: dept.id.toString() }));
  const vendorOptions = vendors.map((vendor) => ({ label: vendor.name, value: vendor.id.toString() }));
  const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Inactive', value: 'inactive' },
    { label: 'Suspended', value: 'suspended' },
  ];
  const driverStatusOptions = [
    { label: 'Available', value: 'available' },
    { label: 'On Leave', value: 'on_leave' },
    { label: 'Inactive', value: 'inactive' },
  ];

  const handleSubmit = () => {
    const validationErrors = validate(data);
    if (hasErrors(validationErrors)) return;

    post(route('drivers.store'), {
      onSuccess: () => {},
      onError: () => {},
    });
  };

  return (
    <AppLayout>
      <Head title="Create Driver" />

      <div className="space-y-8">
        <PageHeader
          title="Create Driver"
          description="Add a new driver to the vehicle management system"
          actions={[
            {
              label: 'Back to Drivers',
              icon: <ArrowLeft className="h-4 w-4" />,
              href: route('drivers.index'),
              variant: 'outline',
            },
          ]}
        />

        <div className="max-w-4xl xl:max-w-5xl">
          <UserFormBody
            data={data}
            processing={processing}
            formCompletion={formCompletion}
            showDriverFields={showDriverFields}
            title="Create Driver"
            submitLabel="Create Driver"
            getFieldError={getFieldError}
            handleFieldChange={handleFieldChange}
            handleUserTypeChange={handleUserTypeChange}
            setData={(field, value) => setData(field, value as string & string[] & (File | null))}
            onSubmit={handleSubmit}
            onReset={() => reset()}
            roles={roles}
            userTypes={userTypes}
            vendorOptions={vendorOptions}
            hideUserTypeAndRoles={true}
            licenseClasses={licenseClasses}
            bloodGroups={bloodGroups}
            departmentOptions={departmentOptions}
            statusOptions={statusOptions}
            driverStatusOptions={driverStatusOptions}
          />
        </div>

        {process.env.NODE_ENV === 'development' && Object.keys(clientErrors).length > 0 && (
          <div className="max-w-6xl">
            <div className="p-3 bg-destructive/10 border border-destructive/20 rounded-lg">
              <h4 className="font-medium text-destructive text-sm mb-2">Validation Errors:</h4>
              <ul className="text-xs text-destructive space-y-1">
                {Object.entries(clientErrors).map(([field, error]) => (
                  <li key={field}>
                    • {field}: {error}
                  </li>
                ))}
              </ul>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
