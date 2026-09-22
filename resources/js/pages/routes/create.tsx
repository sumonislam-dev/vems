import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, Plus, Route as RouteIcon, MapPin, Clock, Trash2, Map } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHeader } from '@/base-components/page-header';
import { EnhancedStopSelect } from '@/components/ui/enhanced-stop-select';
import { ReorderButtons } from '@/components/ui/reorder-buttons';
import { DistanceDisplay, RouteDistanceSummary } from '@/components/ui/distance-display';
import { calculateStopDistances } from '@/lib/distance-calculator';
import MapStopPicker from '@/components/MapStopPicker';
import {
  BaseForm,
  FormField,
  FormTextarea
} from '@/base-components/base-form';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Stop {
    id: number;
    name: string;
    description: string | null;
    latitude: number | null;
    longitude: number | null;
}

interface RouteStopForm {
    stop_id: number;
    arrival_time: string;
    departure_time: string;
    manual_distance?: number | null;
}

interface CreateRouteProps {
    stops: Stop[];
}

export default function CreateRoute({ stops: initialStops }: CreateRouteProps) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        description: '',
        remarks: '',
        stops: [],
    });

    const [selectedStops, setSelectedStops] = useState<RouteStopForm[]>([]);
    const [stops, setStops] = useState<Stop[]>(initialStops);
    const [showMapPicker, setShowMapPicker] = useState(false);
    const [mapPickerStopIndex, setMapPickerStopIndex] = useState<number | null>(null);
    const [manualDistanceMode, setManualDistanceMode] = useState(false);

    // Calculate route statistics
    const getSelectedStopsWithCoordinates = () => {
        return selectedStops.map(routeStop => {
            const stop = stops.find(s => s.id === routeStop.stop_id);
            return stop || null;
        }).filter(Boolean) as Stop[];
    };

    const routeStopsWithCoords = getSelectedStopsWithCoordinates();

    // Calculate distances - use manual if available, otherwise calculated
    let totalDistance = 0;
    const stopDistances: (number | null)[] = [];
    const cumulativeDistances = [0];

    for (let i = 0; i < selectedStops.length; i++) {
        let distanceFromPrevious = 0;

        if (i > 0) {
            // Use manual distance if provided
            if (manualDistanceMode && selectedStops[i].manual_distance !== null && selectedStops[i].manual_distance !== undefined) {
                distanceFromPrevious = selectedStops[i].manual_distance!;
            } else {
                // Calculate distance using coordinates
                const currentStop = routeStopsWithCoords[i];
                const previousStop = routeStopsWithCoords[i - 1];

                if (currentStop && previousStop &&
                    currentStop.latitude && currentStop.longitude &&
                    previousStop.latitude && previousStop.longitude) {
                    const calculatedDistances = calculateStopDistances([previousStop, currentStop]);
                    distanceFromPrevious = calculatedDistances[1] || 0;
                }
            }

            totalDistance += distanceFromPrevious;
        }

        stopDistances.push(distanceFromPrevious);
        cumulativeDistances.push(Math.round((cumulativeDistances[i] + distanceFromPrevious) * 100) / 100);
    }

    const addStop = () => {
        const newStop: RouteStopForm = {
            stop_id: 0,
            arrival_time: '',
            departure_time: '',
            manual_distance: null,
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

    const updateStop = (index: number, field: keyof RouteStopForm, value: string | number | null) => {
        const updatedStops = selectedStops.map((stop, i) =>
            i === index ? { ...stop, [field]: value } : stop
        );
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
    const handleCreateStop = async (stopData: { name: string; description: string; latitude?: number; longitude?: number }) => {
        try {
            const response = await fetch('/api/stops', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify(stopData)
            });

            if (response.ok) {
                const result = await response.json();
                const newStop = result.data;
                setStops(prev => [...prev, newStop]);
                return newStop;
            }
        } catch {
            // stop creation failure is surfaced via form state
        }
    };

    // Handle map stop selection
    const handleMapStopSelect = (stop: Stop) => {
        if (mapPickerStopIndex !== null) {
            updateStop(mapPickerStopIndex, 'stop_id', stop.id);
        }
        setShowMapPicker(false);
        setMapPickerStopIndex(null);
    };

    // Handle opening map for specific stop
    const handleOpenMapForStop = (index: number) => {
        setMapPickerStopIndex(index);
        setShowMapPicker(true);
    };

    // Handle creating stop from map
    const handleCreateStopFromMap = async (stopData: { name: string; address: string; latitude: number; longitude: number }) => {
        try {
            const newStop = await handleCreateStop({
                name: stopData.name,
                description: stopData.address,
                latitude: stopData.latitude,
                longitude: stopData.longitude
            });

            if (newStop && mapPickerStopIndex !== null) {
                updateStop(mapPickerStopIndex, 'stop_id', newStop.id);
            }

            setShowMapPicker(false);
            setMapPickerStopIndex(null);
        } catch {
            // map stop creation failure is non-critical
        }
    };

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('routes.store'), {
            onSuccess: () => {
                reset();
                setSelectedStops([]);
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Create Route" />

            <div className="space-y-6">
                <PageHeader
                    title="Create New Route"
                    description="Create a new vehicle route with multiple stops"
                    actions={[
                        {
                            label: "Back to Routes",
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: "/routes",
                            variant: "outline"
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
                                <CardDescription>
                                    Enter basic information about the route
                                </CardDescription>
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
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant={manualDistanceMode ? "default" : "outline"}
                                            size="sm"
                                            onClick={() => setManualDistanceMode(!manualDistanceMode)}
                                            className="text-xs"
                                        >
                                            {manualDistanceMode ? "Auto Distance" : "Manual Distance"}
                                        </Button>
                                        <Badge variant="secondary">
                                            {selectedStops.length} stops
                                        </Badge>
                                    </div>
                                </CardTitle>
                                <CardDescription>
                                    Add and arrange stops for this route. Drag to reorder.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {selectedStops.map((stop, index) => (
                                    <div key={index} className="border rounded-lg p-4 space-y-4 bg-muted/50">
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
                                                    className="text-red-600 hover:text-red-700 h-8 w-8 p-0"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>

                                        <div className={`grid grid-cols-1 gap-4 ${manualDistanceMode ? 'md:grid-cols-4' : 'md:grid-cols-3'}`}>
                                            <div className="space-y-2">
                                                <Label>Stop Location</Label>
                                                <EnhancedStopSelect
                                                    stops={stops}
                                                    value={stop.stop_id}
                                                    onChange={(stopId: number) => updateStop(index, 'stop_id', stopId)}
                                                    onCreateStop={handleCreateStop}
                                                    placeholder="Search or add new stop..."
                                                    previousStop={index > 0 ? routeStopsWithCoords[index - 1] || null : null}
                                                    showDistances={index > 0}
                                                    onOpenMap={() => handleOpenMapForStop(index)}
                                                />

                                                {/* Distance Display */}
                                                {index < routeStopsWithCoords.length && !manualDistanceMode && (
                                                    <DistanceDisplay
                                                        distances={{
                                                            fromPrevious: stopDistances[index] || null,
                                                            cumulative: cumulativeDistances[index] || null
                                                        }}
                                                        stopIndex={index}
                                                        className="mt-1"
                                                    />
                                                )}
                                            </div>

                                            {/* Manual Distance Input (only show for stops after the first) */}
                                            {manualDistanceMode && index > 0 && (
                                                <div className="space-y-2">
                                                    <Label className="flex items-center gap-1">
                                                        <RouteIcon className="h-3 w-3" />
                                                        Distance from Previous (km)
                                                    </Label>
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={stop.manual_distance || ''}
                                                        onChange={(e) => updateStop(index, 'manual_distance', e.target.value ? parseFloat(e.target.value) : null)}
                                                        placeholder="0.00"
                                                    />
                                                    <div className="text-xs text-muted-foreground">
                                                        Cumulative: {cumulativeDistances[index]?.toFixed(2) || '0.00'} km
                                                    </div>
                                                </div>
                                            )}

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

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addStop}
                                    className="w-full"
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Stop
                                </Button>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Summary Card */}
                    <div className="space-y-6">
                        {/* Route Distance Summary */}
                        <RouteDistanceSummary
                            totalDistance={totalDistance}
                            stopCount={selectedStops.length}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle>Route Summary</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label className="text-sm font-medium">Route Name</Label>
                                    <p className="text-sm text-muted-foreground">
                                        {(data.name as string) || 'No name entered'}
                                    </p>
                                </div>

                                {selectedStops.length > 0 && (
                                    <div className="space-y-2">
                                        <Label className="text-sm font-medium">Route Sequence</Label>
                                        <div className="space-y-1">
                                            {selectedStops.map((stop, index) => {
                                                const stopInfo = stops.find(s => s.id === stop.stop_id);
                                                return (
                                                    <div key={index} className="text-xs text-muted-foreground flex items-center gap-2">
                                                        <Badge variant="outline" className="text-xs">
                                                            {index + 1}
                                                        </Badge>
                                                        <span className="flex-1">{stopInfo?.name || 'Select a stop'}</span>
                                                        {index < cumulativeDistances.length && cumulativeDistances[index] > 0 && (
                                                            <span className="text-blue-600 font-mono text-xs">
                                                                {cumulativeDistances[index].toFixed(1)}km
                                                            </span>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}

                                <div className="pt-4 space-y-2">
                                    <Button
                                        onClick={handleSubmit}
                                        disabled={processing}
                                        className="w-full"
                                    >
                                        {processing ? 'Creating Route...' : 'Create Route'}
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            reset();
                                            setSelectedStops([]);
                                        }}
                                        className="w-full"
                                    >
                                        Clear Form
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Map Stop Picker Modal */}
            {showMapPicker && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50">
                    <div className="mx-4 max-h-[90vh] w-full max-w-4xl overflow-auto rounded-lg bg-card p-6 text-card-foreground">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-lg font-semibold flex items-center gap-2">
                                <Map className="h-5 w-5" />
                                Select Stop from Map
                            </h2>
                            <Button
                                variant="ghost"
                                onClick={() => {
                                    setShowMapPicker(false);
                                    setMapPickerStopIndex(null);
                                }}
                            >
                                ×
                            </Button>
                        </div>

                        <MapStopPicker
                            stops={stops.map(stop => ({
                                id: stop.id,
                                name: stop.name,
                                address: stop.description || '',
                                latitude: stop.latitude,
                                longitude: stop.longitude
                            }))}
                            selectedStops={selectedStops.map((stop, index) => ({
                                stop_id: stop.stop_id,
                                order: index + 1,
                                stop: stops.find(s => s.id === stop.stop_id) ? {
                                    id: stops.find(s => s.id === stop.stop_id)!.id,
                                    name: stops.find(s => s.id === stop.stop_id)!.name,
                                    address: stops.find(s => s.id === stop.stop_id)!.description || '',
                                    latitude: stops.find(s => s.id === stop.stop_id)!.latitude,
                                    longitude: stops.find(s => s.id === stop.stop_id)!.longitude
                                } : undefined
                            }))}
                            onStopSelect={(mapStop) => {
                                const originalStop = stops.find(s => s.id === mapStop.id);
                                if (originalStop) {
                                    handleMapStopSelect(originalStop);
                                }
                            }}
                            onCreateStop={handleCreateStopFromMap}
                        />
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
