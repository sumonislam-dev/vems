import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { confirmDelete } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useCan } from '@/lib/permissions';
import { formatDate } from '@/lib/utils';
import { BreadcrumbItem, ColumnFilter, DataTableColumn, type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Download, Edit, Eye, Mail, Plus, Shield, Trash2, Upload, User, UserCheck, UserX } from 'lucide-react';
import { useMemo, useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    username: string;
    user_type: string;
    department: { name: string } | null;
    zone: { name: string } | null;
    access_zones: Array<{ id: number; name: string }>;
    status: string;
    area: string | null;
    roles: Array<{ name: string }>;
    is_driver: boolean;
    driver_status: string | null;
    blood_group: string | null;
    phone: string | null;
    created_at: string;
}

interface PaginatedUsers {
    data: User[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface UsersPageProps {
    users: PaginatedUsers;
    filterOptions: {
        user_types: string[];
        statuses: string[];
        roles: string[];
        departments: Array<{ id: number; name: string }>;
        zones: Array<{ id: number; name: string }>;
        blood_groups: string[];
    };
    stats: {
        total: number;
        active: number;
        drivers: number;
        inactive: number;
    };
    queryParams: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        filters?: Record<string, string | string[]>;
        per_page?: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/users' },
];

export default function UsersIndex({ users, filterOptions, stats, queryParams }: UsersPageProps) {
    const can = useCan();
    const canEdit = can('edit-users');
    const canDelete = can('delete-users');
    // Your own account can't be deactivated or deleted (the server refuses too)
    const currentUserId = usePage<SharedData>().props.auth.user.id;

    const handleRowClick = (user: User) => {
        router.visit(route('users.show', user.id));
    };

    const [importOpen, setImportOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importProcessing, setImportProcessing] = useState(false);

    const [statusDialogUser, setStatusDialogUser] = useState<User | null>(null);
    const [statusDialogAction, setStatusDialogAction] = useState<'activate' | 'deactivate' | null>(null);
    const [groupChoice, setGroupChoice] = useState(true);
    const [statusProcessing, setStatusProcessing] = useState(false);

    const openStatusDialog = (user: User, action: 'activate' | 'deactivate') => {
        setStatusDialogUser(user);
        setStatusDialogAction(action);
        setGroupChoice(true);
    };

    const closeStatusDialog = () => {
        setStatusDialogUser(null);
        setStatusDialogAction(null);
    };

    const submitStatusChange = () => {
        if (!statusDialogUser || !statusDialogAction) {
            return;
        }

        const routeName = statusDialogAction === 'activate' ? 'users.activate' : 'users.deactivate';
        const payload = statusDialogAction === 'activate' ? { restore_previous_groups: groupChoice } : { remove_from_groups: groupChoice };

        router.post(route(routeName, statusDialogUser.id), payload, {
            preserveScroll: true,
            onStart: () => setStatusProcessing(true),
            onFinish: () => setStatusProcessing(false),
            onSuccess: () => closeStatusDialog(),
        });
    };

    const submitImport = () => {
        if (!importFile) {
            return;
        }

        const formData = new FormData();
        formData.append('file', importFile);

        router.post(route('users.import'), formData, {
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

    // Define table columns
    const columns: DataTableColumn<User>[] = useMemo(
        () => [
            {
                key: 'name',
                label: 'User',
                sortable: true,
                render: (value, user) => (
                    <div className="flex items-center gap-3">
                        <div className="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-gray-100">
                            <User className="h-4 w-4 text-gray-600" />
                        </div>
                        <div className="min-w-0 flex-1">
                            <div className="text-sm font-medium text-gray-900">{value}</div>
                            <div className="flex items-center gap-1 text-xs text-gray-500">
                                <Mail className="h-3 w-3" />
                                {user.email}
                            </div>
                            <div className="text-xs text-gray-400">@{user.username}</div>
                        </div>
                    </div>
                ),
            },
            {
                key: 'department',
                label: 'Department',
                sortable: true,
                filterable: true,
                render: (value) => <span className="text-sm">{value?.name || 'No Department'}</span>,
            },
            {
                key: 'zone',
                label: 'Zone',
                filterable: true,
                render: (value, row) => {
                    const zoneNames = row.access_zones?.length ? row.access_zones.map((z) => z.name).join(', ') : value?.name;
                    return <span className="text-sm">{zoneNames || 'No Zone'}</span>;
                },
            },
            {
                key: 'area',
                label: 'Area',
                sortable: true,
                render: (value) => <span className="text-sm text-muted-foreground">{value || '-'}</span>,
            },
            {
                key: 'status',
                label: 'Status',
                sortable: true,
                filterable: true,
                render: (value) => {
                    const variants = {
                        active: 'default',
                        inactive: 'secondary',
                        suspended: 'destructive',
                    } as const;

                    return <Badge variant={variants[value as keyof typeof variants]}>{value}</Badge>;
                },
            },
            {
                key: 'roles',
                label: 'Roles',
                sortable: false,
                filterable: true,
                render: (value) => (
                    <div className="flex flex-wrap gap-1">
                        {value?.map((role: { name: string }) => (
                            <Badge key={role.name} variant="outline" className="text-xs">
                                <Shield className="mr-1 h-3 w-3" />
                                {role.name}
                            </Badge>
                        ))}
                    </div>
                ),
            },
            {
                key: 'blood_group',
                label: 'Blood Group',
                sortable: true,
                filterable: true,
                render: (value) => <span className="rounded bg-red-50 px-2 py-1 font-mono text-sm text-red-700">{value || 'N/A'}</span>,
            },
            {
                key: 'created_at',
                label: 'Joined',
                sortable: true,
                render: (value) => formatDate(value as string),
                className: 'text-muted-foreground',
            },
            {
                key: 'actions' as keyof User,
                label: 'Actions',
                render: (_, row) => (
                    <div className="flex items-center space-x-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                router.visit(route('users.show', row.id));
                            }}
                            title="View user"
                            className="cursor-pointer transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                        >
                            <Eye className="h-4 w-4" />
                        </Button>
                        {canEdit && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    router.visit(route('users.edit', row.id));
                                }}
                                title="Edit user"
                                className="cursor-pointer transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                            >
                                <Edit className="h-4 w-4" />
                            </Button>
                        )}
                        {canEdit &&
                            row.id !== currentUserId &&
                            (row.status === 'active' ? (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        openStatusDialog(row, 'deactivate');
                                    }}
                                    title="Deactivate user"
                                    className="cursor-pointer transition-all hover:scale-110 hover:bg-amber-50 hover:text-amber-600"
                                >
                                    <UserX className="h-4 w-4" />
                                </Button>
                            ) : (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        openStatusDialog(row, 'activate');
                                    }}
                                    title="Activate user"
                                    className="cursor-pointer transition-all hover:scale-110 hover:bg-green-50 hover:text-green-600"
                                >
                                    <UserCheck className="h-4 w-4" />
                                </Button>
                            ))}
                        {canDelete && row.id !== currentUserId && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={async (e) => {
                                    e.stopPropagation();
                                    if (await confirmDelete(`Are you sure you want to delete ${row.name}?`)) {
                                        router.delete(route('users.destroy', row.id), {
                                            preserveScroll: true,
                                        });
                                    }
                                }}
                                title="Delete user"
                                className="cursor-pointer transition-all hover:scale-110 hover:bg-red-50 hover:text-red-600"
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        )}
                    </div>
                ),
            },
        ],
        [canEdit, canDelete, currentUserId],
    );

    // Define filters
    const filters: ColumnFilter[] = useMemo(
        () => [
            {
                key: 'status',
                label: 'Status',
                type: 'multiselect',
                options: filterOptions.statuses.map((status) => ({
                    label: status.charAt(0).toUpperCase() + status.slice(1),
                    value: status,
                })),
            },
            {
                key: 'department_id',
                label: 'Department',
                type: 'multiselect',
                options: filterOptions.departments.map((dept) => ({
                    label: dept.name,
                    value: dept.id.toString(),
                })),
            },
            {
                key: 'zone_id',
                label: 'Zone',
                type: 'multiselect',
                options: filterOptions.zones.map((zone) => ({
                    label: zone.name,
                    value: zone.id.toString(),
                })),
            },
            {
                key: 'roles',
                label: 'Roles',
                type: 'multiselect',
                options: filterOptions.roles.map((role) => ({
                    label: role.charAt(0).toUpperCase() + role.slice(1),
                    value: role,
                })),
            },
            {
                key: 'blood_group',
                label: 'Blood Group',
                type: 'multiselect',
                options: filterOptions.blood_groups.map((group) => ({
                    label: group,
                    value: group,
                })),
            },
        ],
        [filterOptions.statuses, filterOptions.departments, filterOptions.zones, filterOptions.roles, filterOptions.blood_groups],
    );

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />

            <div className="space-y-6">
                <PageHeader
                    title="Users"
                    description="Manage users and permissions"
                    actions={[
                        {
                            label: 'Import Users',
                            variant: 'outline',
                            icon: <Upload className="mr-2 h-4 w-4" />,
                            onClick: () => setImportOpen(true),
                            permission: 'import-users',
                        },
                        {
                            label: 'Add User',
                            icon: <Plus className="mr-2 h-4 w-4" />,
                            href: route('users.create'),
                            permission: 'create-users',
                        },
                    ]}
                    stats={[
                        {
                            label: 'Total Users',
                            value: stats.total,
                        },
                        {
                            label: 'Active Users',
                            value: stats.active,
                        },
                        {
                            label: 'Drivers',
                            value: stats.drivers,
                        },
                        {
                            label: 'Inactive',
                            value: stats.inactive,
                        },
                    ]}
                />

                {/* Data Table */}
                <ServerSideDataTable
                    data={users}
                    columns={columns}
                    queryParams={queryParams}
                    filterOptions={filterOptions}
                    filters={filters}
                    searchPlaceholder="Search users by name, email, username, or department..."
                    exportable={can('export-users')}
                    exportUrl={route('users.export')}
                    onRowClick={handleRowClick}
                    emptyMessage="No users found. Add your first user to get started."
                    showSerialColumn
                />
            </div>

            <Dialog open={importOpen} onOpenChange={(open) => !open && setImportOpen(false)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Import Users</DialogTitle>
                        <DialogDescription>
                            Fill in the template and upload it. Name and zone code are required (a blank username is made from the email or name); the
                            template lists the valid department, zone and role codes. Imported users set their password via "Forgot Password".
                        </DialogDescription>
                    </DialogHeader>

                    <a
                        href={route('users.import-template')}
                        className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                        download
                    >
                        <Download className="h-4 w-4" />
                        Download template
                    </a>

                    <div>
                        <label htmlFor="users_import_file" className="mb-1 block text-sm font-medium">
                            File
                        </label>
                        <input
                            id="users_import_file"
                            name="file"
                            type="file"
                            accept=".csv,.txt,.xlsx,.xls"
                            className="block w-full rounded-md border border-input text-sm file:mr-2 file:border-0 file:bg-secondary file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-secondary-foreground hover:file:bg-secondary/80"
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

            <Dialog open={statusDialogUser !== null} onOpenChange={(open) => !open && closeStatusDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {statusDialogAction === 'activate' ? 'Activate' : 'Deactivate'} {statusDialogUser?.name}?
                        </DialogTitle>
                        <DialogDescription>
                            {statusDialogAction === 'activate' ? 'This will set the user back to active.' : 'This will set the user to inactive.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex items-center space-x-2">
                        <Checkbox
                            id="status-dialog-group-choice"
                            checked={groupChoice}
                            onCheckedChange={(checked) => setGroupChoice(checked as boolean)}
                        />
                        <label htmlFor="status-dialog-group-choice" className="text-sm leading-none font-medium">
                            {statusDialogAction === 'activate' ? 'Add back to the groups they were removed from' : 'Remove from all groups'}
                        </label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={closeStatusDialog} disabled={statusProcessing}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={submitStatusChange} disabled={statusProcessing}>
                            {statusProcessing ? 'Saving…' : statusDialogAction === 'activate' ? 'Activate' : 'Deactivate'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppSidebarLayout>
    );
}
