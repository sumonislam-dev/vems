import React from 'react';
import { Head, router } from '@inertiajs/react';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { PageHeader } from '@/base-components/page-header';
import { ServerSideDataTable } from '@/base-components/base-data-table';
import { DataTableColumn, ColumnFilter } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Eye, Edit, Trash2, Plus } from 'lucide-react';

interface Department {
    id: number;
    name: string;
    code: string;
    description: string;
    location: string;
    phone: string;
    email: string;
    is_active: boolean;
    status: string;
    users_count: number;
    head: {
        id: number;
        name: string;
        email: string;
    } | null;
    created_at: string;
}

interface Props {
    departments: {
        data: Department[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    filterOptions: {
        statuses: string[];
    };
    queryParams: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        filters?: Record<string, string | string[]>;
        per_page?: number;
    };
}

export default function DepartmentsIndex({ departments, filterOptions, queryParams }: Props) {
    const columns: DataTableColumn<Department>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (value, department) => (
                <div>
                    <div className="font-medium">{value}</div>
                    {department.code && (
                        <div className="text-sm text-muted-foreground">{department.code}</div>
                    )}
                </div>
            ),
        },
        {
            key: 'location',
            label: 'Location',
            sortable: true,
            render: (value) => value || 'N/A',
        },
        {
            key: 'head' as keyof Department,
            label: 'Department Head',
            render: (_, department) => (
                department.head ? (
                    <div>
                        <div className="font-medium">{department.head.name}</div>
                        <div className="text-sm text-muted-foreground">{department.head.email}</div>
                    </div>
                ) : (
                    <span className="text-muted-foreground">No head assigned</span>
                )
            ),
        },
        {
            key: 'users_count' as keyof Department,
            label: 'Users',
            render: (_, department) => (
                <Badge variant="secondary">
                    {department.users_count} user{department.users_count !== 1 ? 's' : ''}
                </Badge>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            filterable: true,
            render: (value) => (
                <Badge variant={value === 'active' ? 'default' : 'secondary'}>
                    {value}
                </Badge>
            ),
        },
        {
            key: 'created_at',
            label: 'Created',
            sortable: true,
            render: (value) => new Date(value).toLocaleDateString(),
        },
        {
            key: 'actions' as keyof Department,
            label: 'Actions',
            render: (_, department) => (
                <div className="flex items-center space-x-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(`/departments/${department.id}`);
                        }}
                        title="View department"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(`/departments/${department.id}/edit`);
                        }}
                        title="Edit department"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            if (confirm('Are you sure you want to delete this department?')) {
                                router.delete(`/departments/${department.id}`);
                            }
                        }}
                        title="Delete department"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-red-50 hover:text-red-600"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    const filters: ColumnFilter[] = [
        {
            key: 'status',
            label: 'Status',
            type: 'multiselect',
            options: filterOptions.statuses.map(status => ({
                label: status.charAt(0).toUpperCase() + status.slice(1),
                value: status,
            })),
        },
    ];

    return (
        <AppSidebarLayout>
            <Head title="Departments" />

            <div className="space-y-6">
                <PageHeader
                    title="Departments"
                    description="Manage your organization's departments"
                    actions={[
                        {
                            label: 'Add Department',
                            icon: <Plus className="mr-2 h-4 w-4" />,
                            href: '/departments/create',
                        },
                    ]}
                />

                <ServerSideDataTable
                    data={departments}
                    columns={columns}
                    queryParams={queryParams}
                    filterOptions={filterOptions}
                    filters={filters}
                    searchPlaceholder="Search departments..."
                    showSerialColumn
                />
            </div>
        </AppSidebarLayout>
    );
}
