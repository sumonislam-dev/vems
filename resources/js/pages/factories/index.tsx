import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { BreadcrumbItem, DataTableColumn } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Edit, Eye, MapPin, Plus, Trash2 } from 'lucide-react';

interface Factory {
    id: number;
    account_id: string;
    name: string;
    status: 'active' | 'inactive';
    address: string | null;
    city: string | null;
    latitude: number | null;
    longitude: number | null;
    mileage_km: number | null;
    created_at: string;
    updated_at: string;
}

interface PaginatedFactories {
    data: Factory[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface FactoriesPageProps {
    factories: PaginatedFactories;
    filterOptions: {
        statuses: string[];
        cities: string[];
    };
    stats: {
        total: number;
        active: number;
        inactive: number;
        cities: number;
    };
    queryParams: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        filters?: Record<string, string | number>;
        per_page?: number;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Factories', href: '/factories' },
];

export default function FactoriesIndex({ factories, filterOptions, stats, queryParams }: FactoriesPageProps) {
    // Define table columns
    const columns: DataTableColumn<Factory>[] = [
        {
            key: 'account_id',
            label: 'Account ID',
            sortable: true,
            className: 'font-mono',
        },
        {
            key: 'name',
            label: 'Factory Name',
            sortable: true,
            render: (value, row) => (
                <div className="flex flex-col">
                    <span className="font-medium">{value}</span>
                    {row.address && (
                        <span className="max-w-xs truncate text-sm text-muted-foreground">{row.address}</span>
                    )}
                </div>
            ),
        },
        {
            key: 'city',
            label: 'City',
            sortable: true,
            render: (value) => value || '-',
        },
        {
            key: 'mileage_km',
            label: 'Distance (km)',
            sortable: true,
            render: (value) => (value ? `${Number(value).toFixed(2)} km` : '-'),
            className: 'text-right font-mono',
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            render: (value) => {
                const variants = {
                    active: 'default',
                    inactive: 'secondary',
                } as const;

                return (
                    <Badge variant={variants[value as keyof typeof variants]} className="capitalize">
                        {value}
                    </Badge>
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
            key: 'actions' as keyof Factory,
            label: 'Actions',
            render: (_, row) => (
                <div className="flex items-center space-x-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(route('factories.show', row.id));
                        }}
                        title="View factory"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    {row.latitude && row.longitude && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                window.open(
                                    `https://www.google.com/maps?q=${row.latitude},${row.longitude}`,
                                    '_blank'
                                );
                            }}
                            title="View on map"
                            className="cursor-pointer transition-all hover:scale-110 hover:bg-green-50 hover:text-green-600"
                        >
                            <MapPin className="h-4 w-4" />
                        </Button>
                    )}
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            router.visit(route('factories.edit', row.id));
                        }}
                        title="Edit factory"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            if (confirm(`Are you sure you want to delete ${row.name}?`)) {
                                router.delete(route('factories.destroy', row.id), {
                                    preserveScroll: true,
                                });
                            }
                        }}
                        title="Delete factory"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-red-50 hover:text-red-600"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppSidebarLayout breadcrumbs={breadcrumbs}>
            <Head title="Factories" />

            <div className="space-y-6">
                <PageHeader
                    title="Factories"
                    description="Manage factory locations and details."
                    actions={[
                        {
                            label: 'Add Factory',
                            icon: <Plus className="mr-2 h-4 w-4" />,
                            href: route('factories.create'),
                        },
                    ]}
                    stats={[
                        {
                            label: 'Total Factories',
                            value: stats.total,
                        },
                        {
                            label: 'Active',
                            value: stats.active,
                        },
                        {
                            label: 'Inactive',
                            value: stats.inactive,
                        },
                        {
                            label: 'Cities',
                            value: stats.cities,
                        },
                    ]}
                />

                {/* Data Table */}
                <ServerSideDataTable
                    data={factories}
                    columns={columns}
                    queryParams={queryParams}
                    filterOptions={filterOptions}
                    searchPlaceholder="Search factories by name, ID, city or address..."
                    emptyMessage="No factories found. Add your first factory to get started."
                    showSerialColumn
                />
            </div>
        </AppSidebarLayout>
    );
}
