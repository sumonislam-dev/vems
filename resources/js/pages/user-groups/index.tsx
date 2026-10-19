import { PageHeader } from '@/base-components/page-header';
import { confirmDelete } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useCan } from '@/lib/permissions';
import type { BreadcrumbItem, PaginatedData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Search, Trash2, Users } from 'lucide-react';
import { useState } from 'react';

interface UserGroup {
    id: number;
    name: string;
    description: string;
    status: 'active' | 'inactive';
    total_members: number;
    creator: {
        id: number;
        name: string;
    } | null;
    created_at: string;
}

interface UserGroupsIndexProps {
    groups: PaginatedData<UserGroup>;
    filters: {
        search?: string;
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
    { title: 'User Groups', href: '/user-groups' },
];

export default function UserGroupsIndex({ groups, filters, stats }: UserGroupsIndexProps) {
    const can = useCan();
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    const handleSearch = (value: string) => {
        setSearchTerm(value);
        router.get(
            route('user-groups.index'),
            { search: value, status: filters.status },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const handleFilterChange = (filterName: string, value: string) => {
        router.get(
            route('user-groups.index'),
            {
                ...filters,
                [filterName]: value,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const handleDelete = async (id: number) => {
        if (await confirmDelete('Are you sure you want to delete this group?')) {
            router.delete(route('user-groups.destroy', id), {
                preserveScroll: true,
            });
        }
    };

    const columns = [
        {
            key: 'name',
            label: 'Group Name',
            sortable: true,
            render: (group: UserGroup) => (
                <div className="text-sm font-medium">
                    <Link href={route('user-groups.show', group.id)} className="text-primary hover:underline">
                        {group.name}
                    </Link>
                </div>
            ),
        },
        {
            key: 'description',
            label: 'Description',
            render: (group: UserGroup) => <div className="max-w-md truncate text-xs text-muted-foreground">{group.description || '—'}</div>,
        },
        {
            key: 'total_members',
            label: 'Members',
            sortable: true,
            render: (group: UserGroup) => (
                <div className="flex items-center gap-1">
                    <Users className="h-3 w-3 text-muted-foreground" />
                    <span className="text-xs font-medium">{group.total_members}</span>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (group: UserGroup) => (
                <Badge variant={group.status === 'active' ? 'default' : 'secondary'} className="h-5 text-[10px]">
                    {group.status}
                </Badge>
            ),
        },
        {
            key: 'creator',
            label: 'Created By',
            render: (group: UserGroup) => <div className="text-xs text-muted-foreground">{group.creator?.name || '—'}</div>,
        },
        {
            key: 'created_at',
            label: 'Created',
            sortable: true,
            render: (group: UserGroup) => <div className="text-xs text-muted-foreground">{group.created_at}</div>,
        },
        {
            key: 'actions',
            label: 'Actions',
            render: (group: UserGroup) => (
                <div className="flex items-center gap-1">
                    <Link href={route('user-groups.show', group.id)}>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 cursor-pointer p-0 transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                        >
                            <Eye className="h-3.5 w-3.5" />
                        </Button>
                    </Link>
                    {can('edit-user-groups') && (
                        <Link href={route('user-groups.edit', group.id)}>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-7 w-7 cursor-pointer p-0 transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                            >
                                <Pencil className="h-3.5 w-3.5" />
                            </Button>
                        </Link>
                    )}
                    {can('delete-user-groups') && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 w-7 cursor-pointer p-0 transition-all hover:scale-110 hover:bg-red-50 hover:text-red-600"
                            onClick={() => handleDelete(group.id)}
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
            <Head title="User Groups" />

            <div className="space-y-4">
                <PageHeader
                    title="User Groups"
                    description=""
                    actions={[
                        {
                            label: 'Create Group',
                            icon: <Plus className="h-4 w-4" />,
                            href: route('user-groups.create'),
                            permission: 'create-user-groups',
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
                                placeholder="Search groups..."
                                value={searchTerm}
                                onChange={(e) => handleSearch(e.target.value)}
                                className="h-8 pl-8 text-sm"
                            />
                        </div>
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
                                {groups.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={columns.length} className="px-4 py-8 text-center text-sm text-muted-foreground">
                                            No groups found
                                        </td>
                                    </tr>
                                ) : (
                                    groups.data.map((group) => (
                                        <tr key={group.id} className="transition-colors hover:bg-muted/50">
                                            {columns.map((col) => (
                                                <td key={col.key} className="px-4 py-2.5">
                                                    {col.render ? col.render(group) : String(group[col.key as keyof UserGroup] || '')}
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
                            Showing {groups.from || 0}-{groups.to || 0} of {groups.total}
                        </div>
                        <div className="flex gap-1.5">
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                                disabled={groups.current_page === 1}
                                onClick={() => router.get(route('user-groups.index'), { ...filters, page: groups.current_page - 1 })}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-7 text-xs"
                                disabled={groups.current_page === groups.last_page}
                                onClick={() => router.get(route('user-groups.index'), { ...filters, page: groups.current_page + 1 })}
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
