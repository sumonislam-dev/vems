import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, DataTableColumn, Permission } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Edit, Eye, Plus, Key, Shield, Users, Trash2 } from 'lucide-react';

interface PaginatedPermissions {
    data: Permission[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface PermissionsPageProps {
    permissions: PaginatedPermissions;
    stats: {
        total: number;
        with_roles: number;
        with_users: number;
        unused: number;
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
    { title: 'Permissions', href: '/permissions' },
];

export default function PermissionsIndex({ permissions, stats, queryParams }: PermissionsPageProps) {
    // Define table columns
    const columns: DataTableColumn<Permission>[] = [
        {
            key: 'name',
            label: 'Permission Name',
            sortable: true,
            render: (value) => (
                <div className="flex items-center space-x-2">
                    <Key className="h-4 w-4 text-amber-500" />
                    <span className="font-medium">{value}</span>
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
            key: 'roles_count',
            label: 'Roles',
            sortable: false,
            render: (_, row) => (
                <div className="flex items-center space-x-1">
                    <Shield className="h-3 w-3 text-gray-400" />
                    <span className="text-sm">
                        {row.roles?.length || 0} roles
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
            render: (value) => formatDate(value as string),
            className: 'text-muted-foreground',
        },
        {
            key: 'actions' as keyof Permission,
            label: 'Actions',
            render: (_, row) => (
                <div className="flex items-center space-x-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(route('permissions.show', row.id));
                        }}
                        title="View permission"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(route('permissions.edit', row.id));
                        }}
                        title="Edit permission"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            if (confirm(`Are you sure you want to delete permission "${row.name}"?`)) {
                                router.delete(route('permissions.destroy', row.id), {
                                    preserveScroll: true,
                                });
                            }
                        }}
                        title="Delete permission"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-red-50 hover:text-red-600"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    const handleRowClick = (permission: Permission) => {
        router.visit(route('permissions.show', permission.id));
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Permissions" />

            <div className="space-y-6">
                <PageHeader
                    title="Permissions"
                    description="Manage system permissions and access control."
                    actions={[
                        {
                            label: 'Create Permission',
                            icon: <Plus className="mr-2 h-4 w-4" />,
                            href: route('permissions.create'),
                        },
                    ]}
                    stats={[
                        {
                            label: 'Total Permissions',
                            value: stats.total,
                        },
                        {
                            label: 'With Roles',
                            value: stats.with_roles,
                        },
                        {
                            label: 'With Users',
                            value: stats.with_users,
                        },
                        {
                            label: 'Unused',
                            value: stats.unused,
                        },
                    ]}
                />

                {/* Data Table */}
                <ServerSideDataTable
                    data={permissions}
                    columns={columns}
                    queryParams={queryParams}
                    filterOptions={{}}
                    filters={[]}
                    searchPlaceholder="Search permissions..."
                    exportable={false}
                    onRowClick={handleRowClick}
                    emptyMessage="No permissions found. Create your first permission to get started."
                    showSerialColumn
                />
            </div>
        </AppSidebarLayout>
    );
}
