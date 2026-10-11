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
    Building,
    MapPin,
    Phone,
    Mail,
    User,
    Users,
    Calendar
} from 'lucide-react';

interface Department {
    id: number;
    name: string;
    code: string;
    description: string;
    location: string;
    phone: string;
    email: string;
    is_active: boolean;
    budget_allocation: Record<string, number> | null;
    total_budget: number;
    head: {
        id: number;
        name: string;
        email: string;
    } | null;
    users: Array<{
        id: number;
        name: string;
        email: string;
        user_type: string;
        status: string;
        roles: string[];
        created_at: string;
    }>;
    created_at: string;
    updated_at: string;
}

interface Props {
    department: Department;
    stats: {
        total_users: number;
        active_users: number;
        drivers: number;
        managers: number;
    };
}

export default function DepartmentShow({ department, stats }: Props) {
    const can = useCan();
    const handleDelete = async () => {
        if (await confirmDelete(`Are you sure you want to delete ${department.name}?`)) {
            router.delete(`/departments/${department.id}`);
        }
    };

    return (
        <AppSidebarLayout>
            <Head title={`Department - ${department.name}`} />

            <div className="space-y-6">
                <PageHeader
                    title={department.name}
                    description={department.description || 'Department details and management'}
                    actions={[
                        {
                            label: 'Back to Departments',
                            variant: 'outline',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: '/departments',
                        },
                        {
                            label: 'Edit Department',
                            icon: <Edit className="mr-2 h-4 w-4" />,
                            href: `/departments/${department.id}/edit`,
                            permission: 'edit-departments',
                        },
                    ]}
                />

                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Users</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_users}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Active Users</CardTitle>
                            <User className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.active_users}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Drivers</CardTitle>
                            <User className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.drivers}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Managers</CardTitle>
                            <User className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.managers}</div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Department Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Department Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-3">
                                <div className="flex items-center space-x-2">
                                    <Building className="h-4 w-4 text-muted-foreground" />
                                    <span className="font-medium">Code:</span>
                                    <span>{department.code || 'N/A'}</span>
                                </div>

                                {department.location && (
                                    <div className="flex items-center space-x-2">
                                        <MapPin className="h-4 w-4 text-muted-foreground" />
                                        <span className="font-medium">Location:</span>
                                        <span>{department.location}</span>
                                    </div>
                                )}

                                {department.phone && (
                                    <div className="flex items-center space-x-2">
                                        <Phone className="h-4 w-4 text-muted-foreground" />
                                        <span className="font-medium">Phone:</span>
                                        <span>{department.phone}</span>
                                    </div>
                                )}

                                {department.email && (
                                    <div className="flex items-center space-x-2">
                                        <Mail className="h-4 w-4 text-muted-foreground" />
                                        <span className="font-medium">Email:</span>
                                        <span>{department.email}</span>
                                    </div>
                                )}

                                <div className="flex items-center space-x-2">
                                    <span className="font-medium">Status:</span>
                                    <Badge variant={department.is_active ? 'default' : 'secondary'}>
                                        {department.is_active ? 'Active' : 'Inactive'}
                                    </Badge>
                                </div>

                                {department.head && (
                                    <div className="flex items-center space-x-2">
                                        <User className="h-4 w-4 text-muted-foreground" />
                                        <span className="font-medium">Department Head:</span>
                                        <div>
                                            <div>{department.head.name}</div>
                                            <div className="text-sm text-muted-foreground">{department.head.email}</div>
                                        </div>
                                    </div>
                                )}

                                <div className="flex items-center space-x-2">
                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                    <span className="font-medium">Created:</span>
                                    <span>{department.created_at}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Department Users */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Department Users</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {department.users.length > 0 ? (
                                <div className="space-y-3">
                                    {department.users.slice(0, 10).map((user) => (
                                        <div key={user.id} className="flex items-center justify-between p-3 border rounded-lg">
                                            <div className="flex items-center space-x-3">
                                                <div className="flex-shrink-0 h-8 w-8 bg-gray-100 rounded-full flex items-center justify-center">
                                                    <User className="h-4 w-4 text-gray-600" />
                                                </div>
                                                <div>
                                                    <div className="font-medium">{user.name}</div>
                                                    <div className="text-sm text-muted-foreground">{user.email}</div>
                                                </div>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <Badge variant="outline">{user.user_type}</Badge>
                                                <Badge variant={user.status === 'active' ? 'default' : 'secondary'}>
                                                    {user.status}
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                    {department.users.length > 10 && (
                                        <div className="text-center text-sm text-muted-foreground">
                                            And {department.users.length - 10} more users...
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="text-center text-muted-foreground py-8">
                                    No users assigned to this department
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Danger Zone */}
                {can('delete-departments') && (
                    <Card className="border-destructive">
                        <CardHeader>
                            <CardTitle className="text-destructive">Danger Zone</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div>
                                    <h4 className="font-medium">Delete Department</h4>
                                    <p className="text-sm text-muted-foreground">
                                        Permanently delete this department. This action cannot be undone.
                                    </p>
                                </div>
                                <Button variant="destructive" onClick={handleDelete}>
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete Department
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppSidebarLayout>
    );
}
