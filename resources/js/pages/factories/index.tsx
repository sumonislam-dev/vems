import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { confirmDelete } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useCan } from '@/lib/permissions';
import { BreadcrumbItem, DataTableColumn } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArchiveRestore, ArrowLeft, Edit, Eye, MapPin, Plus, Trash2 } from 'lucide-react';

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
    zone: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
    deleted_at?: string | null;
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
        zones: Array<{ id: number; name: string }>;
    };
    stats: {
        total: number;
        active: number;
        inactive: number;
        cities: number;
        deleted: number;
    };
    showDeleted: boolean;
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

export default function FactoriesIndex({ factories, filterOptions, stats, showDeleted, queryParams }: FactoriesPageProps) {
    const can = useCan();

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
                    {row.address && <span className="max-w-xs truncate text-sm text-muted-foreground">{row.address}</span>}
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
            key: 'zone',
            label: 'Zone',
            filterable: true,
            render: (value) => value?.name || 'No Zone',
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
            render: (_, row) =>
                showDeleted ? (
                    // Deleted factories can only be restored
                    can('delete-factories') && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                router.patch(route('factories.restore', row.id), {}, { preserveScroll: true });
                            }}
                            title="Restore factory"
                            className="cursor-pointer transition-all hover:scale-110 hover:bg-green-50 hover:text-green-600"
                        >
                            <ArchiveRestore className="mr-1 h-4 w-4" />
                            Restore
                        </Button>
                    )
                ) : (
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
                                    window.open(`https://www.google.com/maps?q=${row.latitude},${row.longitude}`, '_blank');
                                }}
                                title="View on map"
                                className="cursor-pointer transition-all hover:scale-110 hover:bg-green-50 hover:text-green-600"
                            >
                                <MapPin className="h-4 w-4" />
                            </Button>
                        )}
                        {can('edit-factories') && (
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
                        )}
                        {can('delete-factories') && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={async (e) => {
                                    e.stopPropagation();
                                    if (await confirmDelete(`Are you sure you want to delete ${row.name}?`)) {
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
                        )}
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
                            permission: 'create-factories',
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

                {/* Deleted factories are kept (soft-deleted) and can be restored */}
                {(showDeleted || stats.deleted > 0) && (
                    <div className="flex items-center justify-between gap-2 rounded-md border bg-muted/40 px-4 py-2 text-sm">
                        <span className="text-muted-foreground">
                            {showDeleted
                                ? 'Showing deleted factories. Restore one to use it again.'
                                : `${stats.deleted} deleted ${stats.deleted === 1 ? 'factory' : 'factories'}`}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.get(route('factories.index'), showDeleted ? {} : { filters: { deleted: ['only'] } })}
                        >
                            {showDeleted ? (
                                <>
                                    <ArrowLeft className="mr-1 h-4 w-4" />
                                    Back to factories
                                </>
                            ) : (
                                <>
                                    <Trash2 className="mr-1 h-4 w-4" />
                                    Show deleted
                                </>
                            )}
                        </Button>
                    </div>
                )}

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
