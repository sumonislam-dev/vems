import { PageHeader } from '@/base-components/page-header';
import { confirmDelete } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useCan } from '@/lib/permissions';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Building2, Car, Edit, ExternalLink, Mail, Phone, Trash2, User, Users } from 'lucide-react';

interface Vendor {
    id: number;
    name: string;
    address: string;
    phone: string;
    email: string;
    website: string;
    description: string;
    status: string;
    trade_license: string;
    trade_license_file: string | null;
    tin: string;
    tin_file: string | null;
    bin: string;
    bin_file: string | null;
    tax_return: string;
    tax_return_file: string | null;
    bank_details: string | null;
    contact_persons: {
        id: number;
        name: string;
        position: string;
        phone: string;
        email: string;
        is_primary: boolean;
        notes: string;
    }[];
    vehicles?: {
        id: number;
        name: string;
        type: string;
        registration_number: string;
        status: string;
        brand: string;
        model: string;
        color: string;
        is_active: boolean;
    }[];
    created_at: string;
    updated_at: string;
}

interface ShowVendorProps {
    vendor: Vendor;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vendors', href: '/vendors' },
    { title: 'Vendor Details', href: '#' },
];

export default function ShowVendor({ vendor }: ShowVendorProps) {
    const can = useCan();
    const canEdit = can('edit-vendors');
    const canDelete = can('delete-vendors');

    const handleDelete = async () => {
        if (await confirmDelete(`Are you sure you want to delete ${vendor.name}?`)) {
            router.delete(route('vendors.destroy', vendor.id));
        }
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title={`${vendor.name} - Vendor Details`} />

            <div className="space-y-6">
                <PageHeader
                    title={vendor.name}
                    description="Vendor details and contact information"
                    actions={[
                        {
                            label: 'Back',
                            icon: <ArrowLeft className="mr-1 h-4 w-4" />,
                            href: route('vendors.index'),
                            variant: 'outline',
                            className: 'h-8 text-xs',
                        },
                        {
                            label: 'Edit',
                            icon: <Edit className="mr-1 h-4 w-4" />,
                            href: route('vendors.edit', vendor.id),
                            className: 'h-8 text-xs',
                            permission: 'edit-vendors',
                        },
                    ]}
                    className="pb-4"
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    {/* Basic Information */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center text-base">
                                <Building2 className="h-4 w-4 mr-2" />
                                Basic Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="border rounded-lg overflow-x-auto">
                                <table className="w-full text-sm">
                                    <tbody className="divide-y">
                                        <tr>
                                            <td className="px-4 py-2 font-medium text-muted-foreground w-1/3">Service Provider</td>
                                            <td className="px-4 py-2">{vendor.name}</td>
                                        </tr>
                                        <tr>
                                            <td className="px-4 py-2 font-medium text-muted-foreground">Status</td>
                                            <td className="px-4 py-2">
                                                <Badge variant={vendor.status === 'active' ? 'default' : 'secondary'}>
                                                    {vendor.status === 'active' ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td className="px-4 py-2 font-medium text-muted-foreground">Phone</td>
                                            <td className="px-4 py-2 font-mono">{vendor.phone || 'N/A'}</td>
                                        </tr>
                                        <tr>
                                            <td className="px-4 py-2 font-medium text-muted-foreground">Email</td>
                                            <td className="px-4 py-2">{vendor.email || 'N/A'}</td>
                                        </tr>
                                        {vendor.website && (
                                            <tr>
                                                <td className="px-4 py-2 font-medium text-muted-foreground">Website</td>
                                                <td className="px-4 py-2">
                                                    <a
                                                        href={vendor.website}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-blue-600 hover:underline flex items-center"
                                                    >
                                                        {vendor.website}
                                                        <ExternalLink className="h-3 w-3 ml-1" />
                                                    </a>
                                                </td>
                                            </tr>
                                        )}
                                        <tr>
                                            <td className="px-4 py-2 font-medium text-muted-foreground">Address</td>
                                            <td className="px-4 py-2">{vendor.address || 'N/A'}</td>
                                        </tr>
                                        {vendor.description && (
                                            <tr>
                                                <td className="px-4 py-2 font-medium text-muted-foreground">Description</td>
                                                <td className="px-4 py-2">{vendor.description}</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Statistics */}
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center text-base">
                                <Users className="h-4 w-4 mr-2" />
                                Statistics
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex space-x-4 mb-4">
                                <div className="flex-1 border rounded-lg p-3 bg-blue-50/50">
                                    <div className="flex justify-between items-center">
                                        <div className="text-sm text-muted-foreground">Contact Persons</div>
                                        <div className="text-xl font-bold text-blue-600">{vendor.contact_persons?.length || 0}</div>
                                    </div>
                                </div>
                                <div className="flex-1 border rounded-lg p-3 bg-green-50/50">
                                    <div className="flex justify-between items-center">
                                        <div className="text-sm text-muted-foreground">Vehicles</div>
                                        <div className="text-xl font-bold text-green-600">{vendor.vehicles?.length || 0}</div>
                                    </div>
                                </div>
                            </div>

                            <div className="border rounded-lg overflow-x-auto">
                                <table className="w-full text-sm">
                                    <tbody className="divide-y">
                                        <tr>
                                            <td className="px-4 py-2 font-medium text-muted-foreground w-1/3">Vendor ID</td>
                                            <td className="px-4 py-2 font-mono">{vendor.id}</td>
                                        </tr>
                                        <tr>
                                            <td className="px-4 py-2 font-medium text-muted-foreground">Created At</td>
                                            <td className="px-4 py-2">
                                                {new Date(vendor.created_at).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'long',
                                                    day: 'numeric',
                                                })}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td className="px-4 py-2 font-medium text-muted-foreground">Last Updated</td>
                                            <td className="px-4 py-2">
                                                {new Date(vendor.updated_at).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'long',
                                                    day: 'numeric',
                                                })}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Contact Persons */}
                    {vendor.contact_persons && vendor.contact_persons.length > 0 && (
                        <Card className="lg:col-span-2">
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center text-base">
                                    <User className="h-4 w-4 mr-2" />
                                    Contact Persons ({vendor.contact_persons.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="border rounded-lg overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50">
                                            <tr>
                                                <th className="text-left font-medium px-4 py-2">Name</th>
                                                <th className="text-left font-medium px-4 py-2">Position</th>
                                                <th className="text-left font-medium px-4 py-2">Contact</th>
                                                <th className="text-left font-medium px-4 py-2">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {vendor.contact_persons.map((contact) => (
                                                <tr key={contact.id}>
                                                    <td className="px-4 py-2 font-medium">{contact.name}</td>
                                                    <td className="px-4 py-2 text-muted-foreground">{contact.position || '-'}</td>
                                                    <td className="px-4 py-2">
                                                        <div className="space-y-1">
                                                            {contact.phone && (
                                                                <div className="flex items-center text-xs">
                                                                    <Phone className="h-3 w-3 mr-1 text-muted-foreground" />
                                                                    <span className="font-mono">{contact.phone}</span>
                                                                </div>
                                                            )}
                                                            {contact.email && (
                                                                <div className="flex items-center text-xs">
                                                                    <Mail className="h-3 w-3 mr-1 text-muted-foreground" />
                                                                    <span>{contact.email}</span>
                                                                </div>
                                                            )}
                                                        </div>
                                                    </td>
                                                    <td className="px-4 py-2">
                                                        {contact.is_primary && (
                                                            <Badge variant="default" className="text-xs">Primary</Badge>
                                                        )}
                                                        {contact.notes && (
                                                            <div className="text-xs text-muted-foreground mt-1 max-w-xs truncate">
                                                                {contact.notes}
                                                            </div>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Associated Vehicles */}
                    {vendor.vehicles && vendor.vehicles.length > 0 && (
                        <Card className="lg:col-span-2">
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center text-base">
                                    <Car className="h-4 w-4 mr-2" />
                                    Associated Vehicles ({vendor.vehicles.length})
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="border rounded-lg overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50">
                                            <tr>
                                                <th className="text-left font-medium px-4 py-2">Vehicle</th>
                                                <th className="text-left font-medium px-4 py-2">Registration</th>
                                                <th className="text-left font-medium px-4 py-2">Color</th>
                                                <th className="text-left font-medium px-4 py-2">Status</th>
                                                <th className="text-left font-medium px-4 py-2">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {vendor.vehicles.map((vehicle) => (
                                                <tr key={vehicle.id}>
                                                    <td className="px-4 py-2 font-medium">{vehicle.brand} {vehicle.model}</td>
                                                    <td className="px-4 py-2 font-mono">{vehicle.registration_number}</td>
                                                    <td className="px-4 py-2">{vehicle.color || '-'}</td>
                                                    <td className="px-4 py-2">
                                                        <Badge variant={vehicle.is_active ? 'default' : 'secondary'} className="text-xs">
                                                            {vehicle.is_active ? 'Active' : 'Inactive'}
                                                        </Badge>
                                                    </td>
                                                    <td className="px-4 py-2">
                                                        <Link
                                                            href={route('vehicles.show', vehicle.id)}
                                                            className="text-xs text-blue-600 hover:underline"
                                                        >
                                                            View Details →
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Documents & Bank Details */}
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="flex items-center text-base">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 mr-2">
                                <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                            Documents & Bank Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {/* Compact Documents Table */}
                        <div className="border rounded-lg overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50">
                                    <tr>
                                        <th className="text-left font-medium px-4 py-2">Document Type</th>
                                        <th className="text-left font-medium px-4 py-2">Reference</th>
                                        <th className="text-left font-medium px-4 py-2">File</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    <tr>
                                        <td className="px-4 py-2 font-medium">Trade License</td>
                                        <td className="px-4 py-2">{vendor.trade_license || '-'}</td>
                                        <td className="px-4 py-2">
                                            {vendor.trade_license_file ? (
                                                <div className="flex items-center space-x-2">
                                                    <a
                                                        href={route('vendors.document', { vendor: vendor.id, field: 'trade_license_file', download: 1 })}
                                                        target="_blank"
                                                        className="text-xs text-blue-600 hover:underline flex items-center"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3 mr-1">
                                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                                            <polyline points="7 10 12 15 17 10"/>
                                                            <line x1="12" y1="15" x2="12" y2="3"/>
                                                        </svg>
                                                        Download
                                                    </a>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-6 text-xs px-2"
                                                        asChild
                                                    >
                                                        <a href={route('vendors.document', [vendor.id, 'trade_license_file'])} target="_blank">
                                                            <ExternalLink className="h-3 w-3 mr-1" />
                                                            Show
                                                        </a>
                                                    </Button>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-gray-500">No file</span>
                                            )}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td className="px-4 py-2 font-medium">TIN</td>
                                        <td className="px-4 py-2">{vendor.tin || '-'}</td>
                                        <td className="px-4 py-2">
                                            {vendor.tin_file ? (
                                                <div className="flex items-center space-x-2">
                                                    <a
                                                        href={route('vendors.document', { vendor: vendor.id, field: 'tin_file', download: 1 })}
                                                        target="_blank"
                                                        className="text-xs text-blue-600 hover:underline flex items-center"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3 mr-1">
                                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                                            <polyline points="7 10 12 15 17 10"/>
                                                            <line x1="12" y1="15" x2="12" y2="3"/>
                                                        </svg>
                                                        Download
                                                    </a>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-6 text-xs px-2"
                                                        asChild
                                                    >
                                                        <a href={route('vendors.document', [vendor.id, 'tin_file'])} target="_blank">
                                                            <ExternalLink className="h-3 w-3 mr-1" />
                                                            Show
                                                        </a>
                                                    </Button>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-gray-500">No file</span>
                                            )}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td className="px-4 py-2 font-medium">BIN</td>
                                        <td className="px-4 py-2">{vendor.bin || '-'}</td>
                                        <td className="px-4 py-2">
                                            {vendor.bin_file ? (
                                                <div className="flex items-center space-x-2">
                                                    <a
                                                        href={route('vendors.document', { vendor: vendor.id, field: 'bin_file', download: 1 })}
                                                        target="_blank"
                                                        className="text-xs text-blue-600 hover:underline flex items-center"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3 mr-1">
                                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                                            <polyline points="7 10 12 15 17 10"/>
                                                            <line x1="12" y1="15" x2="12" y2="3"/>
                                                        </svg>
                                                        Download
                                                    </a>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-6 text-xs px-2"
                                                        asChild
                                                    >
                                                        <a href={route('vendors.document', [vendor.id, 'bin_file'])} target="_blank">
                                                            <ExternalLink className="h-3 w-3 mr-1" />
                                                            Show
                                                        </a>
                                                    </Button>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-gray-500">No file</span>
                                            )}
                                        </td>
                                    </tr>

                                    <tr>
                                        <td className="px-4 py-2 font-medium">Tax Return</td>
                                        <td className="px-4 py-2">{vendor.tax_return || '-'}</td>
                                        <td className="px-4 py-2">
                                            {vendor.tax_return_file ? (
                                                <div className="flex items-center space-x-2">
                                                    <a
                                                        href={route('vendors.document', { vendor: vendor.id, field: 'tax_return_file', download: 1 })}
                                                        target="_blank"
                                                        className="text-xs text-blue-600 hover:underline flex items-center"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3 mr-1">
                                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                                            <polyline points="7 10 12 15 17 10"/>
                                                            <line x1="12" y1="15" x2="12" y2="3"/>
                                                        </svg>
                                                        Download
                                                    </a>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-6 text-xs px-2"
                                                        asChild
                                                    >
                                                        <a href={route('vendors.document', [vendor.id, 'tax_return_file'])} target="_blank">
                                                            <ExternalLink className="h-3 w-3 mr-1" />
                                                            Show
                                                        </a>
                                                    </Button>
                                                </div>
                                            ) : (
                                                <span className="text-xs text-gray-500">No file</span>
                                            )}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        {/* Bank Details */}
                        {vendor.bank_details && (
                            <div className="mt-4">
                                <h3 className="text-sm font-medium mb-1">Bank Details</h3>
                                <div className="border rounded-lg p-3">
                                    <p className="text-sm whitespace-pre-line">{vendor.bank_details}</p>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Actions */}
                {(canEdit || canDelete) && (
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-base">Actions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex space-x-2">
                                {canEdit && (
                                    <Button
                                        onClick={() => router.visit(route('vendors.edit', vendor.id))}
                                        className="flex items-center h-8 text-xs"
                                        size="sm"
                                    >
                                        <Edit className="mr-1 h-3 w-3" />
                                        Edit Vendor
                                    </Button>
                                )}
                                {canDelete && (
                                    <Button
                                        variant="destructive"
                                        onClick={handleDelete}
                                        className="flex items-center h-8 text-xs"
                                        size="sm"
                                        disabled={vendor.vehicles && vendor.vehicles.length > 0}
                                    >
                                        <Trash2 className="mr-1 h-3 w-3" />
                                        Delete Vendor
                                    </Button>
                                )}
                            </div>
                            {canDelete && vendor.vehicles && vendor.vehicles.length > 0 && (
                                <p className="text-xs text-muted-foreground mt-2">
                                    Cannot delete vendor with associated vehicles. Remove vehicle associations first.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppSidebarLayout>
    );
}
