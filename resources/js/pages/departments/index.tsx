import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { PageHeader } from '@/base-components/page-header';
import { ServerSideDataTable } from '@/base-components/base-data-table';
import { DataTableColumn, ColumnFilter } from '@/types';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Eye, Edit, Trash2, Plus, Upload } from 'lucide-react';

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
    const [importOpen, setImportOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importProcessing, setImportProcessing] = useState(false);

    const submitImport = () => {
        if (!importFile) {
            return;
        }

        const formData = new FormData();
        formData.append('file', importFile);

        router.post(route('departments.import'), formData, {
            forceFormData: true,
            preserveScroll: true,
            onStart: () => setImportProcessing(true),
            onFinish: () => setImportProcessing(false),
            onSuccess: () => {
                setImportOpen(false);
                setImportFile(null);
            },
        });
    };

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
                            label: 'Export Departments',
                            variant: 'outline',
                            href: route('departments.export'),
                        },
                        {
                            label: 'Import Departments',
                            variant: 'outline',
                            icon: <Upload className="mr-2 h-4 w-4" />,
                            onClick: () => setImportOpen(true),
                        },
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

            <Dialog open={importOpen} onOpenChange={(open) => !open && setImportOpen(false)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Import Departments</DialogTitle>
                        <DialogDescription>
                            Upload a CSV or Excel file with columns: name, code, description, location, phone,
                            email, is_active, attendance_mode. Only name and code are required.
                        </DialogDescription>
                    </DialogHeader>

                    <div>
                        <label htmlFor="departments_import_file" className="block text-sm font-medium mb-1">
                            File
                        </label>
                        <input
                            id="departments_import_file"
                            name="file"
                            type="file"
                            accept=".csv,.txt,.xlsx,.xls"
                            className="block w-full text-sm border border-input rounded-md file:mr-2 file:py-1.5 file:px-3 file:border-0 file:text-sm file:font-medium file:bg-secondary file:text-secondary-foreground hover:file:bg-secondary/80"
                            onChange={(e) => setImportFile(e.target.files?.[0] ?? null)}
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setImportOpen(false)} disabled={importProcessing}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={submitImport} disabled={!importFile || importProcessing}>
                            {importProcessing ? 'Importing…' : 'Import'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppSidebarLayout>
    );
}
