import { BaseForm, FormField, FormSelect } from '@/base-components/base-form';
import { confirmDelete } from '@/components/confirm-dialog';
import { PageHeader } from '@/base-components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, Permission } from '@/types';
import { Head, useForm, router } from '@inertiajs/react';
import { ArrowLeft, Save, Shield, Trash2 } from 'lucide-react';

interface Role {
    id: number;
    name: string;
    guard_name: string;
    permissions: Permission[];
    users_count?: number;
    created_at: string;
    updated_at: string;
}

interface EditRoleProps {
    role: Role;
    permissions: Permission[];
}

type RoleForm = {
    name: string;
    guard_name: string;
    permissions: number[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Roles', href: '/roles' },
    { title: 'Edit Role', href: '#' },
];

export default function EditRole({ role, permissions }: EditRoleProps) {
    const { data, setData, put, processing, errors } = useForm<RoleForm>({
        name: role.name,
        guard_name: role.guard_name,
        permissions: role.permissions.map(p => p.id),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('roles.update', role.id));
    };

    const handleDelete = async () => {
        if (await confirmDelete(`Are you sure you want to delete the role "${role.name}"?`)) {
            router.delete(route('roles.destroy', role.id));
        }
    };

    const handlePermissionChange = (permissionId: number, checked: boolean) => {
        if (checked) {
            setData('permissions', [...data.permissions, permissionId]);
        } else {
            setData('permissions', data.permissions.filter(id => id !== permissionId));
        }
    };

    const selectAllPermissions = () => {
        setData('permissions', permissions.map(p => p.id));
    };

    const deselectAllPermissions = () => {
        setData('permissions', []);
    };

    // Group permissions by category
    const groupedPermissions = permissions.reduce((groups, permission) => {
        const category = permission.name.split(' ').slice(1).join(' ') || 'general';
        if (!groups[category]) {
            groups[category] = [];
        }
        groups[category].push(permission);
        return groups;
    }, {} as Record<string, Permission[]>);

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Role - ${role.name}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Edit Role - ${role.name}`}
                    description="Update role information and permissions."
                    actions={[
                        {
                            label: 'Back to Roles',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: route('roles.index'),
                            variant: 'outline',
                        },
                    ]}
                />

                <div className="max-w-4xl">
                    <BaseForm onSubmit={submit}>
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                            {/* Role Information */}
                            <Card className="lg:col-span-1">
                                <CardHeader>
                                    <CardTitle className="flex items-center space-x-2">
                                        <Shield className="h-5 w-5" />
                                        <span>Role Information</span>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <FormField
                                        label="Role Name"
                                        name="name"
                                        value={data.name}
                                        onChange={(value) => setData('name', value)}
                                        error={errors.name}
                                        required
                                        placeholder="e.g., Manager, Editor"
                                    />

                                    <FormSelect
                                        label="Guard Name"
                                        name="guard_name"
                                        value={data.guard_name}
                                        onChange={(value) => setData('guard_name', value)}
                                        options={[
                                            { label: 'Web', value: 'web' },
                                            { label: 'API', value: 'api' },
                                        ]}
                                        error={errors.guard_name}
                                        required
                                    />

                                    <div className="pt-4 border-t">
                                        <div className="text-sm text-gray-600 space-y-1">
                                            <p><strong>Created:</strong> {new Date(role.created_at).toLocaleDateString()}</p>
                                            <p><strong>Updated:</strong> {new Date(role.updated_at).toLocaleDateString()}</p>
                                            {role.users_count !== undefined && (
                                                <p><strong>Users:</strong> {role.users_count}</p>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Permissions */}
                            <Card className="lg:col-span-2">
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle>Permissions</CardTitle>
                                        <div className="flex space-x-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={selectAllPermissions}
                                            >
                                                Select All
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={deselectAllPermissions}
                                            >
                                                Deselect All
                                            </Button>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-6">
                                        {Object.entries(groupedPermissions).map(([category, categoryPermissions]) => (
                                            <div key={category}>
                                                <h4 className="font-medium text-gray-900 mb-3 capitalize">
                                                    {category} Permissions
                                                </h4>
                                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                    {categoryPermissions.map((permission) => (
                                                        <div key={permission.id} className="flex items-center space-x-2">
                                                            <Checkbox
                                                                id={`permission-${permission.id}`}
                                                                checked={data.permissions.includes(permission.id)}
                                                                onCheckedChange={(checked) =>
                                                                    handlePermissionChange(permission.id, checked as boolean)
                                                                }
                                                            />
                                                            <label
                                                                htmlFor={`permission-${permission.id}`}
                                                                className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                                            >
                                                                {permission.name}
                                                            </label>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    {errors.permissions && (
                                        <p className="text-sm text-red-600 mt-2">{errors.permissions}</p>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        <div className="flex justify-between pt-6">
                            <div className="flex gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => window.history.back()}
                                    disabled={processing}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                >
                                    <Save className="mr-2 h-4 w-4" />
                                    Update Role
                                </Button>
                            </div>

                            {/* Danger Zone */}
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={handleDelete}
                                disabled={processing}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Role
                            </Button>
                        </div>
                    </BaseForm>
                </div>
            </div>
        </AppSidebarLayout>
    );
}
