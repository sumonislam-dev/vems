import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { confirmDelete } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { useCan } from '@/lib/permissions';
import { DataTableColumn } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Edit, Plus, Trash2 } from 'lucide-react';

interface Stop {
    id: number;
    name: string;
    description: string | null;
    latitude: number | null;
    longitude: number | null;
    zone_id: number | null;
    zone: { id: number; name: string } | null;
    route_stops_count: number;
    created_at: string;
}

interface Props {
    stops: {
        data: Stop[];
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
    queryParams: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        per_page?: number;
    };
}

export default function StopsIndex({ stops, queryParams }: Props) {
    const can = useCan();

    const columns: DataTableColumn<Stop>[] = [
        {
            key: 'name',
            label: 'Name',
            sortable: true,
        },
        {
            key: 'description',
            label: 'Description',
            render: (value) => <span className="text-sm text-muted-foreground">{value || '—'}</span>,
        },
        {
            key: 'zone' as keyof Stop,
            label: 'Zone',
            render: (_, stop) => (stop.zone ? <Badge variant="outline">{stop.zone.name}</Badge> : <span className="text-muted-foreground">Shared</span>),
        },
        {
            key: 'route_stops_count',
            label: 'Used on Routes',
            sortable: true,
            render: (value) => <Badge variant="secondary">{value}</Badge>,
        },
        {
            key: 'created_at',
            label: 'Created',
            sortable: true,
            render: (value) => new Date(value).toLocaleDateString(),
        },
        {
            key: 'actions' as keyof Stop,
            label: 'Actions',
            render: (_, stop) => (
                <div className="flex items-center space-x-1">
                    {can('edit-stops') && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={(e) => {
                                e.stopPropagation();
                                router.visit(`/stops/${stop.id}/edit`);
                            }}
                            title="Edit stop"
                            className="cursor-pointer transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                        >
                            <Edit className="h-4 w-4" />
                        </Button>
                    )}
                    {can('delete-stops') && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={async (e) => {
                                e.stopPropagation();
                                if (await confirmDelete('Are you sure you want to delete this stop?')) {
                                    router.delete(`/stops/${stop.id}`);
                                }
                            }}
                            title="Delete stop"
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
        <AppSidebarLayout>
            <Head title="Stops" />

            <div className="space-y-6">
                <PageHeader
                    title="Stops"
                    description="Manage the pickup/drop-off locations used to build routes and trips"
                    actions={[
                        {
                            label: 'Add Stop',
                            icon: <Plus className="mr-2 h-4 w-4" />,
                            href: '/stops/create',
                            permission: 'create-stops',
                        },
                    ]}
                />

                <ServerSideDataTable data={stops} columns={columns} queryParams={queryParams} searchPlaceholder="Search stops..." showSerialColumn />
            </div>
        </AppSidebarLayout>
    );
}
