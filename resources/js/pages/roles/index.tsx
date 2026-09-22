import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, DataTableColumn, Role } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Edit, Eye, Plus, Shield, Trash2, Users, Key } from 'lucide-react';

interface PaginatedRoles {
    data: Role[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface RolesPageProps {
    roles: PaginatedRoles;
    stats: {
        total: number;
        with_users: number;
        with_permissions: number;
        empty: number;
    };
    queryParams: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        per_page?: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Roles', href: '/roles' },
];

export default function RolesIndex({ roles, stats, queryParams }: RolesPageProps) {
    // Define table columns
    const columns: DataTableColumn<Role>[] = [
        {
            key: 'name',
            label: 'Role Name',
            sortable: true,
            render: (value) => (
                <div className="flex items-center space-x-2">
                    <Shield className="h-4 w-4 text-blue-500" />
                    <span className="font-medium capitalize">{value}</span>
                </div>
            ),
        },
        {
            key: 'guard_name',
            label: 'Guard',
            sortable: true,
            render: (value) => (
                <Badge variant="outline" className="font-mono">
                    {value}
                </Badge>
            ),
        },
        {
            key: 'permissions_count',
            label: 'Permissions',
            sortable: false,
            render: (_, row) => (
                <div className="flex items-center space-x-1">
                    <Key className="h-3 w-3 text-gray-400" />
                    <span className="text-sm">
                        {row.permissions?.length || 0} permissions
                    </span>
                </div>
            ),
        },
        {
            key: 'users_count',
            label: 'Users',
            sortable: false,
            render: (_, row) => (
                <div className="flex items-center space-x-1">
                    <Users className="h-3 w-3 text-gray-400" />
                    <span className="text-sm">
                        {row.users?.length || 0} users
                    </span>
                </div>
            ),
        },
        {
            key: 'created_at',
            label: 'Created',
            sortable: true,
            render: (value) =>
                new Date(value).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                }),
            className: 'text-muted-foreground',
        },
        {
            key: 'actions' as keyof Role,
            label: 'Actions',
            render: (_, row) => (
                <div className="flex items-center space-x-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(route('roles.show', row.id));
                        }}
                        title="View role"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(route('roles.edit', row.id));
                        }}
                        title="Edit role"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            if (confirm(`Are you sure you want to delete role "${row.name}"?`)) {
                                router.delete(route('roles.destroy', row.id), {
                                    preserveScroll: true,
                                });
                            }
                        }}
                        title="Delete role"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-red-50 hover:text-red-600"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    const handleRowClick = (role: Role) => {
        router.visit(route('roles.show', role.id));
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles" />

            <div className="space-y-6">
                <PageHeader
                    title="Roles"
                    description="Manage user roles and their permissions."
                    actions={[
                        {
                            label: 'Create Role',
                            icon: <Plus className="mr-2 h-4 w-4" />,
                            href: route('roles.create'),
                        },
                    ]}
                    stats={[
                        {
                            label: 'Total Roles',
                            value: stats.total,
                        },
                        {
                            label: 'With Users',
                            value: stats.with_users,
                        },
                        {
                            label: 'With Permissions',
                            value: stats.with_permissions,
                        },
                        {
                            label: 'Empty Roles',
                            value: stats.empty,
                        },
                    ]}
                />

                {/* Data Table */}
                <ServerSideDataTable
                    data={roles}
                    columns={columns}
                    queryParams={queryParams}
                    filterOptions={{}}
                    filters={[]}
                    searchPlaceholder="Search roles..."
                    exportable={false}
                    onRowClick={handleRowClick}
                    emptyMessage="No roles found. Create your first role to get started."
                    showSerialColumn
                />
            </div>
        </AppSidebarLayout>
    );
}
