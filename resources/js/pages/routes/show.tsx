import { Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, Edit, Route as RouteIcon, MapPin, Clock } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';

interface VehicleRoute {
    id: number;
    name: string;
    description: string | null;
    remarks: string | null;
    total_distance: number | null;
    created_at: string;
    updated_at: string;
    route_stops: RouteStop[];
}

interface RouteStop {
    id: number;
    vehicle_route_id: number;
    stop_id: number;
    stop_order: number;
    arrival_time: string | null;
    departure_time: string | null;
    distance_from_previous: number | null;
    cumulative_distance: number | null;
    stop: Stop;
}

interface Stop {
    id: number;
    name: string;
    description: string | null;
    latitude: number | null;
    longitude: number | null;
}

interface ShowRouteProps {
    route: VehicleRoute;
}

export default function ShowRoute({ route }: ShowRouteProps) {
    return (
        <AppLayout>
            <Head title={`Route: ${route.name}`} />

            <div className="space-y-6">
                <PageHeader
                    title={route.name}
                    description={`Route details and stops`}
                    actions={[
                        {
                            label: "Back to Routes",
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: "/routes",
                            variant: "outline"
                        },
                        {
                            label: "Edit Route",
                            icon: <Edit className="mr-2 h-4 w-4" />,
                            href: `/routes/${route.id}/edit`
                        }
                    ]}
                />

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        {/* Route Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <RouteIcon className="h-5 w-5" />
                                    Route Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <h3 className="font-medium">Name</h3>
                                    <p className="text-muted-foreground">{route.name}</p>
                                </div>

                                {route.description && (
                                    <div>
                                        <h3 className="font-medium">Description</h3>
                                        <p className="text-muted-foreground">{route.description}</p>
                                    </div>
                                )}

                                {route.remarks && (
                                    <div>
                                        <h3 className="font-medium">Remarks</h3>
                                        <p className="text-muted-foreground">{route.remarks}</p>
                                    </div>
                                )}

                                <div>
                                    <h3 className="font-medium">Total Distance</h3>
                                    <p className="text-muted-foreground">
                                        {route.total_distance
                                            ? `${Number(route.total_distance).toFixed(2)} km`
                                            : 'Not calculated'
                                        }
                                    </p>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <h3 className="font-medium">Created</h3>
                                        <p className="text-muted-foreground">
                                            {new Date(route.created_at).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </p>
                                    </div>
                                    <div>
                                        <h3 className="font-medium">Last Updated</h3>
                                        <p className="text-muted-foreground">
                                            {new Date(route.updated_at).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })}
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Route Stops */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <MapPin className="h-5 w-5" />
                                        Route Stops
                                    </div>
                                    <Badge variant="secondary">
                                        {route.route_stops.length} stops
                                    </Badge>
                                </CardTitle>
                                <CardDescription>
                                    Ordered sequence of stops for this route
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {route.route_stops.length > 0 ? (
                                    <div className="space-y-4">
                                        {route.route_stops
                                            .sort((a, b) => a.stop_order - b.stop_order)
                                            .map((routeStop) => (
                                                <div key={routeStop.id} className="border rounded-lg p-4 bg-muted/50">
                                                    <div className="flex items-start justify-between">
                                                        <div className="flex items-center gap-3">
                                                            <Badge variant="outline" className="flex items-center gap-1">
                                                                <MapPin className="h-3 w-3" />
                                                                Stop {routeStop.stop_order}
                                                            </Badge>
                                                            <div>
                                                                <h4 className="font-medium">{routeStop.stop.name}</h4>
                                                                {routeStop.stop.description && (
                                                                    <p className="text-sm text-muted-foreground">
                                                                        {routeStop.stop.description}
                                                                    </p>
                                                                )}
                                                            </div>
                                                        </div>

                                                        <div className="text-right text-sm space-y-1">
                                                            {routeStop.distance_from_previous !== null && routeStop.stop_order > 1 && (
                                                                <div className="flex items-center gap-1 text-muted-foreground">
                                                                    <RouteIcon className="h-3 w-3" />
                                                                    From previous: {Number(routeStop.distance_from_previous).toFixed(2)} km
                                                                </div>
                                                            )}
                                                            {routeStop.cumulative_distance !== null && (
                                                                <div className="flex items-center gap-1 text-muted-foreground">
                                                                    <MapPin className="h-3 w-3" />
                                                                    Total: {Number(routeStop.cumulative_distance).toFixed(2)} km
                                                                </div>
                                                            )}
                                                            {routeStop.arrival_time && (
                                                                <div className="flex items-center gap-1 text-muted-foreground">
                                                                    <Clock className="h-3 w-3" />
                                                                    Arrival: {routeStop.arrival_time}
                                                                </div>
                                                            )}
                                                            {routeStop.departure_time && (
                                                                <div className="flex items-center gap-1 text-muted-foreground">
                                                                    <Clock className="h-3 w-3" />
                                                                    Departure: {routeStop.departure_time}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                    </div>
                                ) : (
                                    <div className="text-center text-muted-foreground py-8">
                                        <MapPin className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                        <p>No stops defined for this route</p>
                                        <Button variant="outline" className="mt-4" asChild>
                                            <a href={`/routes/${route.id}/edit`}>Add Stops</a>
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Summary Card */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Route Summary</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <h4 className="text-sm font-medium">Total Stops</h4>
                                    <p className="text-2xl font-bold">{route.route_stops.length}</p>
                                </div>

                                {route.route_stops.length > 0 && (
                                    <>
                                        <div className="space-y-2">
                                            <h4 className="text-sm font-medium">First Stop</h4>
                                            <p className="text-sm text-muted-foreground">
                                                {route.route_stops.find(s => s.stop_order === 1)?.stop.name || 'N/A'}
                                            </p>
                                        </div>

                                        <div className="space-y-2">
                                            <h4 className="text-sm font-medium">Last Stop</h4>
                                            <p className="text-sm text-muted-foreground">
                                                {route.route_stops
                                                    .sort((a, b) => b.stop_order - a.stop_order)[0]?.stop.name || 'N/A'}
                                            </p>
                                        </div>

                                        <div className="space-y-2">
                                            <h4 className="text-sm font-medium">Stops with Times</h4>
                                            <p className="text-sm text-muted-foreground">
                                                {route.route_stops.filter(s => s.arrival_time || s.departure_time).length}
                                                {' of '} {route.route_stops.length}
                                            </p>
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
