import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { confirmDelete } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useCan } from '@/lib/permissions';
import { ColumnFilter, DataTableColumn } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Edit, Plus, Trash2 } from 'lucide-react';

interface Zone {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
    status: string;
    created_at: string;
}

interface Props {
    zones: {
        data: Zone[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    filterOptions: {
        statuses: string[];
    };
    queryParams: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        filters?: Record<string, string | string[]>;
        per_page?: number;
    };
}

export default function ZonesIndex({ zones, filterOptions, queryParams }: Props) {
    const can = useCan();

    const columns: DataTableColumn<Zone>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
        },
        {
            key: 'code',
            label: 'Code',
            sortable: true,
        },
        {
            key: 'status',
            label: 'Status',
            sortable: true,
            filterable: true,
            render: (value) => <Badge variant={value === 'active' ? 'default' : 'secondary'}>{value}</Badge>,
        },
        {
            key: 'created_at',
            label: 'Created',
            sortable: true,
            render: (value) => new Date(value).toLocaleDateString(),
        },
        {
            key: 'actions' as keyof Zone,
            label: 'Actions',
            render: (_, zone) => (
                <div className="flex items-center space-x-1">
                    {can('edit-zones') && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                router.visit(`/zones/${zone.id}/edit`);
                            }}
                            title="Edit zone"
                            className="cursor-pointer transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                        >
                            <Edit className="h-4 w-4" />
                        </Button>
                    )}
                    {can('delete-zones') && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={async (e) => {
                                e.stopPropagation();
                                if (await confirmDelete('Are you sure you want to delete this zone?')) {
                                    router.delete(`/zones/${zone.id}`);
                                }
                            }}
                            title="Delete zone"
                            className="cursor-pointer transition-all hover:scale-110 hover:bg-red-50 hover:text-red-600"
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    )}
                </div>
            ),
        },
    ];

    const filters: ColumnFilter[] = [
        {
            key: 'status',
            label: 'Status',
            type: 'multiselect',
            options: filterOptions.statuses.map((status) => ({
                label: status.charAt(0).toUpperCase() + status.slice(1),
                value: status,
            })),
        },
    ];

    return (
        <AppSidebarLayout>
            <Head title="Zones" />

            <div className="space-y-6">
                <PageHeader
                    title="Zones"
                    description="Manage the operational zones used to segment vehicles, drivers, routes and trips"
                    actions={[
                        {
                            label: 'Add Zone',
                            icon: <Plus className="mr-2 h-4 w-4" />,
                            href: '/zones/create',
                            permission: 'create-zones',
                        },
                    ]}
                />

                <ServerSideDataTable
                    data={zones}
                    columns={columns}
                    queryParams={queryParams}
                    filterOptions={filterOptions}
                    filters={filters}
                    searchPlaceholder="Search zones..."
                    showSerialColumn
                />
            </div>
        </AppSidebarLayout>
    );
}
