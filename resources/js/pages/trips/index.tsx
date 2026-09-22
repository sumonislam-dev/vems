import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { CANCELLATION_REASONS, canCancelTrip, canCompleteTrip, canStartTrip } from '@/lib/trip-status';
import { formatDate } from '@/lib/utils';
import { BreadcrumbItem, DataTableColumn, Trip } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Ban,
    Calendar,
    Car,
    CheckCircle,
    CheckCircle2,
    Clock,
    Edit,
    Eye,
    FileText,
    MapPin,
    MoreVertical,
    Play,
    Plus,
    TrendingUp,
    User,
    XCircle,
} from 'lucide-react';
import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';

interface PaginatedTrips {
    data: Trip[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface TripsPageProps {
    trips: PaginatedTrips;
    stats: {
        total: number;
        pending: number;
        approved: number;
        in_progress: number;
        completed: number;
        today: number;
    };
    queryParams: {
        search?: string;
        filters?: Record<string, (string | number)[]>;
        date_from?: string;
        date_to?: string;
        vehicle_id?: number;
        sort?: string;
        direction?: 'asc' | 'desc';
        per_page?: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Trips', href: '/trips' },
];

const getStatusBadge = (status: Trip['status']) => {
    const config = {
        pending: { label: 'Pending', className: 'border-yellow-200 bg-yellow-100 text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-500/15 dark:text-yellow-300' },
        approved: { label: 'Approved', className: 'border-blue-200 bg-blue-100 text-blue-800 dark:border-blue-500/30 dark:bg-blue-500/15 dark:text-blue-300' },
        rejected: { label: 'Rejected', className: 'border-red-200 bg-red-100 text-red-800 dark:border-red-500/30 dark:bg-red-500/15 dark:text-red-300' },
        assigned: { label: 'Assigned', className: 'border-cyan-200 bg-cyan-100 text-cyan-800 dark:border-cyan-500/30 dark:bg-cyan-500/15 dark:text-cyan-300' },
        in_progress: { label: 'In Progress', className: 'border-violet-200 bg-violet-100 text-violet-800 dark:border-violet-500/30 dark:bg-violet-500/15 dark:text-violet-300' },
        completed: { label: 'Completed', className: 'border-green-200 bg-green-100 text-green-800 dark:border-green-500/30 dark:bg-green-500/15 dark:text-green-300' },
        cancelled: { label: 'Cancelled', className: 'border-slate-200 bg-slate-100 text-slate-800 dark:border-slate-500/30 dark:bg-slate-500/15 dark:text-slate-300' },
    };
    const { label, className } = config[status] || config.pending;
    return <Badge className={className}>{label}</Badge>;
};

const getPriorityBadge = (priority: Trip['priority']) => {
    const config = {
        urgent: { label: 'Urgent', className: 'border-red-300 bg-red-100 text-red-800 dark:border-red-500/40 dark:bg-red-500/20 dark:text-red-300' },
        high: { label: 'High', className: 'border-orange-200 bg-orange-100 text-orange-800 dark:border-orange-500/30 dark:bg-orange-500/15 dark:text-orange-300' },
        medium: { label: 'Medium', className: 'border-yellow-200 bg-yellow-100 text-yellow-800 dark:border-yellow-500/30 dark:bg-yellow-500/15 dark:text-yellow-300' },
        low: { label: 'Low', className: 'border-slate-200 bg-slate-100 text-slate-800 dark:border-slate-500/30 dark:bg-slate-500/15 dark:text-slate-300' },
    };
    const { label, className } = config[priority] || config.medium;
    return <Badge variant="outline" className={className}>{label}</Badge>;
};

const checkPermission = (permission: string, permissions: string[] = []): boolean => permissions.includes(permission);

type TripActionType = 'reject' | 'start' | 'complete' | 'cancel';

export default function TripsIndex({ trips, stats, queryParams }: TripsPageProps) {
    const pageProps = usePage().props as unknown as { auth?: { user?: { id?: number }; permissions?: string[] } };
    const permissions = pageProps.auth?.permissions ?? [];
    const currentUserId = pageProps.auth?.user?.id;
    const canApproveTrips = checkPermission('approve-trips', permissions);
    const canEditTrips = checkPermission('edit-trips', permissions);

    const [actionDialog, setActionDialog] = useState<{ type: TripActionType; trip: Trip } | null>(null);

    const {
        data: rejectData,
        setData: setRejectData,
        post: postReject,
        processing: rejectProcessing,
        errors: rejectErrors,
        reset: resetReject,
        clearErrors: clearRejectErrors,
    } = useForm<{ rejection_reason: string }>({ rejection_reason: '' });

    const {
        data: startData,
        setData: setStartData,
        post: postStart,
        processing: startProcessing,
        errors: startErrors,
        reset: resetStart,
        clearErrors: clearStartErrors,
    } = useForm<{ odometer_start: string }>({ odometer_start: '' });

    const {
        data: completeData,
        setData: setCompleteData,
        post: postComplete,
        processing: completeProcessing,
        errors: completeErrors,
        reset: resetComplete,
        clearErrors: clearCompleteErrors,
    } = useForm<{ odometer_end: string; fuel_consumed: string; fuel_cost: string; other_costs: string; notes: string }>({
        odometer_end: '',
        fuel_consumed: '',
        fuel_cost: '',
        other_costs: '',
        notes: '',
    });

    const {
        data: cancelData,
        setData: setCancelData,
        post: postCancel,
        processing: cancelProcessing,
        errors: cancelErrors,
        reset: resetCancel,
        clearErrors: clearCancelErrors,
    } = useForm<{ cancellation_reason: string; cancellation_notes: string }>({
        cancellation_reason: '',
        cancellation_notes: '',
    });

    const openActionDialog = useCallback(
        (type: TripActionType, trip: Trip) => {
            resetReject();
            clearRejectErrors();
            resetStart();
            clearStartErrors();
            resetComplete();
            clearCompleteErrors();
            resetCancel();
            clearCancelErrors();
            setActionDialog({ type, trip });
        },
        [resetReject, clearRejectErrors, resetStart, clearStartErrors, resetComplete, clearCompleteErrors, resetCancel, clearCancelErrors],
    );

    const closeActionDialog = useCallback(() => {
        setActionDialog(null);
    }, []);

    const submitReject = (submitEvent: FormEvent<HTMLFormElement>) => {
        submitEvent.preventDefault();
        if (!actionDialog) return;

        postReject(route('trips.reject', actionDialog.trip.id), {
            preserveScroll: true,
            onSuccess: () => closeActionDialog(),
        });
    };

    const submitStart = (submitEvent: FormEvent<HTMLFormElement>) => {
        submitEvent.preventDefault();
        if (!actionDialog) return;

        postStart(route('trips.start', actionDialog.trip.id), {
            preserveScroll: true,
            onSuccess: () => closeActionDialog(),
        });
    };

    const submitComplete = (submitEvent: FormEvent<HTMLFormElement>) => {
        submitEvent.preventDefault();
        if (!actionDialog) return;

        postComplete(route('trips.complete', actionDialog.trip.id), {
            preserveScroll: true,
            onSuccess: () => closeActionDialog(),
        });
    };

    const submitCancel = (submitEvent: FormEvent<HTMLFormElement>) => {
        submitEvent.preventDefault();
        if (!actionDialog) return;

        postCancel(route('trips.cancel', actionDialog.trip.id), {
            preserveScroll: true,
            onSuccess: () => closeActionDialog(),
        });
    };

    const isAssignedDriver = useCallback((row: Trip) => !!currentUserId && row.driver?.id === currentUserId, [currentUserId]);

    const [selectedIds, setSelectedIds] = useState<(string | number)[]>([]);
    const [bulkApproveOpen, setBulkApproveOpen] = useState(false);
    const [bulkApproveProcessing, setBulkApproveProcessing] = useState(false);

    // Every server round trip (page/filter/sort change, or a successful bulk
    // approve reloading this page) hands us a fresh `trips` object, so any
    // selection from before that trip no longer applies.
    useEffect(() => {
        setSelectedIds([]);
    }, [trips]);

    const isTripBulkApprovable = useCallback((row: Trip) => canApproveTrips && row.status === 'pending', [canApproveTrips]);
    const getTripRowId = useCallback((row: Trip) => row.id, []);

    const submitBulkApprove = () => {
        router.post(
            route('trips.bulk-approve'),
            { trip_ids: selectedIds },
            {
                preserveScroll: true,
                onStart: () => setBulkApproveProcessing(true),
                onFinish: () => setBulkApproveProcessing(false),
                onSuccess: () => setBulkApproveOpen(false),
            },
        );
    };

    const columns: DataTableColumn<Trip>[] = useMemo(() => [
        {
            key: 'trip_number',
            label: 'Trip Number',
            sortable: true,
            render: (value) => (
                <span className="font-mono text-xs font-semibold text-blue-600">{value}</span>
            ),
        },
        {
            key: 'scheduled_date',
            label: 'Schedule',
            sortable: true,
            render: (_, row) => (
                <div className="flex flex-col space-y-1">
                    <div className="flex items-center text-sm font-medium">
                        <Calendar className="mr-1.5 h-3.5 w-3.5 text-gray-500" />
                        {formatDate(row.scheduled_date)}
                    </div>
                    <div className="flex items-center text-xs text-gray-500">
                        <Clock className="mr-1.5 h-3 w-3" />
                        {row.scheduled_start_time} - {row.scheduled_end_time}
                    </div>
                </div>
            ),
        },
        {
            key: 'trip_type',
            label: 'Type',
            render: (value, row) => (
                <div className="flex flex-col space-y-1">
                    {value && (
                        <Badge variant="outline" className="text-xs font-medium capitalize">
                            {value.replace('-', ' ')}
                        </Badge>
                    )}
                    <span className="text-xs text-gray-500 capitalize">
                        {row.schedule_type.replace('-', ' ')}
                    </span>
                </div>
            ),
        },
        {
            key: 'purpose',
            label: 'Purpose & Details',
            sortable: true,
            render: (value, row) => (
                <div className="max-w-xs">
                    <p className="text-sm font-medium line-clamp-1">{value}</p>
                    {row.start_location && row.end_location && (
                        <div className="mt-1 flex items-center text-xs text-gray-500">
                            <MapPin className="mr-1 h-3 w-3" />
                            <span className="line-clamp-1">
                                {row.start_location} → {row.end_location}
                            </span>
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'vehicle_id',
            label: 'Vehicle',
            render: (_, row) =>
                row.vehicle ? (
                    <div className="flex items-center space-x-2">
                        <Car className="h-4 w-4 text-gray-400" />
                        <div className="flex flex-col">
                            <span className="text-sm font-medium">{row.vehicle.registration_number}</span>
                            <span className="text-xs text-gray-500">
                                {row.vehicle.brand} {row.vehicle.model}
                            </span>
                        </div>
                    </div>
                ) : (
                    <span className="text-sm text-gray-400 italic">Not assigned</span>
                ),
        },
        {
            key: 'driver_id',
            label: 'Driver',
            render: (_, row) =>
                row.driver ? (
                    <div className="flex items-center space-x-2">
                        <User className="h-3.5 w-3.5 text-gray-400" />
                        <span className="text-sm">{row.driver.name}</span>
                    </div>
                ) : (
                    <span className="text-sm text-gray-400 italic">Not assigned</span>
                ),
        },
        {
            key: 'department_id',
            label: 'Department',
            render: (_, row) =>
                row.department ? (
                    <span className="text-sm">{row.department.name}</span>
                ) : (
                    <span className="text-sm text-gray-400">-</span>
                ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (value) => getStatusBadge(value as Trip['status']),
        },
        {
            key: 'priority',
            label: 'Priority',
            render: (value) => getPriorityBadge(value as Trip['priority']),
        },
        {
            key: 'id',
            label: 'Actions',
            render: (_, row) => {
                const canApproveRow = row.status === 'pending' && canApproveTrips;
                const canRejectRow = row.status === 'pending' && canApproveTrips;
                const canStartRow = canStartTrip(row.status) && (canEditTrips || isAssignedDriver(row));
                const canCompleteRow = canCompleteTrip(row.status) && (canEditTrips || isAssignedDriver(row));
                const canCancelRow = canCancelTrip(row.status) && canEditTrips;
                const hasMenuActions = canApproveRow || canRejectRow || canStartRow || canCompleteRow || canCancelRow;

                return (
                    <div className="flex items-center space-x-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.visit(route('trips.show', row.id))}
                            className="cursor-pointer transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                        >
                            <Eye className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => router.visit(route('trips.edit', row.id))}
                            className="cursor-pointer transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                        >
                            <Edit className="h-4 w-4" />
                        </Button>
                        {hasMenuActions && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="sm" className="cursor-pointer transition-all hover:scale-110">
                                        <MoreVertical className="h-4 w-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {canApproveRow && (
                                        <DropdownMenuItem onClick={() => router.post(route('trips.approve', row.id), {}, { preserveScroll: true })}>
                                            <CheckCircle className="mr-2 h-4 w-4 text-green-600" />
                                            Approve Trip
                                        </DropdownMenuItem>
                                    )}
                                    {canRejectRow && (
                                        <DropdownMenuItem onClick={() => openActionDialog('reject', row)}>
                                            <XCircle className="mr-2 h-4 w-4 text-red-600" />
                                            Reject Trip
                                        </DropdownMenuItem>
                                    )}
                                    {canStartRow && (
                                        <DropdownMenuItem onClick={() => openActionDialog('start', row)}>
                                            <Play className="mr-2 h-4 w-4 text-green-600" />
                                            Start Trip
                                        </DropdownMenuItem>
                                    )}
                                    {canCompleteRow && (
                                        <DropdownMenuItem onClick={() => openActionDialog('complete', row)}>
                                            <CheckCircle2 className="mr-2 h-4 w-4 text-green-600" />
                                            Complete Trip
                                        </DropdownMenuItem>
                                    )}
                                    {canCancelRow && (
                                        <DropdownMenuItem
                                            onClick={() => openActionDialog('cancel', row)}
                                            className="text-red-600 focus:text-red-600"
                                        >
                                            <Ban className="mr-2 h-4 w-4" />
                                            Cancel Trip
                                        </DropdownMenuItem>
                                    )}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                    </div>
                );
            },
        },
    ], [canApproveTrips, canEditTrips, isAssignedDriver, openActionDialog]);

    const filters = useMemo(() => [
        {
            key: 'status',
            label: 'Status',
            options: [
                { label: 'All', value: '' },
                { label: 'Pending', value: 'pending' },
                { label: 'Approved', value: 'approved' },
                { label: 'In Progress', value: 'in_progress' },
                { label: 'Completed', value: 'completed' },
                { label: 'Cancelled', value: 'cancelled' },
            ],
            type: 'select' as const,
        },
        {
            key: 'priority',
            label: 'Priority',
            options: [
                { label: 'All', value: '' },
                { label: 'Urgent', value: 'urgent' },
                { label: 'High', value: 'high' },
                { label: 'Medium', value: 'medium' },
                { label: 'Low', value: 'low' },
            ],
            type: 'select' as const,
        },
        {
            key: 'schedule_type',
            label: 'Schedule Type',
            options: [
                { label: 'All', value: '' },
                { label: 'Pick & Drop', value: 'pick-and-drop' },
                { label: 'Pick-up', value: 'pick-up' },
                { label: 'Drop-off', value: 'drop-off' },
                { label: 'Engineer', value: 'engineer' },
                { label: 'Training', value: 'training' },
                { label: 'Ad-hoc', value: 'adhoc' },
                { label: 'Reposition', value: 'reposition' },
                { label: 'Inspection', value: 'inspection' },
                { label: 'Complaints', value: 'complaints' },
                { label: 'CVV', value: 'CVV' },
                { label: 'Incident Inspection', value: 'Incident Inspection' },
                { label: 'Officials', value: 'officials' },
                { label: 'Assigned', value: 'Assigned' },
            ],
            type: 'select' as const,
        },
        {
            key: 'trip_type',
            label: 'Trip Type',
            options: [
                { label: 'All', value: '' },
                { label: 'Inspection', value: 'inspection' },
                { label: 'Pick-up', value: 'pick-up' },
                { label: 'Drop-off', value: 'drop-off' },
                { label: 'Training', value: 'training' },
                { label: 'Complaints', value: 'complaints' },
                { label: 'CVV', value: 'CVV' },
                { label: 'Incident Inspection', value: 'Incident Inspection' },
                { label: 'Officials', value: 'officials' },
                { label: 'Assigned', value: 'Assigned' },
            ],
            type: 'select' as const,
        },
    ], []);

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Trips" />

            <div className="space-y-6">
                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">Total</p>
                                <p className="text-2xl font-bold">{stats.total}</p>
                            </div>
                            <FileText className="h-8 w-8 text-gray-400" />
                        </div>
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">Today</p>
                                <p className="text-2xl font-bold text-blue-600">{stats.today}</p>
                            </div>
                            <Calendar className="h-8 w-8 text-blue-400" />
                        </div>
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">Pending</p>
                                <p className="text-2xl font-bold text-yellow-600">{stats.pending}</p>
                            </div>
                            <Clock className="h-8 w-8 text-yellow-400" />
                        </div>
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">Approved</p>
                                <p className="text-2xl font-bold text-blue-600">{stats.approved}</p>
                            </div>
                            <CheckCircle className="h-8 w-8 text-blue-400" />
                        </div>
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">In Progress</p>
                                <p className="text-2xl font-bold text-purple-600">{stats.in_progress}</p>
                            </div>
                            <TrendingUp className="h-8 w-8 text-purple-400" />
                        </div>
                    </Card>
                    <Card className="p-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-medium text-gray-600">Completed</p>
                                <p className="text-2xl font-bold text-green-600">{stats.completed}</p>
                            </div>
                            <CheckCircle className="h-8 w-8 text-green-400" />
                        </div>
                    </Card>
                </div>

                {/* Data Table */}
                <PageHeader
                    title="Trip Management"
                    description="Manage and track all vehicle trips"
                    actions={[
                        {
                            label: 'Create Trip',
                            icon: <Plus className="h-4 w-4" />,
                            href: route('trips.create'),
                        },
                    ]}
                />

                {canApproveTrips && selectedIds.length > 0 && (
                    <div className="flex items-center justify-between rounded-lg border bg-muted/30 p-3">
                        <span className="text-sm font-medium">{selectedIds.length} trip(s) selected</span>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" onClick={() => setSelectedIds([])}>
                                Clear selection
                            </Button>
                            <Button size="sm" className="bg-green-600 hover:bg-green-700" onClick={() => setBulkApproveOpen(true)}>
                                <CheckCircle className="mr-2 h-4 w-4" />
                                Approve Selected
                            </Button>
                        </div>
                    </div>
                )}

                <ServerSideDataTable
                    data={trips}
                    columns={columns}
                    queryParams={queryParams}
                    filterOptions={{}}
                    filters={filters}
                    searchPlaceholder="Search by trip number, purpose, location..."
                    exportable={false}
                    emptyMessage="No trips found. Create your first trip to get started."
                    selectable={canApproveTrips ? isTripBulkApprovable : undefined}
                    selectedIds={selectedIds}
                    onSelectionChange={canApproveTrips ? setSelectedIds : undefined}
                    getRowId={canApproveTrips ? getTripRowId : undefined}
                    showSerialColumn
                />
            </div>

            <Dialog open={bulkApproveOpen} onOpenChange={(open) => !open && setBulkApproveOpen(false)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Approve {selectedIds.length} trip(s)?</DialogTitle>
                        <DialogDescription>This approves every selected trip that is still pending.</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => setBulkApproveOpen(false)} disabled={bulkApproveProcessing}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            className="bg-green-600 hover:bg-green-700"
                            onClick={submitBulkApprove}
                            disabled={bulkApproveProcessing || selectedIds.length === 0}
                        >
                            <CheckCircle className="mr-2 h-4 w-4" />
                            {bulkApproveProcessing ? 'Approving...' : `Approve ${selectedIds.length} Trip(s)`}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={actionDialog?.type === 'reject'} onOpenChange={(open) => !open && closeActionDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Reject Trip</DialogTitle>
                        <DialogDescription>{actionDialog?.trip.trip_number} will be marked as rejected.</DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitReject}>
                        <div className="space-y-2">
                            <Label htmlFor="rejection_reason">Rejection reason</Label>
                            <Textarea
                                id="rejection_reason"
                                value={rejectData.rejection_reason}
                                onChange={(event) => setRejectData('rejection_reason', event.target.value)}
                                rows={3}
                                required
                            />
                            <InputError message={rejectErrors.rejection_reason} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeActionDialog} disabled={rejectProcessing}>
                                Cancel
                            </Button>
                            <Button type="submit" variant="destructive" disabled={rejectProcessing || !rejectData.rejection_reason}>
                                <XCircle className="mr-2 h-4 w-4" />
                                {rejectProcessing ? 'Rejecting...' : 'Reject Trip'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={actionDialog?.type === 'start'} onOpenChange={(open) => !open && closeActionDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Start Trip</DialogTitle>
                        <DialogDescription>
                            {actionDialog?.trip.trip_number}: you can enter the current odometer reading now, or skip and add it later.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitStart}>
                        <div className="space-y-2">
                            <Label htmlFor="odometer_start">Odometer start (optional)</Label>
                            <Input
                                id="odometer_start"
                                type="number"
                                min="0"
                                step="0.01"
                                value={startData.odometer_start}
                                onChange={(event) => setStartData('odometer_start', event.target.value)}
                                placeholder="Leave blank if not available"
                            />
                            <InputError message={startErrors.odometer_start} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeActionDialog} disabled={startProcessing}>
                                Cancel
                            </Button>
                            <Button type="submit" className="bg-green-600 hover:bg-green-700" disabled={startProcessing}>
                                <Play className="mr-2 h-4 w-4" />
                                {startProcessing ? 'Starting...' : 'Start Trip'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={actionDialog?.type === 'complete'} onOpenChange={(open) => !open && closeActionDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Complete Trip</DialogTitle>
                        <DialogDescription>
                            {actionDialog?.trip.trip_number}: record the ending odometer reading and any costs incurred.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitComplete}>
                        <div className="space-y-2">
                            <Label htmlFor="index_odometer_end">Odometer end (optional)</Label>
                            <Input
                                id="index_odometer_end"
                                type="number"
                                min={actionDialog?.trip.odometer_start ?? 0}
                                step="0.01"
                                value={completeData.odometer_end}
                                onChange={(event) => setCompleteData('odometer_end', event.target.value)}
                                placeholder="Leave blank if not available"
                            />
                            <InputError message={completeErrors.odometer_end} />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="index_fuel_consumed">Fuel consumed</Label>
                                <Input
                                    id="index_fuel_consumed"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={completeData.fuel_consumed}
                                    onChange={(event) => setCompleteData('fuel_consumed', event.target.value)}
                                    placeholder="Optional"
                                />
                                <InputError message={completeErrors.fuel_consumed} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="index_fuel_cost">Fuel cost</Label>
                                <Input
                                    id="index_fuel_cost"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={completeData.fuel_cost}
                                    onChange={(event) => setCompleteData('fuel_cost', event.target.value)}
                                    placeholder="Optional"
                                />
                                <InputError message={completeErrors.fuel_cost} />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="index_other_costs">Other costs</Label>
                            <Input
                                id="index_other_costs"
                                type="number"
                                min="0"
                                step="0.01"
                                value={completeData.other_costs}
                                onChange={(event) => setCompleteData('other_costs', event.target.value)}
                                placeholder="Optional"
                            />
                            <InputError message={completeErrors.other_costs} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="index_complete_notes">Notes</Label>
                            <Textarea
                                id="index_complete_notes"
                                value={completeData.notes}
                                onChange={(event) => setCompleteData('notes', event.target.value)}
                                placeholder="Optional"
                                rows={3}
                            />
                            <InputError message={completeErrors.notes} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeActionDialog} disabled={completeProcessing}>
                                Cancel
                            </Button>
                            <Button type="submit" className="bg-green-600 hover:bg-green-700" disabled={completeProcessing}>
                                <CheckCircle2 className="mr-2 h-4 w-4" />
                                {completeProcessing ? 'Completing...' : 'Complete Trip'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={actionDialog?.type === 'cancel'} onOpenChange={(open) => !open && closeActionDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Cancel Trip</DialogTitle>
                        <DialogDescription>{actionDialog?.trip.trip_number} will be marked as cancelled. Choose a reason.</DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={submitCancel}>
                        <div className="space-y-2">
                            <Label htmlFor="index_cancellation_reason">Cancellation reason</Label>
                            <Select
                                value={cancelData.cancellation_reason}
                                onValueChange={(value) => setCancelData('cancellation_reason', value)}
                            >
                                <SelectTrigger id="index_cancellation_reason" className="w-full">
                                    <SelectValue placeholder="Select a reason" />
                                </SelectTrigger>
                                <SelectContent>
                                    {CANCELLATION_REASONS.map((reason) => (
                                        <SelectItem key={reason.value} value={reason.value}>
                                            {reason.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={cancelErrors.cancellation_reason} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="index_cancellation_notes">Notes</Label>
                            <Textarea
                                id="index_cancellation_notes"
                                value={cancelData.cancellation_notes}
                                onChange={(event) => setCancelData('cancellation_notes', event.target.value)}
                                placeholder="Optional"
                                rows={3}
                            />
                            <InputError message={cancelErrors.cancellation_notes} />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeActionDialog} disabled={cancelProcessing}>
                                Keep Trip
                            </Button>
                            <Button type="submit" variant="destructive" disabled={cancelProcessing || !cancelData.cancellation_reason}>
                                <Ban className="mr-2 h-4 w-4" />
                                {cancelProcessing ? 'Cancelling...' : 'Cancel Trip'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppSidebarLayout>
    );
}
