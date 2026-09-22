import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { PageHeader } from '@/base-components/page-header';
import { ServerSideDataTable } from '@/base-components/base-data-table';
import { DataTableColumn, ColumnFilter } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { MoreHorizontal, Eye, Edit, Trash2, Plus } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

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
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 cursor-pointer p-0 transition-all hover:scale-110">
                            <span className="sr-only">Open menu</span>
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link href={`/departments/${department.id}`}>
                                <Eye className="mr-2 h-4 w-4" />
                                View
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href={`/departments/${department.id}/edit`}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            onClick={() => {
                                if (confirm('Are you sure you want to delete this department?')) {
                                    router.delete(`/departments/${department.id}`);
                                }
                            }}
                            className="text-destructive"
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
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
