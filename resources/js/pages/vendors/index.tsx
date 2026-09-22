import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, DataTableColumn, PaginatedData, Vendor } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Building2, Edit, Eye, Plus, Trash2, Users } from 'lucide-react';

interface VendorsPageProps {
    vendors: PaginatedData<Vendor>;
    stats: {
        total: number;
        active: number;
        inactive: number;
        with_vehicles: number;
    };
    queryParams: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        status?: string;
        per_page?: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vendors', href: '#' },
];

export default function VendorsIndex({ vendors, stats, queryParams }: VendorsPageProps) {
    // Define table columns
    const columns: DataTableColumn<Vendor>[] = [
        {
            key: 'name',
            label: 'Service Provider Name',
            sortable: true,
            render: (value, vendor) => (
                <div>
                    <span className="font-medium">{value}</span>
                    {vendor.vehicles_count && vendor.vehicles_count > 0 && (
                        <div className="text-xs text-muted-foreground">
                            {vendor.vehicles_count} vehicle{vendor.vehicles_count !== 1 ? 's' : ''}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'address',
            label: 'Address',
            render: (value) => (
                <span className="text-sm">{value || 'N/A'}</span>
            ),
        },
        {
            key: 'phone',
            label: 'Phone',
            render: (value) => (
                <span className="text-sm font-mono">{value || 'N/A'}</span>
            ),
        },
        {
            key: 'email',
            label: 'Email',
            render: (value) => (
                <span className="text-sm">{value || 'N/A'}</span>
            ),
        },
        {
            key: 'contact_persons',
            label: 'Contacts',
            render: (value, vendor) => (
                <div className="flex items-center space-x-1">
                    <Users className="h-4 w-4 text-muted-foreground" />
                    <span className="text-sm">
                        {vendor.contact_persons?.length || 0}
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (value) => (
                <Badge variant={value === 'active' ? 'default' : 'secondary'}>
                    {value === 'active' ? 'Active' : 'Inactive'}
                </Badge>
            ),
        },
        {
            key: 'actions',
            label: 'Actions',
            render: (_, vendor) => (
                <div className="flex space-x-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => router.visit(route('vendors.show', vendor.id))}
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => router.visit(route('vendors.edit', vendor.id))}
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleDelete(vendor)}
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-red-50 hover:text-red-600"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    const handleDelete = (vendor: Vendor) => {
        if (confirm(`Are you sure you want to delete ${vendor.name}?`)) {
            router.delete(route('vendors.destroy', vendor.id));
        }
    };

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Vendors" />

            <div className="space-y-6">
                <PageHeader
                    title="Vendors / Service Providers"
                    description="Manage your vehicle service providers and their contact information."
                    actions={[
                        {
                            label: 'Add Vendor',
                            icon: <Plus className="mr-2 h-4 w-4" />,
                            href: route('vendors.create'),
                        },
                    ]}
                />

                {/* Stats Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Vendors</CardTitle>
                            <Building2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Active Vendors</CardTitle>
                            <Badge variant="default" className="h-4 w-4 p-0"></Badge>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{stats.active}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Inactive Vendors</CardTitle>
                            <Badge variant="secondary" className="h-4 w-4 p-0"></Badge>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-gray-600">{stats.inactive}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">With Vehicles</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">{stats.with_vehicles}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Data Table */}
                <ServerSideDataTable
                    data={vendors}
                    columns={columns}
                    searchPlaceholder="Search vendors..."
                    queryParams={queryParams}
                    filters={[
                        {
                            key: 'status',
                            label: 'Status',
                            type: 'select',
                            options: [
                                { label: 'Active', value: 'active' },
                                { label: 'Inactive', value: 'inactive' },
                            ],
                        },
                    ]}
                    showSerialColumn
                />
            </div>
        </AppSidebarLayout>
    );
}
