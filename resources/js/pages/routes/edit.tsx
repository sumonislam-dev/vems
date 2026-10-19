import { BaseForm, FormField, FormSelect, FormTextarea } from '@/base-components/base-form';
import { PageHeader } from '@/base-components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ReorderButtons } from '@/components/ui/reorder-buttons';
import { SearchableStopSelect } from '@/components/ui/searchable-stop-select';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Clock, MapPin, Route as RouteIcon, Save, Trash2 } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

interface Stop {
    id: number;
    name: string;
    description: string | null;
    latitude: number | null;
    longitude: number | null;
    zone_id?: number | null;
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

interface VehicleRoute {
    id: number;
    name: string;
    description: string | null;
    remarks: string | null;
    zone_id: number | null;
    route_stops: RouteStop[];
}

interface RouteStopForm {
    stop_id: number;
    arrival_time: string;
    departure_time: string;
}

interface Zone {
    id: number;
    name: string;
}

interface EditRouteProps {
    route: VehicleRoute;
    stops: Stop[];
    zones: Zone[];
}

export default function EditRoute({ route, stops: initialStops, zones }: EditRouteProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: route.name,
        description: route.description || '',
        remarks: route.remarks || '',
        zone_id: route.zone_id?.toString() || '',
        stops: [],
    });

    const [selectedStops, setSelectedStops] = useState<RouteStopForm[]>([]);
    const [stops, setStops] = useState<Stop[]>(initialStops);

    // Initialize selected stops from route data
    useEffect(() => {
        const initialStops: RouteStopForm[] = route.route_stops
            .sort((a, b) => a.stop_order - b.stop_order)
            .map((routeStop) => ({
                stop_id: routeStop.stop_id,
                arrival_time: routeStop.arrival_time || '',
                departure_time: routeStop.departure_time || '',
            }));
        setSelectedStops(initialStops);
        // @ts-expect-error Inertia form typing issue with arrays
        setData('stops', initialStops);
    }, [route, setData]);

    const addStop = () => {
        const newStop: RouteStopForm = {
            stop_id: 0,
            arrival_time: '',
            departure_time: '',
        };
        const updatedStops = [...selectedStops, newStop];
        setSelectedStops(updatedStops);
        // @ts-expect-error Inertia form typing issue with arrays
        setData('stops', updatedStops);
    };

    const removeStop = (index: number) => {
        const updatedStops = selectedStops.filter((_, i) => i !== index);
        setSelectedStops(updatedStops);
        // @ts-expect-error Inertia form typing issue with arrays
        setData('stops', updatedStops);
    };

    const updateStop = (index: number, field: keyof RouteStopForm, value: string | number) => {
        const updatedStops = selectedStops.map((stop, i) => (i === index ? { ...stop, [field]: value } : stop));
        setSelectedStops(updatedStops);
        // @ts-expect-error Inertia form typing issue with arrays
        setData('stops', updatedStops);
    };

    // Reorder functions
    const moveStop = (fromIndex: number, toIndex: number) => {
        const updatedStops = [...selectedStops];
        const [movedStop] = updatedStops.splice(fromIndex, 1);
        updatedStops.splice(toIndex, 0, movedStop);
        setSelectedStops(updatedStops);
        // @ts-expect-error Inertia form typing issue with arrays
        setData('stops', updatedStops);
    };

    const moveStopUp = (index: number) => {
        if (index > 0) {
            moveStop(index, index - 1);
        }
    };

    const moveStopDown = (index: number) => {
        if (index < selectedStops.length - 1) {
            moveStop(index, index + 1);
        }
    };

    const moveStopToTop = (index: number) => {
        if (index > 0) {
            moveStop(index, 0);
        }
    };

    const moveStopToBottom = (index: number) => {
        if (index < selectedStops.length - 1) {
            moveStop(index, selectedStops.length - 1);
        }
    };

    // Handle creating new stop
    const handleCreateStop = async (stopData: { name: string; description: string }) => {
        try {
            const response = await fetch('/api/stops', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ ...stopData, zone_id: data.zone_id || null }),
            });

            if (response.ok) {
                const result = await response.json();
                const newStop = result.data;
                setStops((prev) => [...prev, newStop]);
                return newStop;
            }
        } catch {
            // stop creation failure is surfaced via form state
        }
    };

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();

        put(`/routes/${route.id}`, {
            onSuccess: () => {
                // Success message will be handled by the backend
            },
        });
    };

    const getStopName = (stopId: number) => {
        const stop = stops.find((s) => s.id === stopId);
        return stop ? stop.name : 'Select a stop';
    };

    return (
        <AppLayout>
            <Head title={`Edit Route: ${route.name}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Edit Route: ${route.name}`}
                    description="Modify route details and stops configuration"
                    actions={[
                        {
                            label: 'Back to Routes',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: '/routes',
                            variant: 'outline',
                        },
                        {
                            label: 'View Route',
                            icon: <RouteIcon className="mr-2 h-4 w-4" />,
                            href: `/routes/${route.id}`,
                            variant: 'outline',
                        },
                    ]}
                />

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        {/* Route Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <RouteIcon className="h-5 w-5" />
                                    Route Information
                                </CardTitle>
                                <CardDescription>Update basic information about the route</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <BaseForm onSubmit={handleSubmit} className="space-y-4">
                                    <FormField
                                        label="Route Name"
                                        name="name"
                                        value={data.name as string}
                                        onChange={(value) => setData('name', value)}
                                        error={errors.name}
                                        placeholder="Enter route name..."
                                        required
                                    />

                                    <FormTextarea
                                        label="Description"
                                        name="description"
                                        value={data.description as string}
                                        onChange={(value) => setData('description', value)}
                                        error={errors.description}
                                        placeholder="Enter route description..."
                                        rows={3}
                                    />

                                    <FormTextarea
                                        label="Remarks"
                                        name="remarks"
                                        value={data.remarks as string}
                                        onChange={(value) => setData('remarks', value)}
                                        error={errors.remarks}
                                        placeholder="Enter any additional remarks..."
                                        rows={2}
                                    />

                                    <FormSelect
                                        label="Zone"
                                        name="zone_id"
                                        value={data.zone_id as string}
                                        onChange={(value) => setData('zone_id', value)}
                                        error={errors.zone_id}
                                        options={zones.map((zone) => ({ label: zone.name, value: zone.id.toString() }))}
                                        placeholder="Select zone..."
                                    />
                                </BaseForm>
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
                                    <Badge variant="secondary">{selectedStops.length} stops</Badge>
                                </CardTitle>
                                <CardDescription>Modify and arrange stops for this route. Drag to reorder.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {selectedStops.map((stop, index) => (
                                    <div key={index} className="space-y-4 rounded-lg border bg-muted/50 p-4">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline">Stop {index + 1}</Badge>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <ReorderButtons
                                                    index={index}
                                                    totalItems={selectedStops.length}
                                                    onMoveUp={() => moveStopUp(index)}
                                                    onMoveDown={() => moveStopDown(index)}
                                                    onMoveToTop={() => moveStopToTop(index)}
                                                    onMoveToBottom={() => moveStopToBottom(index)}
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => removeStop(index)}
                                                    className="h-8 w-8 p-0 text-red-600 hover:text-red-700"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                            <div className="space-y-2">
                                                <Label>Stop Location</Label>
                                                <SearchableStopSelect
                                                    stops={stops}
                                                    value={stop.stop_id}
                                                    onChange={(stopId) => updateStop(index, 'stop_id', stopId)}
                                                    onCreateStop={handleCreateStop}
                                                    placeholder="Search or add new stop..."
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label className="flex items-center gap-1">
                                                    <Clock className="h-3 w-3" />
                                                    Arrival Time
                                                </Label>
                                                <Input
                                                    type="time"
                                                    value={stop.arrival_time}
                                                    onChange={(e) => updateStop(index, 'arrival_time', e.target.value)}
                                                />
                                            </div>

                                            <div className="space-y-2">
                                                <Label className="flex items-center gap-1">
                                                    <Clock className="h-3 w-3" />
                                                    Departure Time
                                                </Label>
                                                <Input
                                                    type="time"
                                                    value={stop.departure_time}
                                                    onChange={(e) => updateStop(index, 'departure_time', e.target.value)}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ))}

                                <Button type="button" variant="outline" onClick={addStop} className="w-full">
                                    <MapPin className="mr-2 h-4 w-4" />
                                    Add Stop
                                </Button>
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
                                    <Label className="text-sm font-medium">Route Name</Label>
                                    <p className="text-sm text-muted-foreground">{(data.name as string) || 'No name entered'}</p>
                                </div>

                                <div className="space-y-2">
                                    <Label className="text-sm font-medium">Total Stops</Label>
                                    <p className="text-sm text-muted-foreground">{selectedStops.length} stops</p>
                                </div>

                                {selectedStops.length > 0 && (
                                    <div className="space-y-2">
                                        <Label className="text-sm font-medium">Route Sequence</Label>
                                        <div className="space-y-1">
                                            {selectedStops.map((stop, index) => (
                                                <div key={index} className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Badge variant="outline" className="text-xs">
                                                        {index + 1}
                                                    </Badge>
                                                    {getStopName(stop.stop_id)}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                <div className="space-y-2 pt-4">
                                    <Button onClick={handleSubmit} disabled={processing} className="w-full">
                                        <Save className="mr-2 h-4 w-4" />
                                        {processing ? 'Updating Route...' : 'Update Route'}
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => (window.location.href = `/routes/${route.id}`)}
                                        className="w-full"
                                    >
                                        Cancel Changes
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
