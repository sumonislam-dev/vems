import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { FormMultiSelect } from '@/base-components/base-form';
import { AlertCircle, ArrowLeft, Building2, Save } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/base-components/page-header';

/**
 * Department Create Page
 *
 * Features:
 * - Comprehensive form for creating new departments
 * - Department head selection from existing users
 * - Contact information
 * - Form validation with error handling
 * - Professional UI with proper spacing and feedback
 */

interface User {
  id: number;
  name: string;
  email: string;
  department?: string;
}

interface CreateDepartmentProps {
  users: User[];
}

export default function CreateDepartment({ users }: CreateDepartmentProps) {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    code: '',
    description: '',
    location: '',
    phone: '',
    email: '',
    head_id: '',
    is_active: '1',
  });

  // Convert users to options for select
  const userOptions = users.map(user => ({
    value: user.id.toString(),
    label: `${user.name} (${user.email})`,
    description: user.department || 'No department assigned'
  }));

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route('departments.store'));
  };

  // Generate code from name
  const generateCode = (name: string) => {
    return name
      .toUpperCase()
      .replace(/[^A-Z0-9]/g, '')
      .substring(0, 10);
  };

  const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const name = e.target.value;
    setData('name', name);
    // Auto-generate code if it's empty or matches the previous auto-generated code
    if (!data.code || data.code === generateCode(data.name)) {
      setData('code', generateCode(name));
    }
  };

  return (
    <AppLayout>
      <Head title="Create Department" />

      <div className="space-y-6">
        {/* Page Header */}
        <PageHeader
          title="Create Department"
          description="Add a new department to your organization"
          actions={[
            {
              label: "Back to Departments",
              icon: <ArrowLeft className="mr-2 h-4 w-4" />,
              href: route('departments.index'),
              variant: "outline"
            }
          ]}
        />

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid gap-6 lg:grid-cols-3">
            {/* Main Form */}
            <div className="lg:col-span-2 space-y-6">
              {/* Basic Information */}
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center space-x-2">
                    <Building2 className="h-5 w-5" />
                    <span>Basic Information</span>
                  </CardTitle>
                  <CardDescription>
                    Enter the fundamental details for this department
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="name">
                        Department Name <span className="text-red-500">*</span>
                      </Label>
                      <Input
                        id="name"
                        type="text"
                        placeholder="Enter department name"
                        value={data.name}
                        onChange={handleNameChange}
                        className={errors.name ? 'border-red-500' : ''}
                        required
                      />
                      {errors.name && (
                        <p className="text-sm text-red-600">{errors.name}</p>
                      )}
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="code">
                        Department Code <span className="text-red-500">*</span>
                      </Label>
                      <Input
                        id="code"
                        type="text"
                        placeholder="Enter department code"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        className={errors.code ? 'border-red-500' : ''}
                        required
                      />
                      {errors.code && (
                        <p className="text-sm text-red-600">{errors.code}</p>
                      )}
                      <p className="text-sm text-gray-500">
                        Unique identifier for the department
                      </p>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="description">Description</Label>
                    <textarea
                      id="description"
                      placeholder="Enter department description"
                      value={data.description}
                      onChange={(e) => setData('description', e.target.value)}
                      className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${
                        errors.description ? 'border-red-500' : 'border-gray-300'
                      }`}
                      rows={3}
                    />
                    {errors.description && (
                      <p className="text-sm text-red-600">{errors.description}</p>
                    )}
                  </div>
                </CardContent>
              </Card>

              {/* Contact Information */}
              <Card>
                <CardHeader>
                  <CardTitle>Contact Information</CardTitle>
                  <CardDescription>
                    Add contact details for this department
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                      <Label htmlFor="location">Location</Label>
                      <Input
                        id="location"
                        type="text"
                        placeholder="Enter department location"
                        value={data.location}
                        onChange={(e) => setData('location', e.target.value)}
                        className={errors.location ? 'border-red-500' : ''}
                      />
                      {errors.location && (
                        <p className="text-sm text-red-600">{errors.location}</p>
                      )}
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="phone">Phone</Label>
                      <Input
                        id="phone"
                        type="tel"
                        placeholder="Enter phone number"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                        className={errors.phone ? 'border-red-500' : ''}
                      />
                      {errors.phone && (
                        <p className="text-sm text-red-600">{errors.phone}</p>
                      )}
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                      id="email"
                      type="email"
                      placeholder="Enter department email"
                      value={data.email}
                      onChange={(e) => setData('email', e.target.value)}
                      className={errors.email ? 'border-red-500' : ''}
                    />
                    {errors.email && (
                      <p className="text-sm text-red-600">{errors.email}</p>
                    )}
                  </div>
                </CardContent>
              </Card>

              {/* Department Management */}
              <Card>
                <CardHeader>
                  <CardTitle>Department Management</CardTitle>
                  <CardDescription>
                    Assign department head
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <FormMultiSelect
                    label="Department Head"
                    name="head_id"
                    placeholder="Select department head"
                    options={userOptions}
                    value={data.head_id ? [data.head_id] : []}
                    onChange={(values) => setData('head_id', values[0] || '')}
                    error={errors.head_id}
                    searchable={true}
                    description="Choose a user to lead this department"
                  />
                </CardContent>
              </Card>
            </div>

            {/* Sidebar */}
            <div className="space-y-6">
              {/* Status Settings */}
              <Card>
                <CardHeader>
                  <CardTitle>Status Settings</CardTitle>
                  <CardDescription>
                    Configure department status
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="flex items-center space-x-2">
                    <Checkbox
                      id="is_active"
                      checked={data.is_active === '1'}
                      onCheckedChange={(checked: boolean) => setData('is_active', checked ? '1' : '0')}
                    />
                    <Label htmlFor="is_active">Active Department</Label>
                  </div>
                  <p className="text-sm text-gray-500 mt-2">
                    {data.is_active === '1'
                      ? "Department is active and visible to users"
                      : "Department is inactive and hidden from users"
                    }
                  </p>
                  {errors.is_active && (
                    <p className="text-sm text-red-600 mt-1">{errors.is_active}</p>
                  )}
                </CardContent>
              </Card>

              {/* Form Actions */}
              <Card>
                <CardHeader>
                  <CardTitle>Actions</CardTitle>
                  <CardDescription>
                    Save or cancel department creation
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                  <Button
                    type="submit"
                    disabled={processing}
                    className="w-full"
                  >
                    <Save className="mr-2 h-4 w-4" />
                    {processing ? 'Creating...' : 'Create Department'}
                  </Button>

                  <Link href={route('departments.index')}>
                    <Button variant="outline" className="w-full">
                      Cancel
                    </Button>
                  </Link>
                </CardContent>
              </Card>

              {/* Validation Errors */}
              {Object.keys(errors).length > 0 && (
                <Card className="border-red-200">
                  <CardHeader>
                    <CardTitle className="text-red-600 flex items-center space-x-2">
                      <AlertCircle className="h-5 w-5" />
                      <span>Validation Errors</span>
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <ul className="space-y-1 text-sm">
                      {Object.entries(errors).map(([field, message]) => (
                        <li key={field} className="text-red-600">
                          • {message}
                        </li>
                      ))}
                    </ul>
                  </CardContent>
                </Card>
              )}

              {/* Help Information */}
              <Card>
                <CardHeader>
                  <CardTitle>Help</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-sm text-gray-600">
                  <p>
                    <strong>Department Code:</strong> A unique identifier that will be auto-generated from the name.
                  </p>
                  <p>
                    <strong>Department Head:</strong> The user responsible for managing this department.
                  </p>
                </CardContent>
              </Card>
            </div>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
