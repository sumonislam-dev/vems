import { ServerSideDataTable } from '@/base-components/base-data-table';
import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppSidebarLayout from '@/layouts/app/app-sidebar-layout';
import { ColumnFilter, DataTableColumn } from '@/types';
import { Head } from '@inertiajs/react';
import { Edit, Eye, Plus, Trash2, MapPin, Route as RouteIcon } from 'lucide-react';

interface VehicleRoute {
    id: number;
    name: string;
    description: string | null;
    remarks: string | null;
    total_distance: number | null;
    route_stops_count: number;
    created_at: string;
    updated_at: string;
    route_stops?: RouteStop[];
}

interface RouteStop {
    id: number;
    vehicle_route_id: number;
    stop_id: number;
    stop_order: number;
    arrival_time: string | null;
    departure_time: string | null;
    stop: Stop;
}

interface Stop {
    id: number;
    name: string;
    description: string | null;
    latitude: number | null;
    longitude: number | null;
}

interface PaginatedRoutes {
    data: VehicleRoute[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

interface RoutesPageProps {
    routes: PaginatedRoutes;
    stats: {
        total: number;
        total_stops: number;
        routes_with_stops: number;
        avg_stops_per_route: number;
    };
    queryParams: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        per_page?: number;
    };
}

export default function RoutesIndex({ routes, stats, queryParams }: RoutesPageProps) {
    // Define table columns
    const columns: DataTableColumn<VehicleRoute>[] = [
        {
            key: 'id',
            label: 'ID',
            sortable: true,
            className: 'w-16',
        },
        {
            key: 'name',
            label: 'Route Name',
            sortable: true,
            render: (value: string, row: VehicleRoute) => (
                <div className="flex flex-col">
                    <span className="font-medium flex items-center gap-2">
                        <RouteIcon className="h-4 w-4 text-blue-600" />
                        {value}
                    </span>
                    {row.description && (
                        <span className="max-w-xs truncate text-sm text-muted-foreground">{row.description}</span>
                    )}
                </div>
            ),
        },
        {
            key: 'route_stops_count',
            label: 'Total Stops',
            sortable: true,
            render: (value: number) => (
                <Badge variant="secondary" className="flex items-center gap-1 w-fit">
                    <MapPin className="h-3 w-3" />
                    {value} stops
                </Badge>
            ),
            className: 'text-center',
        },
        {
            key: 'total_distance',
            label: 'Distance',
            sortable: true,
            render: (value: number | string | null) => value ? (
                <Badge variant="outline" className="flex items-center gap-1 w-fit">
                    <RouteIcon className="h-3 w-3" />
                    {Number(value).toFixed(2)} km
                </Badge>
            ) : (
                <span className="text-muted-foreground text-sm">Not calculated</span>
            ),
            className: 'text-center',
        },
        {
            key: 'remarks',
            label: 'Remarks',
            render: (value: string | null) => value ? (
                <span className="max-w-xs truncate text-sm">{value}</span>
            ) : (
                <span className="text-muted-foreground text-sm">No remarks</span>
            ),
        },
        {
            key: 'created_at',
            label: 'Created',
            sortable: true,
            render: (value: string) =>
                new Date(value).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                }),
            className: 'text-muted-foreground',
        },
        {
            key: 'actions',
            label: 'Actions',
            render: (_: unknown, row: VehicleRoute) => (
                <div className="flex items-center space-x-1">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            window.location.href = `/routes/${row.id}`;
                        }}
                        title="View route"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-blue-50 hover:text-blue-600"
                    >
                        <Eye className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            window.location.href = `/routes/${row.id}/edit`;
                        }}
                        title="Edit route"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-indigo-50 hover:text-indigo-600"
                    >
                        <Edit className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            if (confirm(`Are you sure you want to delete the route "${row.name}"?`)) {
                                // Delete functionality would go here
                                alert(`Delete route: ${row.name}`);
                            }
                        }}
                        title="Delete route"
                        className="cursor-pointer transition-all hover:scale-110 hover:bg-red-50 hover:text-red-600"
                    >
                        <Trash2 className="h-4 w-4" />
                    </Button>
                </div>
            ),
            className: 'w-32',
        },
    ];

    // Define available filters
    const availableFilters: ColumnFilter[] = [];

    // Stats cards for the header
    const statsCards = [
        {
            title: 'Total Routes',
            value: stats.total.toLocaleString(),
            icon: <RouteIcon className="h-4 w-4" />,
            color: 'blue',
        },
        {
            title: 'Total Stops',
            value: stats.total_stops.toLocaleString(),
            icon: <MapPin className="h-4 w-4" />,
            color: 'green',
        },
        {
            title: 'Routes with Stops',
            value: stats.routes_with_stops.toLocaleString(),
            icon: <RouteIcon className="h-4 w-4" />,
            color: 'purple',
        },
        {
            title: 'Avg Stops/Route',
            value: stats.avg_stops_per_route.toString(),
            icon: <MapPin className="h-4 w-4" />,
            color: 'orange',
        },
    ];

    return (
        <AppSidebarLayout>
            <Head title="Routes" />

            <div className="space-y-6">
                <PageHeader
                    title="Routes Management"
                    description="Manage vehicle routes and their stops"
                    actions={[
                        {
                            label: "Create Route",
                            icon: <Plus className="mr-2 h-4 w-4" />,
                            href: "/routes/create"
                        }
                    ]}
                />

                {/* Stats Cards */}
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {statsCards.map((stat, index) => (
                        <div key={index} className="rounded-lg border bg-card p-4">
                            <div className="flex items-center space-x-2">
                                <div className={`rounded-md p-2 bg-${stat.color}-100 text-${stat.color}-600`}>
                                    {stat.icon}
                                </div>
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">{stat.title}</p>
                                    <p className="text-2xl font-bold">{stat.value}</p>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Data Table */}
                <ServerSideDataTable
                    data={routes}
                    columns={columns}
                    searchable
                    searchPlaceholder="Search routes..."
                    filters={availableFilters}
                    queryParams={queryParams}
                    onRowClick={(route) => {
                        window.location.href = `/routes/${route.id}`;
                    }}
                />
            </div>
        </AppSidebarLayout>
    );
}
