import { PageHeader } from '@/base-components/page-header';
import { confirmAction, confirmDelete } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useCan } from '@/lib/permissions';
import type { BreadcrumbItem, PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Lock, Pencil, Plus, Search, Trash2, Unlock } from 'lucide-react';
import { useState } from 'react';

interface Department {
    id: number;
    name: string;
}

interface Logistic {
    id: number;
    name: string;
    description: string | null;
    status: string;
    department: Department | null;
    creator: {
        id: number;
        name: string;
    } | null;
    created_at: string;
}

interface LogisticsPageProps {
    logistics: PaginatedData<Logistic>;
    departments: Department[];
    filters: {
        search?: string;
        department_id?: string;
        status?: string;
    };
    stats: {
        total: number;
        active: number;
        inactive: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Logistics', href: '/logistics' },
];

export default function LogisticsIndex({ logistics, departments, filters, stats }: LogisticsPageProps) {
    const can = useCan();
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    const handleSearch = (value: string) => {
        setSearchTerm(value);
        router.get(route('logistics.index'), { ...filters, search: value }, { preserveState: true });
    };

    const handleFilterChange = (key: string, value: string) => {
        router.get(route('logistics.index'), { ...filters, [key]: value }, { preserveState: true });
    };

    const handleDelete = async (id: number) => {
        if (await confirmDelete('Are you sure you want to delete this logistics?')) {
            router.delete(route('logistics.destroy', id));
        }
    };

    const handleToggleLock = async (id: number) => {
        if (await confirmAction({ title: 'Change the lock on this logistics entry?', confirmLabel: 'Yes, change it' })) {
            router.patch(route('logistics.toggle-lock', id));
        }
    };

    const columns = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
            render: (logistic: Logistic) => (
                <div className="text-sm font-medium">
                    <Link href={route('logistics.show', logistic.id)} className="text-primary hover:underline">
                        {logistic.name}
                    </Link>
                </div>
            ),
        },
        {
            key: 'department',
            label: 'Department',
            render: (logistic: Logistic) => <div className="text-xs text-muted-foreground">{logistic.department?.name || '—'}</div>,
        },
        {
            key: 'description',
            label: 'Description',
            render: (logistic: Logistic) => <div className="max-w-md truncate text-xs text-muted-foreground">{logistic.description || '—'}</div>,
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (logistic: Logistic) => (
                <div className="flex items-center gap-1">
                    {logistic.status === 'active' ? (
                        <Badge variant="default" className="h-5 gap-1 text-[10px]">
                            <Unlock className="h-3 w-3" />
                            Active
                        </Badge>
                    ) : (
                        <Badge variant="destructive" className="h-5 gap-1 text-[10px]">
                            <Lock className="h-3 w-3" />
                            Inactive
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'creator',
            label: 'Created By',
            render: (logistic: Logistic) => <div className="text-xs text-muted-foreground">{logistic.creator?.name || '—'}</div>,
        },
        {
            key: 'created_at',
            label: 'Created',
            sortable: true,
            render: (logistic: Logistic) => <div className="text-xs text-muted-foreground">{logistic.created_at}</div>,
        },
        {
            key: 'actions',
            label: 'Actions',
            render: (logistic: Logistic) => (
                <div className="flex items-center gap-1">
                    {can('edit-logistics') && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 cursor-pointer p-0 transition-all hover:scale-110 hover:bg-amber-50 hover:text-amber-600"
                            onClick={() => handleToggleLock(logistic.id)}
                            title={logistic.status === 'active' ? 'Deactivate' : 'Activate'}
                        >
                            {logistic.status === 'active' ? <Unlock className="h-3.5 w-3.5" /> : <Lock className="h-3.5 w-3.5" />}
                        </Button>
                    )}
                    <Link href={route('logistics.show', logistic.id)}>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 cursor-pointer p-0 transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                        >
                            <Eye className="h-3.5 w-3.5" />
                        </Button>
                    </Link>
                    {can('edit-logistics') && (
                        <Link href={route('logistics.edit', logistic.id)}>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 w-7 cursor-pointer p-0 transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                            >
                                <Pencil className="h-3.5 w-3.5" />
                            </Button>
                        </Link>
                    )}
                    {can('delete-logistics') && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 cursor-pointer p-0 transition-all hover:scale-110 hover:bg-red-50 hover:text-red-600"
                            onClick={() => handleDelete(logistic.id)}
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Logistics" />

            <div className="space-y-4">
                <PageHeader
                    title="Logistics"
                    description=""
                    actions={[
                        {
                            label: 'Create Logistics',
                            icon: <Plus className="h-4 w-4" />,
                            href: route('logistics.create'),
                            permission: 'create-logistics',
                        },
                    ]}
                    stats={[
                        {
                            label: 'Total',
                            value: stats?.total || 0,
                        },
                        {
                            label: 'Active',
                            value: stats?.active || 0,
                        },
                        {
                            label: 'Inactive',
                            value: stats?.inactive || 0,
                        },
                    ]}
                />

                {/* Data Table */}
                <Card>
                    <div className="flex items-center gap-2 border-b p-3">
                        <div className="relative max-w-xs flex-1">
                            <Search className="absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                type="text"
                                placeholder="Search logistics..."
                                value={searchTerm}
                                onChange={(e) => handleSearch(e.target.value)}
                                className="h-8 pl-8 text-sm"
                            />
                        </div>
                        <Select
                            value={filters.department_id || 'all'}
                            onValueChange={(value) => handleFilterChange('department_id', value === 'all' ? '' : value)}
                        >
                            <SelectTrigger className="h-8 w-40 text-sm">
                                <SelectValue placeholder="Department" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all" className="text-sm">
                                    All Departments
                                </SelectItem>
                                {departments.map((dept) => (
                                    <SelectItem key={dept.id} value={dept.id.toString()} className="text-sm">
                                        {dept.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={filters.status || 'all'} onValueChange={(value) => handleFilterChange('status', value === 'all' ? '' : value)}>
                            <SelectTrigger className="h-8 w-32 text-sm">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all" className="text-sm">
                                    All Status
                                </SelectItem>
                                <SelectItem value="active" className="text-sm">
                                    Active
                                </SelectItem>
                                <SelectItem value="inactive" className="text-sm">
                                    Inactive
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-muted/50">
                                <tr>
                                    {columns.map((col) => (
                                        <th key={col.key} className="px-4 py-2 text-left text-xs font-medium text-muted-foreground">
                                            {col.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {logistics.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={columns.length} className="px-4 py-8 text-center text-sm text-muted-foreground">
                                            No logistics found
                                        </td>
                                    </tr>
                                ) : (
                                    logistics.data.map((logistic) => (
                                        <tr key={logistic.id} className="transition-colors hover:bg-muted/50">
                                            {columns.map((col) => (
                                                <td key={col.key} className="px-4 py-2.5">
                                                    {col.render ? col.render(logistic) : String(logistic[col.key as keyof Logistic] || '')}
                                                </td>
                                            ))}
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-between border-t p-3">
                        <div className="text-xs text-muted-foreground">
                            Showing {logistics.from || 0}-{logistics.to || 0} of {logistics.total}
                        </div>
                        <div className="flex gap-1.5">
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                                disabled={logistics.current_page === 1}
                                onClick={() => router.get(route('logistics.index'), { ...filters, page: logistics.current_page - 1 })}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                                disabled={logistics.current_page === logistics.last_page}
                                onClick={() => router.get(route('logistics.index'), { ...filters, page: logistics.current_page + 1 })}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </Card>
            </div>
        </AppSidebarLayout>
    );
}
