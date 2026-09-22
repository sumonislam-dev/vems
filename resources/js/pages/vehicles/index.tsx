import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, DataTableColumn, Vehicle } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle, Edit, Eye, Plus, Trash2, UserCog, XCircle } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useState } from 'react';

type AssignableDriver = {
    id: number
    name: string
    email?: string
    official_phone?: string
}

const checkPermission = (permission: string, permissions: string[] = []): boolean => permissions.includes(permission);

interface PaginatedVehicles {
    data: Vehicle[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface VehiclesPageProps {
    vehicles: PaginatedVehicles;
    filterOptions: {
        brands: string[];
        colors: string[];
        vehicle_types: Array<{ label: string; value: string }>;
        rental_types: Array<{ label: string; value: string }>;
        fuel_types: Array<{ label: string; value: string }>;
        vendors: Array<{ label: string; value: string }>;
        statuses: Array<{ label: string; value: boolean }>;
        conditions: Array<{ label: string; value: string }>;
    };
    stats: {
        total: number;
        active: number;
        brands: number;
        inactive: number;
    };
    queryParams: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        filters?: Record<string, unknown>;
        per_page?: number;
    };
    assignableDrivers?: AssignableDriver[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vehicles', href: '/vehicles' },
];

export default function VehiclesIndex({ vehicles, filterOptions, stats, queryParams, assignableDrivers = [] }: VehiclesPageProps) {
    const pageProps = usePage().props as unknown as { auth?: { permissions?: string[] } };
    const canAssignVehicles = checkPermission('assign-vehicles', pageProps.auth?.permissions ?? []);
    const canEditVehicles = checkPermission('edit-vehicles', pageProps.auth?.permissions ?? []);

    const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
    const [bulkStatusProcessing, setBulkStatusProcessing] = useState(false);

    // Every server round trip (page/filter/sort change, or a successful bulk
    // status update reloading this page) hands us a fresh `vehicles` object,
    // so any selection from before that trip no longer applies.
    useEffect(() => {
        setSelectedIds([]);
    }, [vehicles]);

    const getVehicleRowId = useCallback((row: Vehicle) => row.id, []);

    const submitBulkStatus = (isActive: boolean) => {
        router.post(
            route('vehicles.bulk-status'),
            { vehicle_ids: selectedIds, is_active: isActive },
            {
                preserveScroll: true,
                onStart: () => setBulkStatusProcessing(true),
                onFinish: () => setBulkStatusProcessing(false),
            },
        );
    };

    const [assignDriverVehicle, setAssignDriverVehicle] = useState<Vehicle | null>(null);
    const {
        data: assignDriverData,
        setData: setAssignDriverData,
        post: postAssignDriver,
        processing: assignDriverProcessing,
        errors: assignDriverErrors,
        reset: resetAssignDriver,
        clearErrors: clearAssignDriverErrors,
    } = useForm<{ driver_id: string; confirm_reassign: boolean }>({
        driver_id: 'none',
        confirm_reassign: false,
    });

    const openAssignDriverDialog = (vehicle: Vehicle) => {
        setAssignDriverData({
            driver_id: vehicle.driver_id ? String(vehicle.driver_id) : 'none',
            confirm_reassign: false,
        });
        clearAssignDriverErrors();
        setAssignDriverVehicle(vehicle);
    };

    const closeAssignDriverDialog = () => {
        setAssignDriverVehicle(null);
        resetAssignDriver();
        clearAssignDriverErrors();
    };

    const submitAssignDriver = (submitEvent: FormEvent<HTMLFormElement>) => {
        submitEvent.preventDefault();
        if (!assignDriverVehicle) return;

        postAssignDriver(route('vehicles.assign-driver', assignDriverVehicle.id), {
            preserveScroll: true,
            onSuccess: () => closeAssignDriverDialog(),
        });
    };

    const handleRowClick = (vehicle: Vehicle) => {
        router.visit(route('vehicles.show', vehicle.id));
    };

    // Test manual sorting
    const handleSort = (field: string) => {
        const currentDirection = queryParams?.direction || 'asc';
        const newDirection = queryParams?.sort === field && currentDirection === 'asc' ? 'desc' : 'asc';

        const params: Record<string, string | number | undefined> = {
            sort: field,
            direction: newDirection,
        };

        if (queryParams?.search) params.search = queryParams.search;
        if (queryParams?.per_page) params.per_page = queryParams.per_page;

        router.get(route('vehicles.index'), params, {
            preserveState: true,
            preserveScroll: true,
        });
    };
    const columns: DataTableColumn<Vehicle>[] = [
        {
            key: 'id',
            label: 'SL',
            className: 'w-16',
            render: (_value, vehicle) => {
                const index = vehicles.data.findIndex((row) => row.id === vehicle.id);
                const serial = (vehicles.current_page - 1) * vehicles.per_page + index + 1;
                return <span className="text-sm text-muted-foreground">{serial}</span>;
            },
        },
        {
            key: 'brand',
            label: 'Brand',
            sortable: true,
            render: (value) => (
                <span className="font-medium">{value}</span>
            ),
        },
        {
            key: 'model',
            label: 'Model',
            sortable: true,
            render: (value) => (
                <span className="text-muted-foreground">{value}</span>
            ),
        },
        {
            key: 'vehicle_type',
            label: 'Type',
            sortable: true,
            render: (value) => (
                <Badge variant="secondary" className="capitalize">
                    {typeof value === 'string' ? value.replace('_', ' ') : value}
                </Badge>
            ),
        },
        {
            key: 'color',
            label: 'Color',
            sortable: true,
            render: (value) => (
                <Badge variant="outline" className="capitalize">
                    {value}
                </Badge>
            ),
        },
        {
            key: 'registration_number',
            label: 'Registration',
            sortable: true,
            render: (value) => (
                <span className="font-mono text-sm">{value}</span>
            ),
        },
        {
            key: 'vendor',
            label: 'Vendor',
            sortable: true,
            render: (value) => {
                // Handle both the vendor object and the legacy vendor string
                const vendorName = typeof value === 'object' && value !== null && 'name' in value
                    ? value.name
                    : (typeof value === 'string' ? value : null);

                return (
                    <span className="text-muted-foreground">
                        {vendorName || 'N/A'}
                    </span>
                );
            },
        },
        {
            key: 'driver',
            label: 'Driver',
            sortable: true,
            render: (value, vehicle) => {
                const driver = vehicle.driver;

                if (!driver) {
                    return (
                        <span className="text-muted-foreground">N/A</span>
                    );
                }

                return (
                    <div className="flex flex-col">
                        <span className="font-medium text-sm">{driver.name}</span>
                        <span className="text-xs text-muted-foreground">
                            {driver.official_phone || driver.email}
                        </span>
                    </div>
                );
            },
        },
        {
            key: 'is_active',
            label: 'Status',
            sortable: true,
            render: (value) => (
                <Badge variant={value ? 'default' : 'secondary'}>
                    {value ? 'Active' : 'Inactive'}
                </Badge>
            ),
        },
        {
            key: 'status' as keyof Vehicle,
            label: 'Condition',
            sortable: true,
            render: (value) => {
                const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
                    available: 'default',
                    assigned: 'secondary',
                    in_transit: 'secondary',
                    maintenance: 'outline',
                    out_of_service: 'destructive',
                };

                return (
                    <Badge variant={variants[value as string] ?? 'outline'} className="capitalize">
                        {typeof value === 'string' ? value.replace('_', ' ') : value}
                    </Badge>
                );
            },
        },
        {
            key: 'expiring_documents' as keyof Vehicle,
            label: 'Expiry',
            sortable: false,
            render: (_, vehicle) => {
                const documents = vehicle.expiring_documents ?? [];

                if (documents.length === 0) {
                    return <span className="text-sm text-muted-foreground">-</span>;
                }

                return (
                    <div className="flex flex-col gap-1">
                        {documents.map((doc) => (
                            <Badge
                                key={doc.type}
                                variant={doc.days_left !== null && doc.days_left < 0 ? 'destructive' : 'outline'}
                                className="w-fit gap-1 text-xs"
                            >
                                <AlertTriangle className="h-3 w-3" />
                                {doc.name}
                                {doc.days_left !== null && (doc.days_left < 0 ? ` expired ${Math.abs(doc.days_left)}d ago` : ` in ${doc.days_left}d`)}
                            </Badge>
                        ))}
                    </div>
                );
            },
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
            key: 'actions' as keyof Vehicle,
            label: 'Actions',
            render: (_, row) => (
                <div className="flex items-center space-x-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(route('vehicles.show', row.id));
                        }}
                        title="View vehicle"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(route('vehicles.edit', row.id));
                        }}
                        title="Edit vehicle"
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
                                openAssignDriverDialog(row);
                            }}
                            title="Assign driver"
                            className="cursor-pointer transition-all hover:scale-110 hover:bg-emerald-50 hover:text-emerald-600"
                        >
                            <UserCog className="h-4 w-4" />
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            if (confirm(`Are you sure you want to delete ${row.brand} ${row.model}?`)) {
                                router.delete(route('vehicles.destroy', row.id), {
                                    preserveScroll: true,
                                });
                            }
                        }}
                        title="Delete vehicle"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-red-50 hover:text-red-600"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    // Define filters
    const filters: Array<{
        key: string;
        label: string;
        type: string;
        options: Array<{ label: string; value: string | boolean }>;
    }> = [
        {
            key: 'brand',
            label: 'Brand',
            type: 'multiselect',
            options: filterOptions.brands.map((brand) => ({
                label: brand,
                value: brand,
            })),
        },
        {
            key: 'color',
            label: 'Color',
            type: 'multiselect',
            options: filterOptions.colors.map((color) => ({
                label: color.charAt(0).toUpperCase() + color.slice(1),
                value: color,
            })),
        },
        {
            key: 'vehicle_type',
            label: 'Vehicle Type',
            type: 'multiselect',
            options: filterOptions.vehicle_types.map((type) => ({
                label: type.label,
                value: type.value,
            })),
        },
        {
            key: 'rental_type',
            label: 'Rental Type',
            type: 'multiselect',
            options: filterOptions.rental_types.map((type) => ({
                label: type.label,
                value: type.value,
            })),
        },
        {
            key: 'fuel_type',
            label: 'Fuel Type',
            type: 'multiselect',
            options: filterOptions.fuel_types.map((type) => ({
                label: type.label,
                value: type.value,
            })),
        },
        {
            key: 'vendor_id',
            label: 'Service Provider',
            type: 'multiselect',
            options: filterOptions.vendors.map((vendor) => ({
                label: vendor.label,
                value: vendor.value,
            })),
        },
        {
            key: 'is_active',
            label: 'Status',
            type: 'multiselect',
            options: filterOptions.statuses.map((status) => ({
                label: status.label,
                value: status.value,
            })),
        },
        {
            key: 'status',
            label: 'Condition',
            type: 'multiselect',
            options: filterOptions.conditions.map((condition) => ({
                label: condition.label,
                value: condition.value,
            })),
        },
    ];

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Vehicles" />

            <div className="space-y-6">
                <PageHeader
                    title="Vehicles"
                    description="Manage fleet and documentation"
                    actions={[
                        {
                            label: 'Sort by Name',
                            variant: 'outline',
                            onClick: () => handleSort('brand')
                        },
                        {
                            label: 'Sort by ID',
                            variant: 'outline',
                            onClick: () => handleSort('id')
                        },
                        {
                            label: 'Add Vehicle',
                            icon: <Plus className="mr-2 h-4 w-4" />,
                            href: route('vehicles.create'),
                        },
                    ]}
                    stats={[
                        {
                            label: 'Total Vehicles',
                            value: stats.total,
                        },
                        {
                            label: 'Active Vehicles',
                            value: stats.active,
                        },
                        {
                            label: 'Brands',
                            value: stats.brands,
                        },
                        {
                            label: 'Inactive',
                            value: stats.inactive,
                        },
                    ]}
                />

                {canEditVehicles && selectedIds.length > 0 && (
                    <div className="flex items-center justify-between rounded-lg border bg-muted/30 p-3">
                        <span className="text-sm font-medium">{selectedIds.length} vehicle(s) selected</span>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" onClick={() => setSelectedIds([])}>
                                Clear selection
                            </Button>
                            <Button
                                size="sm"
                                className="bg-green-600 hover:bg-green-700"
                                onClick={() => submitBulkStatus(true)}
                                disabled={bulkStatusProcessing}
                            >
                                <CheckCircle className="mr-2 h-4 w-4" />
                                Activate Selected
                            </Button>
                            <Button
                                size="sm"
                                variant="destructive"
                                onClick={() => submitBulkStatus(false)}
                                disabled={bulkStatusProcessing}
                            >
                                <XCircle className="mr-2 h-4 w-4" />
                                Deactivate Selected
                            </Button>
                        </div>
                    </div>
                )}

                {/* Data Table */}
                <ServerSideDataTable
                    data={vehicles}
                    columns={columns}
                    queryParams={queryParams}
                    filterOptions={filterOptions}
                    filters={filters}
                    searchPlaceholder="Search vehicles..."
                    exportable={true}
                    exportUrl="/vehicles-export"
                    onRowClick={handleRowClick}
                    emptyMessage="No vehicles found. Add your first vehicle to get started."
                    selectable={canEditVehicles ? () => true : undefined}
                    selectedIds={selectedIds}
                    onSelectionChange={canEditVehicles ? setSelectedIds : undefined}
                    getRowId={canEditVehicles ? getVehicleRowId : undefined}
                />
            </div>

            <Dialog open={assignDriverVehicle !== null} onOpenChange={(open) => !open && closeAssignDriverDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Assign Driver</DialogTitle>
                        <DialogDescription>
                            Choose a driver for {assignDriverVehicle?.registration_number}, or unassign the current one.
                        </DialogDescription>
                    </DialogHeader>

                    <form className="space-y-4" onSubmit={submitAssignDriver}>
                        <div className="space-y-2">
                            <Label htmlFor="assign_driver_id">Driver</Label>
                            <Select
                                value={assignDriverData.driver_id}
                                onValueChange={(value) => setAssignDriverData('driver_id', value)}
                            >
                                <SelectTrigger id="assign_driver_id" className="w-full">
                                    <SelectValue placeholder="Select a driver" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">— Unassign —</SelectItem>
                                    {assignableDrivers.map((driver) => (
                                        <SelectItem key={driver.id} value={String(driver.id)}>
                                            {driver.name}
                                            {driver.email ? ` • ${driver.email}` : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={assignDriverErrors.driver_id} />
                        </div>

                        {assignDriverErrors.confirm_reassign && (
                            <div className="space-y-2 rounded-md border border-amber-300 bg-amber-50 p-3">
                                <p className="text-sm text-amber-800">{assignDriverErrors.confirm_reassign}</p>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="confirm_reassign"
                                        checked={assignDriverData.confirm_reassign}
                                        onCheckedChange={(checked) => setAssignDriverData('confirm_reassign', checked === true)}
                                    />
                                    <Label htmlFor="confirm_reassign" className="text-sm font-normal">
                                        Confirm reassign
                                    </Label>
                                </div>
                            </div>
                        )}

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeAssignDriverDialog} disabled={assignDriverProcessing}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={assignDriverProcessing}>
                                {assignDriverProcessing ? 'Saving...' : 'Save'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppSidebarLayout>
    );
}
