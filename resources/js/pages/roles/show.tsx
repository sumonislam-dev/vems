import React from 'react';
import { confirmDelete } from '@/components/confirm-dialog';
import { Head, router } from '@inertiajs/react';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { PageHeader } from '@/base-components/page-header';
import { useCan } from '@/lib/permissions';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    ArrowLeft,
    Edit,
    Trash2,
    Shield,
    Users,
    Calendar,
    Key
} from 'lucide-react';
import { BreadcrumbItem, Permission } from '@/types';

interface User {
    id: number;
    name: string;
    email: string;
    user_type: string;
    status: string;
}

interface Role {
    id: number;
    name: string;
    guard_name: string;
    permissions: Permission[];
    users: User[];
    created_at: string;
    updated_at: string;
}

interface Props {
    role: Role;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Roles', href: '/roles' },
    { title: 'Role Details', href: '#' },
];

export default function RoleShow({ role }: Props) {
    const can = useCan();
    const handleDelete = async () => {
        if (await confirmDelete(`Are you sure you want to delete the role "${role.name}"?`)) {
            router.delete(route('roles.destroy', role.id));
        }
    };

    // Group permissions by category
    const groupedPermissions = role.permissions.reduce((groups, permission) => {
        const category = permission.name.split(' ').slice(1).join(' ') || 'general';
        if (!groups[category]) {
            groups[category] = [];
        }
        groups[category].push(permission);
        return groups;
    }, {} as Record<string, Permission[]>);

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`Role - ${role.name}`} />

            <div className="space-y-6">
                <PageHeader
                    title={role.name}
                    description={`Role details and permissions for ${role.name}`}
                    actions={[
                        {
                            label: 'Back to Roles',
                            variant: 'outline',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: route('roles.index'),
                        },
                        {
                            label: 'Edit Role',
                            icon: <Edit className="mr-2 h-4 w-4" />,
                            href: route('roles.edit', role.id),
                            permission: 'edit-roles',
                        },
                    ]}
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Role Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center space-x-2">
                                <Shield className="h-5 w-5" />
                                <span>Role Information</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-3">
                                <div className="flex items-center space-x-2">
                                    <span className="font-medium">Name:</span>
                                    <span>{role.name}</span>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <span className="font-medium">Guard:</span>
                                    <Badge variant="outline">{role.guard_name}</Badge>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                    <span className="font-medium">Users:</span>
                                    <span>{role.users.length}</span>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Key className="h-4 w-4 text-muted-foreground" />
                                    <span className="font-medium">Permissions:</span>
                                    <span>{role.permissions.length}</span>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                    <span className="font-medium">Created:</span>
                                    <span>{new Date(role.created_at).toLocaleDateString()}</span>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                    <span className="font-medium">Updated:</span>
                                    <span>{new Date(role.updated_at).toLocaleDateString()}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Users with this Role */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center space-x-2">
                                <Users className="h-5 w-5" />
                                <span>Users ({role.users.length})</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {role.users.length > 0 ? (
                                <div className="space-y-3">
                                    {role.users.map((user) => (
                                        <div key={user.id} className="flex items-center justify-between p-3 border rounded-lg">
                                            <div>
                                                <div className="font-medium">{user.name}</div>
                                                <div className="text-sm text-muted-foreground">{user.email}</div>
                                            </div>
                                            <div className="flex space-x-2">
                                                <Badge variant="outline">{user.user_type}</Badge>
                                                <Badge variant={user.status === 'active' ? 'default' : 'secondary'}>
                                                    {user.status}
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center text-muted-foreground py-8">
                                    No users assigned to this role
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Permissions */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center space-x-2">
                                <Key className="h-5 w-5" />
                                <span>Permissions ({role.permissions.length})</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {role.permissions.length > 0 ? (
                                <div className="space-y-4">
                                    {Object.entries(groupedPermissions).map(([category, categoryPermissions]) => (
                                        <div key={category}>
                                            <h4 className="font-medium text-gray-900 mb-2 capitalize">
                                                {category}
                                            </h4>
                                            <div className="flex flex-wrap gap-2">
                                                {categoryPermissions.map((permission) => (
                                                    <Badge key={permission.id} variant="secondary">
                                                        {permission.name}
                                                    </Badge>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center text-muted-foreground py-8">
                                    No permissions assigned to this role
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Danger Zone */}
                {can('delete-roles') && (
                    <Card className="border-destructive">
                        <CardHeader>
                            <CardTitle className="text-destructive">Danger Zone</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div>
                                    <h4 className="font-medium">Delete Role</h4>
                                    <p className="text-sm text-muted-foreground">
                                        Permanently delete this role. This action cannot be undone.
                                        {role.users.length > 0 && (
                                            <span className="text-red-600 block">
                                                Cannot delete: This role has {role.users.length} user(s) assigned.
                                            </span>
                                        )}
                                    </p>
                                </div>
                                <Button
                                    variant="destructive"
                                    onClick={handleDelete}
                                    disabled={role.users.length > 0}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete Role
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppSidebarLayout>
    );
}
