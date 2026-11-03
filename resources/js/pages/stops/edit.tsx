import { BaseForm, FormField, FormTextarea } from '@/base-components/base-form';
import { PageHeader } from '@/base-components/page-header';
import MapStopPicker from '@/components/MapStopPicker';
import { SearchableSelect } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Map, MapPin, Save } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface Zone {
    id: number;
    name: string;
}

interface Stop {
    id: number;
    name: string;
    description: string | null;
    latitude: number | null;
    longitude: number | null;
    zone_id: number | null;
}

interface ExistingStop {
    id: number;
    name: string;
    description: string | null;
    latitude: number | null;
    longitude: number | null;
}

interface EditStopProps {
    stop: Stop;
    zones: Zone[];
    stops: ExistingStop[];
}

export default function EditStop({ stop, zones, stops }: EditStopProps) {
    const { data, setData, put, processing, errors } = useForm({
        name: stop.name,
        description: stop.description || '',
        latitude: stop.latitude?.toString() || '',
        longitude: stop.longitude?.toString() || '',
        zone_id: stop.zone_id?.toString() || '',
    });

    const [showMapPicker, setShowMapPicker] = useState(false);

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/stops/${stop.id}`);
    };

    // The map picker is built for picking/creating route stops, so its
    // callbacks hand back a location rather than persisting anything here —
    // we just copy the location into this form's own fields, keeping the
    // name/description already typed here unless they're still empty.
    const applyMapLocation = (location: { name: string; address: string; latitude: number; longitude: number }) => {
        setData((prev) => ({
            ...prev,
            name: prev.name || location.name,
            description: prev.description || location.address,
            latitude: location.latitude.toString(),
            longitude: location.longitude.toString(),
        }));
        setShowMapPicker(false);
    };

    return (
        <AppLayout>
            <Head title={`Edit Stop: ${stop.name}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Edit Stop: ${stop.name}`}
                    description="Update this stop's details and location"
                    actions={[
                        {
                            label: 'Back to Stops',
                            icon: <ArrowLeft className="mr-2 h-4 w-4" />,
                            href: '/stops',
                            variant: 'outline',
                        },
                    ]}
                />

                <Card>
                    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div className="space-y-1.5">
                            <CardTitle className="flex items-center gap-2">
                                <MapPin className="h-5 w-5" />
                                Stop Information
                            </CardTitle>
                            <CardDescription>Name, location and zone for this stop</CardDescription>
                        </div>
                        {/* Zone sits in the title bar, matching the routes create/edit forms */}
                        <div className="flex items-center gap-2 sm:w-64">
                            <span className="text-sm font-medium text-muted-foreground">Zone</span>
                            <div className="flex-1">
                                <SearchableSelect
                                    label="Zone"
                                    name="zone_id"
                                    value={data.zone_id}
                                    onChange={(value) => setData('zone_id', value === 'none' ? '' : value)}
                                    error={errors.zone_id}
                                    options={zones.map((zone) => ({ label: zone.name, value: zone.id.toString() }))}
                                    placeholder="Select zone..."
                                    hideLabel
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <BaseForm onSubmit={handleSubmit} className="space-y-4">
                            <FormField
                                label="Stop Name"
                                name="name"
                                value={data.name}
                                onChange={(value) => setData('name', value)}
                                error={errors.name}
                                placeholder="Enter stop name..."
                                required
                            />

                            <FormTextarea
                                label="Description"
                                name="description"
                                value={data.description}
                                onChange={(value) => setData('description', value)}
                                error={errors.description}
                                placeholder="Enter description or address..."
                                rows={3}
                            />

                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium">Location</span>
                                    <Button type="button" variant="outline" size="sm" onClick={() => setShowMapPicker(true)}>
                                        <Map className="mr-2 h-4 w-4" />
                                        Pick on Map
                                    </Button>
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <FormField
                                        label="Latitude"
                                        name="latitude"
                                        type="number"
                                        value={data.latitude}
                                        onChange={(value) => setData('latitude', value)}
                                        error={errors.latitude}
                                        placeholder="e.g. 23.8103"
                                    />
                                    <FormField
                                        label="Longitude"
                                        name="longitude"
                                        type="number"
                                        value={data.longitude}
                                        onChange={(value) => setData('longitude', value)}
                                        error={errors.longitude}
                                        placeholder="e.g. 90.4125"
                                    />
                                </div>
                            </div>

                            <div className="flex justify-end pt-2">
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Saving...' : 'Update Stop'}
                                </Button>
                            </div>
                        </BaseForm>
                    </CardContent>
                </Card>
            </div>

            {showMapPicker && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50">
                    <div className="mx-4 max-h-[90vh] w-full max-w-4xl overflow-auto rounded-lg bg-card p-6 text-card-foreground">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <Map className="h-5 w-5" />
                                Pick a Location
                            </h2>
                            <Button variant="ghost" onClick={() => setShowMapPicker(false)}>
                                ×
                            </Button>
                        </div>

                        <MapStopPicker
                            stops={stops.map((s) => ({
                                id: s.id,
                                name: s.name,
                                address: s.description || '',
                                latitude: s.latitude,
                                longitude: s.longitude,
                            }))}
                            selectedStops={[]}
                            onStopSelect={(selected) => {
                                if (selected.latitude === null || selected.longitude === null) return;
                                applyMapLocation({
                                    name: selected.name,
                                    address: selected.address,
                                    latitude: selected.latitude,
                                    longitude: selected.longitude,
                                });
                            }}
                            onCreateStop={applyMapLocation}
                        />
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
