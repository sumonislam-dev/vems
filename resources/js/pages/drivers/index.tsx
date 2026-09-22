import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { formatDate } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, ColumnFilter, DataTableColumn } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { AlertTriangle, Edit, Eye, Plus, Trash2, User, Mail, Car, CheckCircle } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';

type AssignableVehicle = {
    id: number;
    registration_number: string;
    brand: string;
    model: string;
    current_driver_id: number | null;
    current_driver_name: string | null;
};

const checkPermission = (permission: string, permissions: string[] = []): boolean => permissions.includes(permission);

interface User {
    id: number;
    name: string;
    email: string;
    username: string;
    user_type: string;
    department: { name: string } | null;
    status: string;
    area: string | null;
    roles: Array<{ name: string }>;
    vehicle: { id: number; registration_number: string; brand: string; model: string; vehicle_type: string } | null;
    vendor: { id: number; name: string; status: string } | null;
    is_driver: boolean;
    driver_status: string | null;
    blood_group: string | null;
    phone: string | null;
    driving_license_no: string | null;
    license_class: string | null;
    license_expiry_date: string | null;
    license_status: 'not_provided' | 'expired' | 'expiring_soon' | 'valid';
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
        user_types?: string[];
        statuses?: string[];
        driver_statuses?: string[];
        roles?: string[];
        departments?: Array<{ id: number; name: string }>;
        blood_groups?: string[];
        vendors?: Array<{ id: number; name: string }>;
    };
    stats: {
        total?: number;
        active?: number;
        available?: number;
        on_trip?: number;
        drivers?: number;
        inactive?: number;
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
    { title: 'Drivers', href: '/drivers' },
];

