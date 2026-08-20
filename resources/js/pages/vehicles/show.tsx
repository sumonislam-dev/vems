import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, Vehicle } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Edit, Trash2 } from 'lucide-react';

type Assignment = {
    id: number
    driver?: { id: number; name: string; email?: string } | null
    assigner?: { id: number; name: string } | null
    started_at: string
    ended_at: string | null
    is_current: boolean
}

interface ShowVehicleProps {
    vehicle: Vehicle;
    assignments?: Assignment[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vehicles', href: '/vehicles' },
    { title: 'Vehicle Details', href: '#' },
];

export default function ShowVehicle({ vehicle, assignments = [] }: ShowVehicleProps) {
    const handleDelete = () => {
        if (confirm(`Are you sure you want to delete ${vehicle.brand} ${vehicle.model}?`)) {
            router.delete(route('vehicles.destroy', vehicle.id));
        }
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`${vehicle.brand} ${vehicle.model} - Vehicle Details`} />

            <div className="space-y-6">
                <PageHeader
                    title={`${vehicle.brand} ${vehicle.model}`}
                    description="Vehicle details and information."
                    actions={[
                        {
                            label: 'Back to Vehicles',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: route('vehicles.index'),
                            variant: 'outline',
                        },
                        {
                            label: 'Edit Vehicle',
                            icon: <Edit className="mr-2 h-4 w-4" />,
                            href: route('vehicles.edit', vehicle.id),
                        },
                    ]}
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Basic Vehicle Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Basic Vehicle Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Brand</label>
                                    <p className="text-sm font-medium">{vehicle.brand}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Model</label>
                                    <p className="text-sm">{vehicle.model}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Color</label>
                                    <div className="flex items-center space-x-2">
                                        <Badge variant="outline" className="capitalize">
                                            {vehicle.color}
                                        </Badge>
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Registration Number</label>
                                    <p className="font-mono text-sm font-semibold">{vehicle.registration_number}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Vendor / Service Provider</label>
                                    <p className="text-sm">
                                        {typeof vehicle.vendor === 'object' && vehicle.vendor !== null
                                            ? vehicle.vendor.name
                                            : (vehicle.vendor || 'N/A')}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Status</label>
                                    <div>
                                        <Badge variant={vehicle.is_active ? 'default' : 'secondary'}>
                                            {vehicle.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Additional Vehicle Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Additional Vehicle Details</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Manufacture Year</label>
                                    <p className="text-sm">{vehicle.manufacture_year || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Fuel Type</label>
                                    <p className="text-sm capitalize">{vehicle.fuel_type || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Engine Number</label>
                                    <p className="text-sm font-mono">{vehicle.engine_number || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Chassis Number</label>
                                    <p className="text-sm font-mono">{vehicle.chassis_number || 'N/A'}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Tax Token Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Tax Token Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Tax Token Number</label>
                                    <p className="text-sm font-mono">{vehicle.tax_token_number || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Tax Token Last Date</label>
                                    <p className="text-sm">
                                        {vehicle.tax_token_last_date
                                            ? new Date(vehicle.tax_token_last_date).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            })
                                            : 'N/A'
                                        }
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Alert Status</label>
                                    <Badge variant={vehicle.tax_token_alert_enabled ? 'default' : 'secondary'}>
                                        {vehicle.tax_token_alert_enabled ? 'Enabled' : 'Disabled'}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Fitness Certificate Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Fitness Certificate Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Fitness Certificate Number</label>
                                    <p className="text-sm font-mono">{vehicle.fitness_certificate_number || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Fitness Certificate Last Date</label>
                                    <p className="text-sm">
                                        {vehicle.fitness_certificate_last_date
                                            ? new Date(vehicle.fitness_certificate_last_date).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            })
                                            : 'N/A'
                                        }
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Alert Status</label>
                                    <Badge variant={vehicle.fitness_alert_enabled ? 'default' : 'secondary'}>
                                        {vehicle.fitness_alert_enabled ? 'Enabled' : 'Disabled'}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    {/* Insurance Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Insurance Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Insurance Type</label>
                                    <p className="text-sm capitalize">
                                        {vehicle.insurance_type ? vehicle.insurance_type.replace('_', ' ') : 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Insurance Policy Number</label>
                                    <p className="text-sm font-mono">{vehicle.insurance_policy_number || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Insurance Company</label>
                                    <p className="text-sm">{vehicle.insurance_company || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Insurance Last Date</label>
                                    <p className="text-sm">
                                        {vehicle.insurance_last_date
                                            ? new Date(vehicle.insurance_last_date).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            })
                                            : 'N/A'
                                        }
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Alert Status</label>
                                    <Badge variant={vehicle.insurance_alert_enabled ? 'default' : 'secondary'}>
                                        {vehicle.insurance_alert_enabled ? 'Enabled' : 'Disabled'}
                                    </Badge>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Owner Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Owner Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Registration Certificate Number</label>
                                    <p className="text-sm font-mono">{vehicle.registration_certificate_number || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Owner Name</label>
                                    <p className="text-sm font-medium">{vehicle.owner_name || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Owner Phone</label>
                                    <p className="text-sm">{vehicle.owner_phone || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Owner Email</label>
                                    <p className="text-sm">{vehicle.owner_email || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Owner NID</label>
                                    <p className="text-sm font-mono">{vehicle.owner_nid || 'N/A'}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Owner Address</label>
                                    <p className="text-sm">{vehicle.owner_address || 'N/A'}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* System Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>System Information</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Vehicle ID</label>
                                    <p className="font-mono text-sm">{vehicle.id}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Alert Days Before Expiry</label>
                                    <p className="text-sm">{vehicle.alert_days_before} days</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Created At</label>
                                    <p className="text-sm">
                                        {vehicle.created_at
                                            ? new Date(vehicle.created_at).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })
                                            : 'N/A'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">Last Updated</label>
                                    <p className="text-sm">
                                        {vehicle.updated_at
                                            ? new Date(vehicle.updated_at).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })
                                            : 'N/A'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Vendor / Service Provider Information */}
                    {vehicle.vendor && typeof vehicle.vendor === 'object' && 'name' in vehicle.vendor && (
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle>Vendor / Service Provider Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">Service Provider Name</label>
                                        <p className="text-sm font-medium">{vehicle.vendor.name}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">Status</label>
                                        <Badge variant={vehicle.vendor.status === 'active' ? 'default' : 'secondary'}>
                                            {vehicle.vendor.status === 'active' ? 'Active' : 'Inactive'}</Badge>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">Phone</label>
                                        <p className="text-sm">{vehicle.vendor.phone || 'N/A'}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">Email</label>
                                        <p className="text-sm">{vehicle.vendor.email || 'N/A'}</p>
                                    </div>
                                    <div className="md:col-span-2">
                                        <label className="text-sm font-medium text-muted-foreground">Address</label>
                                        <p className="text-sm">{vehicle.vendor.address || 'N/A'}</p>
                                    </div>
                                    {vehicle.vendor.website && (
                                        <div className="md:col-span-2">
                                            <label className="text-sm font-medium text-muted-foreground">Website</label>
                                            <p className="text-sm">
                                                <a href={vehicle.vendor.website} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">
                                                    {vehicle.vendor.website}
                                                </a>
                                            </p>
                                        </div>
                                    )}
                                    {vehicle.vendor.description && (
                                        <div className="md:col-span-2">
                                            <label className="text-sm font-medium text-muted-foreground">Description</label>
                                            <p className="text-sm">{vehicle.vendor.description}</p>
                                        </div>
                                    )}
                                </div>

                                {/* Contact Persons */}
                                {vehicle.vendor.contact_persons && vehicle.vendor.contact_persons.length > 0 && (
                                    <div className="mt-6">
                                        <h4 className="text-sm font-medium text-muted-foreground mb-3">Contact Persons</h4>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            {vehicle.vendor.contact_persons.map((contact) => (
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

                    {/* Driver Information */}
                    {vehicle.driver && (
                        <Card className="lg:col-span-2">
                            <CardHeader>
                                <CardTitle>Driver Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">Driver Name</label>
                                        <p className="text-sm font-medium">{vehicle.driver.name}</p>
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">Driver Type</label>
                                        <Badge variant="outline">
                                            {vehicle.driver.user_type?.toUpperCase() || 'DRIVER'}
                                        </Badge>
                                    </div>
                                    {vehicle.driver.driving_license_no && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">Driving License</label>
                                            <p className="text-sm font-mono">{vehicle.driver.driving_license_no}</p>
                                        </div>
                                    )}
                                    {vehicle.driver.nid_number && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">NID Number</label>
                                            <p className="text-sm font-mono">{vehicle.driver.nid_number}</p>
                                        </div>
                                    )}
                                    {vehicle.driver.email && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">Email</label>
                                            <p className="text-sm">{vehicle.driver.email}</p>
                                        </div>
                                    )}
                                    {vehicle.driver.official_phone && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">Official Phone</label>
                                            <p className="text-sm">{vehicle.driver.official_phone}</p>
                                        </div>
                                    )}
                                    {vehicle.driver.personal_phone && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">Personal Phone</label>
                                            <p className="text-sm">{vehicle.driver.personal_phone}</p>
                                        </div>
                                    )}
                                    {vehicle.driver.present_address && (
                                        <div className="lg:col-span-2">
                                            <label className="text-sm font-medium text-muted-foreground">Present Address</label>
                                            <p className="text-sm">{vehicle.driver.present_address}</p>
                                        </div>
                                    )}
                                    {vehicle.driver.emergency_contact_name && (
                                        <div>
                                            <label className="text-sm font-medium text-muted-foreground">Emergency Contact</label>
                                            <p className="text-sm">{vehicle.driver.emergency_contact_name}</p>
                                            {vehicle.driver.emergency_contact_phone && (
                                                <p className="text-xs text-muted-foreground">{vehicle.driver.emergency_contact_phone}</p>
                                            )}
                                            {vehicle.driver.emergency_contact_relation && (
                                                <p className="text-xs text-muted-foreground">({vehicle.driver.emergency_contact_relation})</p>
                                            )}
                                        </div>
                                    )}
                                    <div>
                                        <label className="text-sm font-medium text-muted-foreground">Status</label>
                                        <Badge variant={vehicle.driver.status === 'active' ? 'default' : 'secondary'}>
                                            {vehicle.driver.status === 'active' ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Driver Assignment History */}
                {assignments.length > 0 && (
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Driver Assignment History</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-x-auto rounded border">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left">Driver</th>
                                            <th className="px-3 py-2 text-left">Started</th>
                                            <th className="px-3 py-2 text-left">Ended</th>
                                            <th className="px-3 py-2 text-left">Assigned By</th>
                                            <th className="px-3 py-2 text-left">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {assignments.map((a) => (
                                            <tr key={a.id} className="border-t">
                                                <td className="px-3 py-2">{a.driver?.name ?? '-'}</td>
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
                    <CardHeader>
                        <CardTitle>Actions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex space-x-4">
                            <Button
                                onClick={() => router.visit(route('vehicles.edit', vehicle.id))}
                                className="flex items-center"
                            >
                                <Edit className="mr-2 h-4 w-4" />
                                Edit Vehicle
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={handleDelete}
                                className="flex items-center"
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete Vehicle
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppSidebarLayout>
    );
}
