import { PageHeader } from '@/base-components/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, User } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    ArrowLeft,
    Edit,
    Mail,
    MapPin,
    Phone,
    Trash2,
    User as UserIcon,
    UserCheck,
    UserX,
    CalendarDays,
    Building,
    Car,
    CreditCard,
    Shield,
    MessageSquare,
    Droplets,
    FileText,
    Star
} from 'lucide-react';
import { FormEvent, useState } from 'react';

type VehicleAssignment = {
    id: number;
    vehicle?: { id: number; registration_number: string; brand: string; model: string; vehicle_type: string } | null;
    assigner?: { id: number; name: string } | null;
    started_at: string;
    ended_at: string | null;
    is_current: boolean;
};

type DriverStats = {
    total_trips: number;
    completed_trips: number;
    in_progress_trips: number;
    total_distance: number | null;
    average_rating: number | string | null;
};

type RecentTrip = {
    id: number;
    trip_number: string;
    scheduled_date: string;
    trip_type: string;
    description: string | null;
    status: string;
};

interface ShowUserProps {
    user: User;
    vehicleAssignments?: VehicleAssignment[];
    driverStats?: DriverStats;
    recentTrips?: RecentTrip[];
}

type AssignableVehicle = {
    id: number;
    registration_number: string;
    brand: string;
    model: string;
    current_driver_id: number | null;
    current_driver_name: string | null;
};

const checkPermission = (permission: string, permissions: string[] = []): boolean => permissions.includes(permission);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/users' },
    { title: 'User Details', href: '#' },
];