export default function DriversIndex({
    users,
    filterOptions = {},
    stats = {},
    queryParams = {}
}: UsersPageProps) {
    const pageProps = usePage().props as unknown as { auth?: { permissions?: string[] } };
    const canAssignVehicles = checkPermission('assign-vehicles', pageProps.auth?.permissions ?? []);
    const canEditDrivers = checkPermission('edit-drivers', pageProps.auth?.permissions ?? []);

    const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
    const [bulkStatusOpen, setBulkStatusOpen] = useState(false);
    const [bulkStatusProcessing, setBulkStatusProcessing] = useState(false);
    const [bulkDriverStatus, setBulkDriverStatus] = useState('available');

    // Every server round trip (page/filter/sort change, or a successful bulk
    // status update reloading this page) hands us a fresh `users` object, so
    // any selection from before that trip no longer applies.
    useEffect(() => {
        setSelectedIds([]);
    }, [users]);

    const getDriverRowId = useCallback((row: User) => row.id, []);

    const submitBulkStatus = () => {
        router.post(
            route('drivers.bulk-status'),
            { driver_ids: selectedIds, driver_status: bulkDriverStatus },
            {
                preserveScroll: true,
                onStart: () => setBulkStatusProcessing(true),
                onFinish: () => setBulkStatusProcessing(false),
                onSuccess: () => setBulkStatusOpen(false),
            },
        );
    };

    const [assignVehicleDriver, setAssignVehicleDriver] = useState<User | null>(null);
    const [assignableVehicles, setAssignableVehicles] = useState<AssignableVehicle[]>([]);
    const [loadingVehicles, setLoadingVehicles] = useState(false);

    const {
        data: assignVehicleData,
        setData: setAssignVehicleData,
        post: postAssignVehicle,
        processing: assignVehicleProcessing,
        errors: assignVehicleErrors,
        reset: resetAssignVehicle,
        clearErrors: clearAssignVehicleErrors,
    } = useForm<{ driver_id: string; vehicle_id: string; confirm_reassign: boolean }>({
        driver_id: '',
        vehicle_id: '',
        confirm_reassign: false,
    });

    const openAssignVehicleDialog = useCallback(async (driver: User) => {
        setAssignVehicleData({ driver_id: String(driver.id), vehicle_id: '', confirm_reassign: false });
        clearAssignVehicleErrors();
        setAssignVehicleDriver(driver);

        setLoadingVehicles(true);
        try {
            const response = await axios.get<AssignableVehicle[]>(route('drivers.assignable-vehicles', driver.id));
            setAssignableVehicles(response.data);
        } catch {
            // Non-critical: the select will just show no options if this fails.
        } finally {
            setLoadingVehicles(false);
        }
    }, [setAssignVehicleData, clearAssignVehicleErrors]);

    const closeAssignVehicleDialog = () => {
        setAssignVehicleDriver(null);
        resetAssignVehicle();
        clearAssignVehicleErrors();
    };

    const submitAssignVehicle = (submitEvent: FormEvent<HTMLFormElement>) => {
        submitEvent.preventDefault();
        if (!assignVehicleData.vehicle_id) return;

        postAssignVehicle(route('vehicles.assign-driver', assignVehicleData.vehicle_id), {
            preserveScroll: true,
            onSuccess: () => closeAssignVehicleDialog(),
        });
    };

    const handleRowClick = (user: User) => {
        router.visit(route('drivers.show', user.id));
    };

    // Define table columns
    const columns: DataTableColumn<User>[] = useMemo(() => [
        {
            key: 'id',
            label: 'SL',
            className: 'w-16',
            render: (_value, user) => {
                const index = users.data.findIndex((row) => row.id === user.id);
                const serial = (users.current_page - 1) * users.per_page + index + 1;
                return <span className="text-sm text-muted-foreground">{serial}</span>;
            },
        },
        {
            key: 'name',
            label: 'User',
            sortable: true,
            render: (value, user) => (
                <div className="flex items-center gap-3">
                    <div className="flex-shrink-0 h-8 w-8 bg-gray-100 rounded-full flex items-center justify-center">
                        <User className="h-4 w-4 text-gray-600" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="font-medium text-sm text-gray-900">
                            {value}
                        </div>
                        <div className="flex items-center gap-1 text-xs text-gray-500">
                            <Mail className="h-3 w-3" />
                            {user.email}
                        </div>
                        <div className="text-xs text-gray-400">
                            @{user.username}
                        </div>
                    </div>
                </div>
            ),
        },
        {
            key: 'area',
            label: 'Area',
            sortable: true,
            render: (value) => (
                <span className="text-sm text-muted-foreground">
                    {value || '-'}
                </span>
            ),
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

                return (
                    <Badge variant={variants[value as keyof typeof variants]}>
                        {value}
                    </Badge>
                );
            },
        },
        {
            key: 'vehicle',
            label: 'Vehicle',
            sortable: false,
            render: (value) =>
                value ? (
                    <div className="flex flex-col gap-1">
                        <span className="flex items-center gap-1 font-mono text-sm font-medium">
                            <Car className="h-3 w-3 text-muted-foreground" />
                            {value.registration_number}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {value.brand} {value.model}
                        </span>
                        {value.vehicle_type && (
                            <Badge variant="secondary" className="w-fit text-xs capitalize">
                                {value.vehicle_type.replace('_', ' ')}
                            </Badge>
                        )}
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">Unassigned</span>
                ),
        },
        {
            key: 'vendor',
            label: 'Vendor',
            sortable: false,
            filterable: true,
            render: (value) =>
                value ? (
                    <div className="flex flex-col">
                        <span className="text-sm font-medium">{value.name}</span>
                        <Badge variant={value.status === 'active' ? 'default' : 'secondary'} className="w-fit text-xs">
                            {value.status}
                        </Badge>
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">In-house</span>
                ),
        },
        {
            key: 'license_status' as keyof User,
            label: 'License',
            sortable: false,
            render: (_, row) => {
                if (!row.driving_license_no) {
                    return <span className="text-sm text-muted-foreground">-</span>;
                }

                const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
                    valid: 'default',
                    expiring_soon: 'outline',
                    expired: 'destructive',
                    not_provided: 'secondary',
                };

                const labels: Record<string, string> = {
                    valid: 'Valid',
                    expiring_soon: 'Expiring Soon',
                    expired: 'Expired',
                    not_provided: 'No Expiry Set',
                };

                return (
                    <div className="flex flex-col gap-1">
                        <span className="font-mono text-xs">{row.driving_license_no}</span>
                        <Badge variant={variants[row.license_status]} className="w-fit text-xs gap-1">
                            {row.license_status !== 'valid' && <AlertTriangle className="h-3 w-3" />}
                            {labels[row.license_status]}
                        </Badge>
                    </div>
                );
            },
        },
        {
            key: 'blood_group',
            label: 'Blood Group',
            sortable: true,
            filterable: true,
            render: (value) => (
                <span className="font-mono text-sm bg-red-50 text-red-700 px-2 py-1 rounded">
                    {value || 'N/A'}
                </span>
            ),
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
                            router.visit(route('drivers.show', row.id));
                        }}
                        title="View driver"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(route('drivers.edit', row.id));
                        }}
                        title="Edit driver"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    {canAssignVehicles && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                openAssignVehicleDialog(row);
                            }}
                            title="Assign vehicle"
                            className="cursor-pointer transition-all hover:scale-110 hover:bg-emerald-50 hover:text-emerald-600"
                        >
                            <Car className="h-4 w-4" />
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            if (confirm(`Are you sure you want to delete ${row.name}?`)) {
                                router.delete(route('drivers.destroy', row.id), {
                                    preserveScroll: true,
                                });
                            }
                        }}
                        title="Delete driver"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-red-50 hover:text-red-600"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ], [canAssignVehicles, openAssignVehicleDialog, users.data, users.current_page, users.per_page]);

    // Define filters
    const filters: ColumnFilter[] = useMemo(() => [
        {
            key: 'user_type',
            label: 'User Type',
            type: 'multiselect',
            options: (filterOptions.user_types || []).map((type) => ({
                label: type.charAt(0).toUpperCase() + type.slice(1).replace('_', ' '),
                value: type,
            })),
        },
        {
            key: 'driver_status',
            label: 'Driver Status',
            type: 'multiselect',
            options: (filterOptions.driver_statuses || []).map((status) => ({
                label: status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' '),
                value: status,
            })),
        },
        {
            key: 'status',
            label: 'Status',
            type: 'multiselect',
            options: (filterOptions.statuses || []).map((status) => ({
                label: status.charAt(0).toUpperCase() + status.slice(1),
                value: status,
            })),
        },
        {
            key: 'department_id',
            label: 'Department',
            type: 'multiselect',
            options: (filterOptions.departments || []).map((dept) => ({
                label: dept.name,
                value: dept.id.toString(),
            })),
        },
        {
            key: 'roles',
            label: 'Roles',
            type: 'multiselect',
            options: (filterOptions.roles || []).map((role) => ({
                label: role.charAt(0).toUpperCase() + role.slice(1),
                value: role,
            })),
        },
        {
            key: 'blood_group',
            label: 'Blood Group',
            type: 'multiselect',
            options: (filterOptions.blood_groups || []).map((group) => ({
                label: group,
                value: group,
            })),
        },
        {
            key: 'vendor_id',
            label: 'Vendor',
            type: 'multiselect',
            options: (filterOptions.vendors || []).map((vendor) => ({
                label: vendor.name,
                value: vendor.id.toString(),
            })),
        },
    ], [
        filterOptions.user_types,
        filterOptions.driver_statuses,
        filterOptions.statuses,
        filterOptions.departments,
        filterOptions.roles,
        filterOptions.blood_groups,
        filterOptions.vendors,
    ]);

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Drivers" />

            <div className="space-y-6">
                <PageHeader
                    title="Drivers"
                    description="Manage drivers and transport managers"
                    actions={[
                        {
                            label: 'Add Driver',
                            icon: <Plus className="mr-2 h-4 w-4" />,
                            href: route('drivers.create'),
                        },
                    ]}
                    stats={[
                        {
                            label: 'Total Drivers',
                            value: stats?.total || 0,
                        },
                        {
                            label: 'Active',
                            value: stats?.active || 0,
                        },
                        {
                            label: 'Available',
                            value: stats?.available || 0,
                        },
                        {
                            label: 'On Trip',
                            value: stats?.on_trip || 0,
                        },
                    ]}
                />

                {canEditDrivers && selectedIds.length > 0 && (
                    <div className="flex items-center justify-between rounded-lg border bg-muted/30 p-3">
                        <span className="text-sm font-medium">{selectedIds.length} driver(s) selected</span>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" onClick={() => setSelectedIds([])}>
                                Clear selection
                            </Button>
                            <Button size="sm" onClick={() => setBulkStatusOpen(true)}>
                                Change Driver Status
                            </Button>
                        </div>
                    </div>
                )}

                {/* Data Table */}
                {users ? (
                    <ServerSideDataTable
                        data={users}
                        columns={columns}
                        queryParams={queryParams}
                        filterOptions={filterOptions}
                        filters={filters}
                        searchPlaceholder="Search drivers by name, email, license number, or department..."
                        exportable={true}
                        exportUrl="/drivers-export"
                        onRowClick={handleRowClick}
                        emptyMessage="No drivers found. Add your first driver to get started."
                        selectable={canEditDrivers ? () => true : undefined}
                        selectedIds={selectedIds}
                        onSelectionChange={canEditDrivers ? setSelectedIds : undefined}
                        getRowId={canEditDrivers ? getDriverRowId : undefined}
                    />
                ) : (
                    <div className="text-center py-8">Loading drivers...</div>
                )}
            </div>

            <Dialog open={bulkStatusOpen} onOpenChange={(open) => !open && setBulkStatusOpen(false)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Change status for {selectedIds.length} driver(s)</DialogTitle>
                        <DialogDescription>This sets the driver status for every selected driver.</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="bulk_driver_status">Driver Status</Label>
                        <Select value={bulkDriverStatus} onValueChange={setBulkDriverStatus}>
                            <SelectTrigger id="bulk_driver_status" className="w-full">
                                <SelectValue placeholder="Select a status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="available">Available</SelectItem>
                                <SelectItem value="on_trip">On Trip</SelectItem>
                                <SelectItem value="on_leave">On Leave</SelectItem>
                                <SelectItem value="inactive">Inactive</SelectItem>
                                <SelectItem value="suspended">Suspended</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setBulkStatusOpen(false)} disabled={bulkStatusProcessing}>
                            Cancel
                        </Button>
                        <Button type="button" onClick={submitBulkStatus} disabled={bulkStatusProcessing}>
                            <CheckCircle className="mr-2 h-4 w-4" />
                            {bulkStatusProcessing ? 'Updating...' : `Update ${selectedIds.length} Driver(s)`}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={assignVehicleDriver !== null} onOpenChange={(open) => !open && closeAssignVehicleDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Assign Vehicle</DialogTitle>
                        <DialogDescription>Choose a vehicle to assign to {assignVehicleDriver?.name}.</DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitAssignVehicle}>
                        <div className="space-y-2">
                            <Label htmlFor="assign_vehicle_id">Vehicle</Label>
                            <Select
                                value={assignVehicleData.vehicle_id}
                                onValueChange={(value) => setAssignVehicleData('vehicle_id', value)}
                            >
                                <SelectTrigger id="assign_vehicle_id" className="w-full">
                                    <SelectValue placeholder={loadingVehicles ? 'Loading vehicles...' : 'Select a vehicle'} />
                                </SelectTrigger>
                                <SelectContent>
                                    {assignableVehicles.map((vehicle) => (
                                        <SelectItem key={vehicle.id} value={String(vehicle.id)}>
                                            {vehicle.registration_number} - {vehicle.brand} {vehicle.model}
                                            {vehicle.current_driver_id && vehicle.current_driver_id !== assignVehicleDriver?.id
                                                ? ` (currently: ${vehicle.current_driver_name})`
                                                : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={assignVehicleErrors.vehicle_id ?? assignVehicleErrors.driver_id} />
                        </div>

                        {assignVehicleErrors.confirm_reassign && (
                            <div className="space-y-2 rounded-md border border-amber-300 bg-amber-50 p-3">
                                <p className="text-sm text-amber-800">{assignVehicleErrors.confirm_reassign}</p>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="confirm_reassign_vehicle"
                                        checked={assignVehicleData.confirm_reassign}
                                        onCheckedChange={(checked) => setAssignVehicleData('confirm_reassign', checked === true)}
                                    />
                                    <Label htmlFor="confirm_reassign_vehicle" className="text-sm font-normal">
                                        Confirm reassign
                                    </Label>
                                </div>
                            </div>
                        )}

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeAssignVehicleDialog} disabled={assignVehicleProcessing}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={assignVehicleProcessing || !assignVehicleData.vehicle_id}>
                                {assignVehicleProcessing ? 'Saving...' : 'Save'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppSidebarLayout>
    );
}
