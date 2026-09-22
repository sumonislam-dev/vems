import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, User } from '@/types';
import { Head, router } from '@inertiajs/react';
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

interface ShowUserProps {
    user: User;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/users' },
    { title: 'User Details', href: '#' },
];

export default function ShowUser({ user }: ShowUserProps) {
    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete ${user.name}?`)) {
            router.delete(route('users.destroy', user.id));
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
                            href: route('users.index'),
                            variant: 'outline',
                        },
                        {
                            label: 'Edit User',
                            icon: <Edit className="mr-2 h-4 w-4" />,
                            href: route('users.edit', user.id),
                        },
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
                                                {new Date(user.license_expiry_date) < new Date() && (
                                                    <Badge variant="destructive" className="ml-2 text-xs">Expired</Badge>
                                                )}
                                            </p>
                                        </div>
                                    )}

                                    {typeof user.total_trips_completed === 'number' && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">Total Trips</label>
                                            <p className="text-sm font-semibold">{user.total_trips_completed}</p>
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

                {/* Actions */}
                <Card>
                    <CardContent className="p-4">
                        <div className="flex flex-col sm:flex-row gap-3">
                            <Button
                                onClick={() => router.visit(route('users.edit', user.id))}
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
        </AppSidebarLayout>
    );
}