export default function ShowUser({ user, vehicleAssignments = [], driverStats, recentTrips = [] }: ShowUserProps) {
    const pageProps = usePage().props as unknown as { auth?: { permissions?: string[] } };
    const canAssignVehicles = checkPermission('assign-vehicles', pageProps.auth?.permissions ?? []);

    const [assignVehicleOpen, setAssignVehicleOpen] = useState(false);
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
        driver_id: String(user.id),
        vehicle_id: '',
        confirm_reassign: false,
    });

    const openAssignVehicleDialog = async () => {
        setAssignVehicleData({ driver_id: String(user.id), vehicle_id: '', confirm_reassign: false });
        clearAssignVehicleErrors();
        setAssignVehicleOpen(true);

        setLoadingVehicles(true);
        try {
            const response = await axios.get<AssignableVehicle[]>(route('drivers.assignable-vehicles', user.id));
            setAssignableVehicles(response.data);
        } catch {
            // Non-critical: the select will just show no options if this fails.
        } finally {
            setLoadingVehicles(false);
        }
    };

    const closeAssignVehicleDialog = () => {
        setAssignVehicleOpen(false);
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

    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete ${user.name}?`)) {
            router.delete(route('drivers.destroy', user.id));
        }
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'active':
                return <UserCheck className="h-4 w-4" />;
            case 'inactive':
                return <UserX className="h-4 w-4" />;
            case 'suspended':
                return <UserX className="h-4 w-4" />;
            default:
                return <UserIcon className="h-4 w-4" />;
        }
    };

    const getStatusVariant = (status: string) => {
        switch (status) {
            case 'active':
                return 'default';
            case 'inactive':
                return 'secondary';
            case 'suspended':
                return 'destructive';
            default:
                return 'outline';
        }
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`${user.name} - User Details`} />

            <div className="space-y-6">
                {/* Important Alerts */}
                {user.user_type === 'driver' && user.license_expiry_date && new Date(user.license_expiry_date) < new Date() && (
                    <Card className="border-destructive bg-destructive/5">
                        <CardContent className="p-4">
                            <div className="flex items-center gap-2 text-destructive">
                                <UserX className="h-5 w-5" />
                                <div>
                                    <h4 className="font-semibold">Expired Driving License</h4>
                                    <p className="text-sm">This driver's license expired on {new Date(user.license_expiry_date).toLocaleDateString()}. Please renew immediately.</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <PageHeader
                    title={user.name}
                    description="User profile and information."
                    actions={[
                        {
                            label: 'Back to Users',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: route('drivers.index'),
                            variant: 'outline',
                        },
                        {
                            label: 'Edit User',
                            icon: <Edit className="mr-2 h-4 w-4" />,
                            href: route('users.edit', user.id),
                        },
                        ...(canAssignVehicles
                            ? [
                                {
                                    label: 'Assign Vehicle',
                                    icon: <Car className="mr-2 h-4 w-4" />,
                                    onClick: openAssignVehicleDialog,
                                    variant: 'outline' as const,
                                },
                            ]
                            : []),
                    ]}
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    {/* User Profile - Enhanced */}
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>User Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {/* Profile Header */}
                            <div className="flex items-center space-x-4">
                                <div className="flex-shrink-0">
                                    {user.image ? (
                                        <img className="h-16 w-16 rounded-full object-cover" src={user.image} alt={user.name} />
                                    ) : (
                                        <div className="h-16 w-16 rounded-full bg-gradient-to-br from-primary/20 to-primary/40 flex items-center justify-center">
                                            <span className="text-xl font-semibold text-primary">
                                                {user.name.charAt(0).toUpperCase()}
                                            </span>
                                        </div>
                                    )}
                                </div>
                                <div className="space-y-1">
                                    <h3 className="text-lg font-semibold">{user.name}</h3>
                                    {user.username && (
                                        <p className="text-sm text-muted-foreground">@{user.username}</p>
                                    )}
                                    <div className="flex items-center space-x-2">
                                        <Badge variant={getStatusVariant(user.status)} className="gap-1">
                                            {getStatusIcon(user.status)}
                                            {user.status.charAt(0).toUpperCase() + user.status.slice(1)}
                                        </Badge>
                                        {user.user_type && (
                                            <Badge variant="outline" className="capitalize gap-1">
                                                <Shield className="h-3 w-3" />
                                                {user.user_type.replace('_', ' ')}
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Basic Information */}
                            <div className="space-y-4">
                                <h4 className="text-sm font-medium text-muted-foreground border-b pb-2">Basic Information</h4>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {user.employee_id && (
                                        <div className="space-y-1">
                                            <div className="flex items-center space-x-2">
                                                <FileText className="h-4 w-4 text-muted-foreground" />
                                                <label className="text-sm font-medium text-muted-foreground">Employee ID</label>
                                            </div>
                                            <p className="text-sm font-mono">{user.employee_id}</p>
                                        </div>
                                    )}

                                    <div className="space-y-1">
                                        <div className="flex items-center space-x-2">
                                            <Mail className="h-4 w-4 text-muted-foreground" />
                                            <label className="text-sm font-medium text-muted-foreground">Email</label>
                                        </div>
                                        <p className="text-sm">{user.email || 'N/A'}</p>
                                    </div>

                                    {user.department_id && (
                                        <div className="space-y-1">
                                            <div className="flex items-center space-x-2">
                                                <Building className="h-4 w-4 text-muted-foreground" />
                                                <label className="text-sm font-medium text-muted-foreground">Department</label>
                                            </div>
                                            <p className="text-sm">Department #{user.department_id}</p>
                                        </div>
                                    )}

                                    {user.blood_group && (
                                        <div className="space-y-1">
                                            <div className="flex items-center space-x-2">
                                                <Droplets className="h-4 w-4 text-muted-foreground" />
                                                <label className="text-sm font-medium text-muted-foreground">Blood Group</label>
                                            </div>
                                            <p className="font-mono text-sm font-semibold text-red-600">{user.blood_group}</p>
                                        </div>
                                    )}

                                    {user.joining_date && (
                                        <div className="space-y-1">
                                            <div className="flex items-center space-x-2">
                                                <CalendarDays className="h-4 w-4 text-muted-foreground" />
                                                <label className="text-sm font-medium text-muted-foreground">Joining Date</label>
                                            </div>
                                            <p className="text-sm">
                                                {new Date(user.joining_date).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'long',
                                                    day: 'numeric',
                                                })}
                                            </p>
                                        </div>
                                    )}

                                    {user.probation_end_date && (
                                        <div className="space-y-1">
                                            <div className="flex items-center space-x-2">
                                                <CalendarDays className="h-4 w-4 text-muted-foreground" />
                                                <label className="text-sm font-medium text-muted-foreground">Probation End Date</label>
                                            </div>
                                            <p className="text-sm">
                                                {new Date(user.probation_end_date).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'long',
                                                    day: 'numeric',
                                                })}
                                                {new Date(user.probation_end_date) > new Date() && (
                                                    <Badge variant="secondary" className="ml-2">On Probation</Badge>
                                                )}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Contact Information */}
                            <div className="space-y-4">
                                <h4 className="text-sm font-medium text-muted-foreground border-b pb-2">Contact Information</h4>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {user.official_phone && (
                                        <div className="space-y-1">
                                            <div className="flex items-center space-x-2">
                                                <Phone className="h-4 w-4 text-muted-foreground" />
                                                <label className="text-sm font-medium text-muted-foreground">Official Phone</label>
                                            </div>
                                            <p className="text-sm font-mono">{user.official_phone}</p>
                                        </div>
                                    )}

                                    {user.personal_phone && (
                                        <div className="space-y-1">
                                            <div className="flex items-center space-x-2">
                                                <Phone className="h-4 w-4 text-muted-foreground" />
                                                <label className="text-sm font-medium text-muted-foreground">Personal Phone</label>
                                            </div>
                                            <p className="text-sm font-mono">{user.personal_phone}</p>
                                        </div>
                                    )}

                                    {user.whatsapp_id && (
                                        <div className="space-y-1">
                                            <div className="flex items-center space-x-2">
                                                <MessageSquare className="h-4 w-4 text-muted-foreground" />
                                                <label className="text-sm font-medium text-muted-foreground">WhatsApp</label>
                                            </div>
                                            <p className="text-sm font-mono">{user.whatsapp_id}</p>
                                        </div>
                                    )}

                                    {/* Legacy phone field for backward compatibility */}
                                    {user.phone && !user.official_phone && !user.personal_phone && (
                                        <div className="space-y-1">
                                            <div className="flex items-center space-x-2">
                                                <Phone className="h-4 w-4 text-muted-foreground" />
                                                <label className="text-sm font-medium text-muted-foreground">Phone</label>
                                            </div>
                                            <p className="text-sm font-mono">{user.phone}</p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Identity Documents */}
                            {(user.nid_number || user.passport_number || user.driving_license_no) && (
                                <div className="space-y-4">
                                    <h4 className="text-sm font-medium text-muted-foreground border-b pb-2">Identity Documents</h4>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {user.nid_number && (
                                            <div className="space-y-1">
                                                <div className="flex items-center space-x-2">
                                                    <CreditCard className="h-4 w-4 text-muted-foreground" />
                                                    <label className="text-sm font-medium text-muted-foreground">National ID</label>
                                                </div>
                                                <p className="text-sm font-mono">{user.nid_number}</p>
                                                {user.nid_file && (
                                                    <a href={`/storage/${user.nid_file}`} target="_blank" rel="noreferrer" className="text-xs text-blue-600 hover:underline">
                                                        View scan
                                                    </a>
                                                )}
                                            </div>
                                        )}

                                        {user.passport_number && (
                                            <div className="space-y-1">
                                                <div className="flex items-center space-x-2">
                                                    <CreditCard className="h-4 w-4 text-muted-foreground" />
                                                    <label className="text-sm font-medium text-muted-foreground">Passport</label>
                                                </div>
                                                <p className="text-sm font-mono">{user.passport_number}</p>
                                            </div>
                                        )}

                                        {user.driving_license_no && (
                                            <div className="space-y-1">
                                                <div className="flex items-center space-x-2">
                                                    <Car className="h-4 w-4 text-muted-foreground" />
                                                    <label className="text-sm font-medium text-muted-foreground">Driving License</label>
                                                </div>
                                                <p className="text-sm font-mono">{user.driving_license_no}</p>
                                                {user.driving_license_file && (
                                                    <a href={`/storage/${user.driving_license_file}`} target="_blank" rel="noreferrer" className="text-xs text-blue-600 hover:underline">
                                                        View scan
                                                    </a>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Emergency Contact */}
                            {(user.emergency_contact_name || user.emergency_phone) && (
                                <div className="space-y-4">
                                    <h4 className="text-sm font-medium text-muted-foreground border-b pb-2">Emergency Contact</h4>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {user.emergency_contact_name && (
                                            <div className="space-y-1">
                                                <div className="flex items-center space-x-2">
                                                    <UserIcon className="h-4 w-4 text-muted-foreground" />
                                                    <label className="text-sm font-medium text-muted-foreground">Contact Name</label>
                                                </div>
                                                <p className="text-sm">{user.emergency_contact_name}</p>
                                                {user.emergency_contact_relation && (
                                                    <p className="text-xs text-muted-foreground">({user.emergency_contact_relation})</p>
                                                )}
                                            </div>
                                        )}

                                        {user.emergency_phone && (
                                            <div className="space-y-1">
                                                <div className="flex items-center space-x-2">
                                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                                    <label className="text-sm font-medium text-muted-foreground">Emergency Phone</label>
                                                </div>
                                                <p className="text-sm font-mono">{user.emergency_phone}</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Address Information */}
                            <div className="space-y-4">
                                <h4 className="text-sm font-medium text-muted-foreground border-b pb-2">Address Information</h4>
                                <div className="grid grid-cols-1 gap-4">
                                    {user.area && (
                                        <div className="space-y-1">
                                            <div className="flex items-center space-x-2">
                                                <MapPin className="h-4 w-4 text-muted-foreground" />
                                                <label className="text-sm font-medium text-muted-foreground">Area/Location</label>
                                            </div>
                                            <p className="text-sm font-medium">{user.area}</p>
                                        </div>
                                    )}

                                    {user.present_address && (
                                        <div className="space-y-1">
                                            <div className="flex items-center space-x-2">
                                                <MapPin className="h-4 w-4 text-muted-foreground" />
                                                <label className="text-sm font-medium text-muted-foreground">Present Address</label>
                                            </div>
                                            <p className="text-sm">{user.present_address}</p>
                                        </div>
                                    )}

                                    {user.permanent_address && (
                                        <div className="space-y-1">
                                            <div className="flex items-center space-x-2">
                                                <MapPin className="h-4 w-4 text-muted-foreground" />
                                                <label className="text-sm font-medium text-muted-foreground">Permanent Address</label>
                                            </div>
                                            <p className="text-sm">{user.permanent_address}</p>
                                        </div>
                                    )}

                                    {/* Legacy address field for backward compatibility */}
                                    {user.address && !user.present_address && !user.permanent_address && (
                                        <div className="space-y-1">
                                            <div className="flex items-center space-x-2">
                                                <MapPin className="h-4 w-4 text-muted-foreground" />
                                                <label className="text-sm font-medium text-muted-foreground">Address</label>
                                            </div>
                                            <p className="text-sm">{user.address}</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* System Information & Driver Stats */}
                    <div className="space-y-6">
                        {/* Driver Statistics (if applicable) */}
                        {user.user_type === 'driver' && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Car className="h-5 w-5" />
                                        Driver Statistics
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {user.driver_status && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">Driver Status</label>
                                            <p className="text-sm">
                                                <Badge
                                                    variant={user.driver_status === 'active' ? 'default' : 'secondary'}
                                                    className="capitalize"
                                                >
                                                    {user.driver_status}
                                                </Badge>
                                            </p>
                                        </div>
                                    )}

                                    {user.license_class && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">License Class</label>
                                            <p className="text-sm font-mono">{user.license_class}</p>
                                        </div>
                                    )}

                                    {user.license_issue_date && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">License Issue Date</label>
                                            <p className="text-sm">
                                                {new Date(user.license_issue_date).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'long',
                                                    day: 'numeric',
                                                })}
                                            </p>
                                        </div>
                                    )}

                                    {user.license_expiry_date && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">License Expiry</label>
                                            <p className="text-sm">
                                                {new Date(user.license_expiry_date).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'long',
                                                    day: 'numeric',
                                                })}
                                                {user.license_status === 'expired' && (
                                                    <Badge variant="destructive" className="ml-2 text-xs">Expired</Badge>
                                                )}
                                                {user.license_status === 'expiring_soon' && (
                                                    <Badge variant="outline" className="ml-2 text-xs">Expiring Soon</Badge>
                                                )}
                                            </p>
                                        </div>
                                    )}

                                    {typeof (driverStats?.total_trips ?? user.total_trips_completed) === 'number' && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">Total Trips</label>
                                            <p className="text-sm font-semibold">{driverStats?.total_trips ?? user.total_trips_completed}</p>
                                        </div>
                                    )}

                                    {driverStats && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">Completed / In Progress</label>
                                            <p className="text-sm font-semibold">{driverStats.completed_trips} / {driverStats.in_progress_trips}</p>
                                        </div>
                                    )}

                                    {typeof user.total_distance_covered === 'number' && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">Distance Covered</label>
                                            <p className="text-sm font-semibold">{user.total_distance_covered} km</p>
                                        </div>
                                    )}

                                    {user.average_rating !== null && user.average_rating !== undefined && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">Average Rating</label>
                                            <div className="flex items-center gap-1">
                                                <Star className="h-4 w-4 fill-yellow-400 text-yellow-400" />
                                                <p className="text-sm font-semibold">{Number(user.average_rating).toFixed(1)}</p>
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* System Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="h-5 w-5" />
                                    System Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">User ID</label>
                                    <p className="font-mono text-sm">{user.id}</p>
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Email Verified</label>
                                    <p className="text-sm">
                                        {user.email_verified_at ? (
                                            <Badge variant="default" className="gap-1">
                                                <UserCheck className="h-3 w-3" />
                                                Verified
                                            </Badge>
                                        ) : (
                                            <Badge variant="destructive" className="gap-1">
                                                <UserX className="h-3 w-3" />
                                                Not Verified
                                            </Badge>
                                        )}
                                    </p>
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Created At</label>
                                    <p className="text-sm">
                                        {new Date(user.created_at).toLocaleDateString('en-US', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })}
                                    </p>
                                </div>

                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Last Updated</label>
                                    <p className="text-sm">
                                        {new Date(user.updated_at).toLocaleDateString('en-US', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })}
                                    </p>
                                </div>

                                {user.last_login_at && (
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">Last Login</label>
                                        <p className="text-sm">
                                            {new Date(user.last_login_at).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </p>
                                        {user.last_login_ip && (
                                            <p className="text-xs text-muted-foreground mt-1">
                                                IP: {user.last_login_ip}
                                                {user.last_login_location && ` • ${user.last_login_location}`}
                                                {user.last_login_device && ` • ${user.last_login_device}`}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Recent Trips */}
                {recentTrips.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Trips</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded border">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left">Trip #</th>
                                            <th className="px-3 py-2 text-left">Date</th>
                                            <th className="px-3 py-2 text-left">Type</th>
                                            <th className="px-3 py-2 text-left">Description</th>
                                            <th className="px-3 py-2 text-left">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {recentTrips.map((trip) => (
                                            <tr
                                                key={trip.id}
                                                className="border-t cursor-pointer hover:bg-gray-50"
                                                onClick={() => router.visit(route('trips.show', trip.id))}
                                            >
                                                <td className="px-3 py-2 font-mono">{trip.trip_number}</td>
                                                <td className="px-3 py-2">{new Date(trip.scheduled_date).toLocaleDateString()}</td>
                                                <td className="px-3 py-2 capitalize">{trip.trip_type?.replace('_', ' ')}</td>
                                                <td className="px-3 py-2 text-muted-foreground">{trip.description || '-'}</td>
                                                <td className="px-3 py-2">
                                                    <Badge variant={trip.status === 'completed' ? 'default' : trip.status === 'cancelled' || trip.status === 'rejected' ? 'destructive' : 'secondary'} className="capitalize">
                                                        {trip.status.replace('_', ' ')}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Vendor / Service Provider Information */}
                {user.vendor && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Vendor / Service Provider Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Service Provider Name</label>
                                    <p className="text-sm font-medium">{user.vendor.name}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Status</label>
                                    <Badge variant={user.vendor.status === 'active' ? 'default' : 'secondary'}>
                                        {user.vendor.status === 'active' ? 'Active' : 'Inactive'}
                                    </Badge>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Phone</label>
                                    <p className="text-sm">{user.vendor.phone || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Email</label>
                                    <p className="text-sm">{user.vendor.email || 'N/A'}</p>
                                </div>
                                <div className="md:col-span-2">
                                    <label className="text-sm font-medium text-muted-foreground">Address</label>
                                    <p className="text-sm">{user.vendor.address || 'N/A'}</p>
                                </div>
                                {user.vendor.website && (
                                    <div className="md:col-span-2">
                                        <label className="text-sm font-medium text-muted-foreground">Website</label>
                                        <p className="text-sm">
                                            <a href={user.vendor.website} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">
                                                {user.vendor.website}
                                            </a>
                                        </p>
                                    </div>
                                )}
                                {user.vendor.description && (
                                    <div className="md:col-span-2">
                                        <label className="text-sm font-medium text-muted-foreground">Description</label>
                                        <p className="text-sm">{user.vendor.description}</p>
                                    </div>
                                )}
                            </div>

                            {/* Contact Persons */}
                            {user.vendor.contact_persons && user.vendor.contact_persons.length > 0 && (
                                <div className="mt-6">
                                    <h4 className="text-sm font-medium text-muted-foreground mb-3">Contact Persons</h4>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {user.vendor.contact_persons.map((contact) => (
                                            <div key={contact.id} className="border rounded-lg p-4">
                                                <div className="flex items-center justify-between mb-2">
                                                    <h5 className="font-medium">{contact.name}</h5>
                                                    {contact.is_primary && (
                                                        <Badge variant="outline" className="text-xs">Primary</Badge>
                                                    )}
                                                </div>
                                                {contact.position && (
                                                    <p className="text-sm text-muted-foreground mb-1">{contact.position}</p>
                                                )}
                                                {contact.phone && (
                                                    <p className="text-sm">📞 {contact.phone}</p>
                                                )}
                                                {contact.email && (
                                                    <p className="text-sm">✉️ {contact.email}</p>
                                                )}
                                                {contact.notes && (
                                                    <p className="text-sm text-muted-foreground mt-2">{contact.notes}</p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Vehicle Assignment History */}
                {vehicleAssignments.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Vehicle Assignment History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded border">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left">Vehicle</th>
                                            <th className="px-3 py-2 text-left">Type</th>
                                            <th className="px-3 py-2 text-left">Started</th>
                                            <th className="px-3 py-2 text-left">Ended</th>
                                            <th className="px-3 py-2 text-left">Assigned By</th>
                                            <th className="px-3 py-2 text-left">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {vehicleAssignments.map((a) => (
                                            <tr key={a.id} className="border-t">
                                                <td className="px-3 py-2">
                                                    {a.vehicle
                                                        ? `${a.vehicle.registration_number} - ${a.vehicle.brand} ${a.vehicle.model}`
                                                        : '-'}
                                                </td>
                                                <td className="px-3 py-2 capitalize">
                                                    {a.vehicle?.vehicle_type?.replace('_', ' ') ?? '-'}
                                                </td>
                                                <td className="px-3 py-2">{new Date(a.started_at).toLocaleString()}</td>
                                                <td className="px-3 py-2">{a.ended_at ? new Date(a.ended_at).toLocaleString() : '-'}</td>
                                                <td className="px-3 py-2">{a.assigner?.name ?? '-'}</td>
                                                <td className="px-3 py-2">
                                                    <span className={`inline-flex items-center rounded px-2 py-0.5 text-xs ${a.is_current ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'}`}>
                                                        {a.is_current ? 'Current' : 'Past'}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Actions */}
                <Card>
                    <CardContent className="p-4">
                        <div className="flex flex-col sm:flex-row gap-3">
                            <Button
                                onClick={() => router.visit(route('drivers.edit', user.id))}
                                className="flex items-center justify-center gap-2"
                            >
                                <Edit className="h-4 w-4" />
                                Edit User
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={handleDelete}
                                className="flex items-center justify-center gap-2"
                            >
                                <Trash2 className="h-4 w-4" />
                                Delete User
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => window.print()}
                                className="flex items-center justify-center gap-2"
                            >
                                <FileText className="h-4 w-4" />
                                Print Profile
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Dialog open={assignVehicleOpen} onOpenChange={(open) => !open && closeAssignVehicleDialog()}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Assign Vehicle</DialogTitle>
                        <DialogDescription>Choose a vehicle to assign to {user.name}.</DialogDescription>
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
                                            {vehicle.current_driver_id && vehicle.current_driver_id !== user.id
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
