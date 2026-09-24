import { BaseForm, FormField, FormSelect } from '@/base-components/base-form';
import { PageHeader } from '@/base-components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, Permission } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Search, Shield } from 'lucide-react';
import { useState } from 'react';

interface CreateRoleProps {
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
    { title: 'Create Role', href: '#' },
];

export default function CreateRole({ permissions }: CreateRoleProps) {
    const { data, setData, post, processing, errors } = useForm<RoleForm>({
        name: '',
        guard_name: 'web',
        permissions: [],
    });
    const [permissionSearch, setPermissionSearch] = useState('');

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('roles.store'));
    };

    const handlePermissionChange = (permissionId: number, checked: boolean) => {
        if (checked) {
            setData('permissions', [...data.permissions, permissionId]);
        } else {
            setData(
                'permissions',
                data.permissions.filter((id) => id !== permissionId),
            );
        }
    };

    const selectAllPermissions = () => {
        setData(
            'permissions',
            permissions.map((p) => p.id),
        );
    };

    const deselectAllPermissions = () => {
        setData('permissions', []);
    };

    // Group permissions by category, filtered by the search box
    const filteredPermissions = permissions.filter((permission) => permission.name.toLowerCase().includes(permissionSearch.toLowerCase()));
    const groupedPermissions = filteredPermissions.reduce(
        (groups, permission) => {
            const category = permission.name.split(' ').slice(1).join(' ') || 'general';
            if (!groups[category]) {
                groups[category] = [];
            }
            groups[category].push(permission);
            return groups;
        },
        {} as Record<string, Permission[]>,
    );

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Role" />

            <div className="space-y-6">
                <PageHeader
                    title="Create Role"
                    description="Create a new role and assign permissions."
                    actions={[
                        {
                            label: 'Back to Roles',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: route('roles.index'),
                            variant: 'outline',
                        },
                    ]}
                />

                <div className="w-full">
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

                                    <div className="flex gap-3">
                                        <Button type="button" variant="outline" onClick={() => window.history.back()} disabled={processing}>
                                            Cancel
                                        </Button>
                                        <Button type="submit" disabled={processing}>
                                            <Plus className="h-4 w-4" />
                                            Create Role
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Permissions */}
                            <Card className="lg:col-span-2">
                                <CardHeader>
                                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <CardTitle>Permissions</CardTitle>
                                        <div className="flex flex-wrap gap-2">
                                            <Button type="button" variant="outline" size="sm" onClick={selectAllPermissions}>
                                                Select All
                                            </Button>
                                            <Button type="button" variant="outline" size="sm" onClick={deselectAllPermissions}>
                                                Deselect All
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="relative mt-3">
                                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            value={permissionSearch}
                                            onChange={(e) => setPermissionSearch(e.target.value)}
                                            placeholder="Search permissions..."
                                            className="pl-9"
                                        />
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="mt-2 space-y-6">
                                        {filteredPermissions.length === 0 && (
                                            <p className="text-sm text-muted-foreground">No permissions match "{permissionSearch}".</p>
                                        )}
                                        {Object.entries(groupedPermissions).map(([category, categoryPermissions]) => (
                                            <div key={category}>
                                                <h4 className="mb-3 font-medium text-gray-900 capitalize">{category} Permissions</h4>
                                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 2xl:grid-cols-4">
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
                                                                className="text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                                            >
                                                                {permission.name}
                                                            </label>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                    {errors.permissions && <p className="mt-2 text-sm text-red-600">{errors.permissions}</p>}
                                </CardContent>
                            </Card>
                        </div>
                    </BaseForm>
                </div>
            </div>
        </AppSidebarLayout>
    );
}
