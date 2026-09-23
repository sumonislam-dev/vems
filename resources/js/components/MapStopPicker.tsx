import React, { useState, useRef, useCallback, useEffect } from 'react';
import { MapContainer, TileLayer, Marker, Popup, useMapEvents } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { GoogleMap, Marker as GMarker, InfoWindow, useJsApiLoader } from '@react-google-maps/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { MapPin, Plus, Navigation, Search, X } from 'lucide-react';

// Fix for default markers in React-Leaflet
// @ts-expect-error - Leaflet marker icon fix
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
});

// Region the map is fixed to, and which map provider renders it — controlled
// from .env (VITE_MAP_*) rather than hardcoded, so deploying this app for a
// different country/provider only means changing .env, not this file. Falls
// back to the current Bangladesh/OSM values if a var is unset.
function envNumber(value: string | undefined, fallback: number): number {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
}

const MAP_COUNTRY_CODE = import.meta.env.VITE_MAP_COUNTRY_CODE || 'bd';
const MAP_DEFAULT_CENTER: [number, number] = [
    envNumber(import.meta.env.VITE_MAP_DEFAULT_LAT, 23.8103),
    envNumber(import.meta.env.VITE_MAP_DEFAULT_LNG, 90.4125),
];
const MAP_DEFAULT_ZOOM = envNumber(import.meta.env.VITE_MAP_DEFAULT_ZOOM, 10);
const MAP_MIN_ZOOM = envNumber(import.meta.env.VITE_MAP_MIN_ZOOM, 6);
const MAP_BOUNDS_SOUTH = envNumber(import.meta.env.VITE_MAP_BOUNDS_SOUTH, 20.3);
const MAP_BOUNDS_WEST = envNumber(import.meta.env.VITE_MAP_BOUNDS_WEST, 87.8);
const MAP_BOUNDS_NORTH = envNumber(import.meta.env.VITE_MAP_BOUNDS_NORTH, 26.9);
const MAP_BOUNDS_EAST = envNumber(import.meta.env.VITE_MAP_BOUNDS_EAST, 92.9);

// Keeps the map from panning/zooming out to anywhere outside the region above
// — applied to the map view itself (padded slightly beyond the border in the
// default Bangladesh values).
const MAP_BOUNDS: L.LatLngBoundsExpression = [
    [MAP_BOUNDS_SOUTH, MAP_BOUNDS_WEST],
    [MAP_BOUNDS_NORTH, MAP_BOUNDS_EAST],
];

// "osm" (default, free, no key) or "google" (needs a billed Google Cloud
// project + API key). Falls back to OSM if "google" is picked without a key
// so a misconfigured .env never produces a blank/broken map.
const GOOGLE_MAPS_API_KEY = import.meta.env.VITE_GOOGLE_MAPS_API_KEY || '';
const MAP_PROVIDER = (import.meta.env.VITE_MAP_PROVIDER || 'osm').toLowerCase();
const USE_GOOGLE_MAPS = MAP_PROVIDER === 'google' && GOOGLE_MAPS_API_KEY.length > 0;

if (MAP_PROVIDER === 'google' && !GOOGLE_MAPS_API_KEY && import.meta.env.DEV) {
    console.warn(
        'VITE_MAP_PROVIDER is "google" but VITE_GOOGLE_MAPS_API_KEY is empty — falling back to the free OpenStreetMap map. Set the key in .env to actually use Google Maps.'
    );
}

// Stable reference required by @react-google-maps/api — a new array literal
// on every render would make it reload the Google Maps script repeatedly.
const GOOGLE_MAP_LIBRARIES: 'places'[] = ['places'];

// Custom icon for route stops
const stopIcon = new L.Icon({
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
});

const NEW_STOP_ICON_URL = 'data:image/svg+xml;base64,' + btoa(`
    <svg xmlns="http://www.w3.org/2000/svg" width="25" height="41" viewBox="0 0 25 41">
        <path fill="#ef4444" stroke="#dc2626" stroke-width="1" d="M12.5 0C5.6 0 0 5.6 0 12.5c0 6.9 12.5 28.5 12.5 28.5S25 19.4 25 12.5C25 5.6 19.4 0 12.5 0z"/>
        <circle fill="#ffffff" cx="12.5" cy="12.5" r="4"/>
    </svg>
`);

const USER_LOCATION_ICON_URL = 'data:image/svg+xml;base64,' + btoa(`
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20">
        <circle fill="#3b82f6" cx="10" cy="10" r="8" stroke="#ffffff" stroke-width="2"/>
        <circle fill="#ffffff" cx="10" cy="10" r="3"/>
    </svg>
`);

// Custom icon for new stop location (Leaflet)
const newStopIcon = new L.Icon({
    iconUrl: NEW_STOP_ICON_URL,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
});

interface Stop {
    id: number;
    name: string;
    address: string;
    latitude: number | null;
    longitude: number | null;
}

interface MapStopPickerProps {
    stops: Stop[];
    selectedStops: Array<{
        stop_id: number;
        order: number;
        stop?: Stop;
    }>;
    onStopSelect: (stop: Stop) => void;
    onCreateStop: (stopData: { name: string; address: string; latitude: number; longitude: number }) => void;
    className?: string;
}

// A geocoding search result, normalized across providers. Nominatim resolves
// lat/lon up front; Google Places only returns a place_id from autocomplete,
// so lat/lon are resolved lazily (via Place Details) when the result is
// selected — see GoogleStopMap's handleSelectGeocodingResult.
interface GeocodingResult {
    display_name: string;
    place_id: string;
    lat?: string;
    lon?: string;
}

// ---------------------------------------------------------------------------
// Shared, provider-agnostic pieces
// ---------------------------------------------------------------------------

function MapControls({
    isCreatingStop,
    onToggleCreate,
    onLocate,
}: {
    isCreatingStop: boolean;
    onToggleCreate: () => void;
    onLocate: () => void;
}) {
    return (
        <div className="flex gap-2 flex-wrap">
            <Button
                type="button"
                variant={isCreatingStop ? "destructive" : "outline"}
                onClick={onToggleCreate}
                className="flex items-center gap-2"
            >
                <Plus className="h-4 w-4" />
                {isCreatingStop ? 'Cancel Creation' : 'Create New Stop'}
            </Button>

            <Button
                type="button"
                variant="outline"
                onClick={onLocate}
                className="flex items-center gap-2"
            >
                <Navigation className="h-4 w-4" />
                My Location
            </Button>
        </div>
    );
}

function CreateStopForm({
    newStopPosition,
    newStopName,
    newStopAddress,
    onNameChange,
    onAddressChange,
    onCreate,
    onCancel,
}: {
    newStopPosition: { lat: number; lng: number } | null;
    newStopName: string;
    newStopAddress: string;
    onNameChange: (value: string) => void;
    onAddressChange: (value: string) => void;
    onCreate: () => void;
    onCancel: () => void;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-sm flex items-center gap-2">
                    <MapPin className="h-4 w-4 text-red-500" />
                    Create New Stop
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <p className="text-sm text-muted-foreground">
                    Click on the map to select the location for your new stop.
                </p>

                {newStopPosition && (
                    <div className="space-y-3">
                        <div>
                            <label className="text-sm font-medium">Stop Name</label>
                            <Input
                                value={newStopName}
                                onChange={(e) => onNameChange(e.target.value)}
                                placeholder="Enter stop name"
                                className="mt-1"
                            />
                        </div>

                        <div>
                            <label className="text-sm font-medium">Address</label>
                            <Input
                                value={newStopAddress}
                                onChange={(e) => onAddressChange(e.target.value)}
                                placeholder="Enter address (optional)"
                                className="mt-1"
                            />
                        </div>

                        <div className="flex gap-2">
                            <Button
                                type="button"
                                onClick={onCreate}
                                disabled={!newStopName.trim()}
                                className="flex-1"
                            >
                                Create Stop
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onCancel}
                            >
                                Cancel
                            </Button>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function isStopSelectedIn(selectedStops: MapStopPickerProps['selectedStops'], stopId: number) {
    return selectedStops.some(selected => selected.stop_id === stopId);
}

function getStopOrderIn(selectedStops: MapStopPickerProps['selectedStops'], stopId: number) {
    return selectedStops.find(selected => selected.stop_id === stopId)?.order;
}

// Search box shared by both providers: filters saved stops locally, and
// delegates the free-text geocoding search to whichever provider is active
// via `searchGeocoding`.
function LocationSearch({
    stops,
    selectedStops,
    searchGeocoding,
    onSelectStop,
    onSelectGeocodingResult,
}: {
    stops: Stop[];
    selectedStops: MapStopPickerProps['selectedStops'];
    searchGeocoding: (query: string) => Promise<GeocodingResult[]>;
    onSelectStop: (stop: Stop) => void;
    onSelectGeocodingResult: (result: GeocodingResult) => void;
}) {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<GeocodingResult[]>([]);
    const [savedStopResults, setSavedStopResults] = useState<Stop[]>([]);
    const [isSearching, setIsSearching] = useState(false);
    const [showSearchResults, setShowSearchResults] = useState(false);
    const searchRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (searchRef.current && !searchRef.current.contains(event.target as Node)) {
                setShowSearchResults(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, []);

    const runSearch = useCallback(async (query: string) => {
        if (!query.trim() || query.length < 2) {
            setSearchResults([]);
            setSavedStopResults([]);
            setShowSearchResults(false);
            return;
        }

        setIsSearching(true);
        try {
            const filteredStops = stops.filter(stop =>
                stop.name.toLowerCase().includes(query.toLowerCase()) ||
                stop.address.toLowerCase().includes(query.toLowerCase())
            ).slice(0, 5);

            setSavedStopResults(filteredStops);

            let geocodingResults: GeocodingResult[] = [];
            if (query.length >= 3) {
                geocodingResults = await searchGeocoding(query);
            }

            setSearchResults(geocodingResults);
            setShowSearchResults(filteredStops.length > 0 || geocodingResults.length > 0);
        } catch (error) {
            console.error('Error searching location:', error);
        } finally {
            setIsSearching(false);
        }
    }, [stops, searchGeocoding]);

    const handleSearchChange = useCallback((query: string) => {
        setSearchQuery(query);
        const timeoutId = setTimeout(() => {
            runSearch(query);
        }, 300);
        return () => clearTimeout(timeoutId);
    }, [runSearch]);

    const handleGeocodingSelect = (result: GeocodingResult) => {
        onSelectGeocodingResult(result);
        setShowSearchResults(false);
        setSearchQuery('');
    };

    const handleSavedStopSelect = (stop: Stop) => {
        onSelectStop(stop);
        setShowSearchResults(false);
        setSearchQuery('');
    };

    return (
        <div className="relative z-[1100]" ref={searchRef}>
            <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    value={searchQuery}
                    onChange={(e) => handleSearchChange(e.target.value)}
                    placeholder="Search for a location..."
                    className="pl-10 pr-10"
                />
                {searchQuery && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setSearchQuery('');
                            setSearchResults([]);
                            setSavedStopResults([]);
                            setShowSearchResults(false);
                        }}
                        className="absolute right-1 top-1/2 transform -translate-y-1/2 h-8 w-8 p-0"
                    >
                        <X className="h-4 w-4" />
                    </Button>
                )}
            </div>

            {showSearchResults && (
                <Card className="absolute z-[1200] w-full mt-1 shadow-lg border max-h-60 overflow-auto">
                    <CardContent className="p-0">
                        {savedStopResults.length > 0 && (
                            <>
                                <div className="px-3 py-2 text-xs font-medium text-muted-foreground bg-muted/50 border-b">
                                    Saved Stops
                                </div>
                                {savedStopResults.map((stop) => (
                                    <button
                                        key={`saved-${stop.id}`}
                                        onClick={() => handleSavedStopSelect(stop)}
                                        className="w-full px-4 py-3 text-left hover:bg-muted transition-colors border-b last:border-b-0 flex items-start gap-3"
                                    >
                                        <MapPin className="h-4 w-4 mt-0.5 text-blue-600 flex-shrink-0" />
                                        <div className="min-w-0 flex-1">
                                            <div className="text-sm font-medium truncate">
                                                {stop.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground truncate">
                                                {stop.address}
                                            </div>
                                        </div>
                                        {isStopSelectedIn(selectedStops, stop.id) && (
                                            <Badge variant="secondary" className="text-xs">
                                                Selected
                                            </Badge>
                                        )}
                                    </button>
                                ))}
                            </>
                        )}

                        {searchResults.length > 0 && (
                            <>
                                <div className="px-3 py-2 text-xs font-medium text-muted-foreground bg-muted/50 border-b">
                                    Map Locations (Click to create stop)
                                </div>
                                {searchResults.map((result) => (
                                    <button
                                        key={`geocoding-${result.place_id}`}
                                        onClick={() => handleGeocodingSelect(result)}
                                        className="w-full px-4 py-3 text-left hover:bg-muted transition-colors border-b last:border-b-0 flex items-start gap-3"
                                    >
                                        <Plus className="h-4 w-4 mt-0.5 text-green-600 flex-shrink-0" />
                                        <div className="min-w-0 flex-1">
                                            <div className="text-sm font-medium truncate">
                                                {result.display_name.split(',')[0].trim()}
                                            </div>
                                            <div className="text-xs text-muted-foreground truncate">
                                                {result.display_name}
                                            </div>
                                            <div className="text-xs text-green-600 mt-1">
                                                Click to create and add as stop
                                            </div>
                                        </div>
                                    </button>
                                ))}
                            </>
                        )}

                        {savedStopResults.length === 0 && searchResults.length === 0 && (
                            <div className="px-4 py-3 text-sm text-muted-foreground text-center">
                                No locations found
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

            {isSearching && (
                <div className="absolute right-10 top-1/2 transform -translate-y-1/2 z-[1300]">
                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-primary"></div>
                </div>
            )}
        </div>
    );
}

// Component to handle Leaflet map clicks
function MapClickHandler({ onMapClick }: { onMapClick: (lat: number, lng: number) => void }) {
    useMapEvents({
        click(e) {
            onMapClick(e.latlng.lat, e.latlng.lng);
        },
    });
    return null;
}

// ---------------------------------------------------------------------------
// OpenStreetMap / Leaflet implementation (default, free, no API key)
// ---------------------------------------------------------------------------

function LeafletStopMap({
    stops,
    selectedStops,
    onStopSelect,
    onCreateStop,
    className = ''
}: MapStopPickerProps) {
    const [isCreatingStop, setIsCreatingStop] = useState(false);
    const [newStopPosition, setNewStopPosition] = useState<{ lat: number; lng: number } | null>(null);
    const [newStopName, setNewStopName] = useState('');
    const [newStopAddress, setNewStopAddress] = useState('');
    const [userLocation, setUserLocation] = useState<{ lat: number; lng: number } | null>(null);
    const mapRef = useRef<L.Map | null>(null);

    const getCurrentLocation = useCallback(() => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const { latitude, longitude } = position.coords;
                    setUserLocation({ lat: latitude, lng: longitude });
                    if (mapRef.current) {
                        mapRef.current.setView([latitude, longitude], 13);
                    }
                },
                (error) => {
                    console.error('Error getting location:', error);
                }
            );
        }
    }, []);

    // Bias/restrict results to the configured region (see VITE_MAP_* above):
    // countrycodes hard-filters by country, viewbox further prioritizes
    // results within its bounding box (bounded=0 keeps it as a bias, not a
    // hard cutoff).
    const searchGeocoding = useCallback(async (query: string): Promise<GeocodingResult[]> => {
        const response = await fetch(
            `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=8&addressdetails=1&countrycodes=${MAP_COUNTRY_CODE}&viewbox=${MAP_BOUNDS_WEST},${MAP_BOUNDS_NORTH},${MAP_BOUNDS_EAST},${MAP_BOUNDS_SOUTH}&bounded=0`
        );

        if (!response.ok) {
            return [];
        }

        const rawResults = await response.json();
        return rawResults.map((result: {
            display_name: string;
            lat: string;
            lon: string;
            place_id: string;
        }) => ({
            display_name: result.display_name,
            lat: result.lat,
            lon: result.lon,
            place_id: result.place_id,
        }));
    }, []);

    const handleSelectGeocodingResult = (result: GeocodingResult) => {
        if (!result.lat || !result.lon) {
            return;
        }
        const lat = parseFloat(result.lat);
        const lng = parseFloat(result.lon);

        if (mapRef.current) {
            mapRef.current.setView([lat, lng], 15);
        }

        const nameFromDisplay = result.display_name.split(',')[0].trim();

        onCreateStop({
            name: nameFromDisplay,
            address: result.display_name,
            latitude: lat,
            longitude: lng
        });
    };

    const handleSelectStop = (stop: Stop) => {
        if (stop.latitude !== null && stop.longitude !== null && mapRef.current) {
            mapRef.current.setView([stop.latitude, stop.longitude], 15);
        }
        onStopSelect(stop);
    };

    const handleMapClick = useCallback((lat: number, lng: number) => {
        if (isCreatingStop) {
            setNewStopPosition({ lat, lng });
            setNewStopAddress(`${lat.toFixed(6)}, ${lng.toFixed(6)}`);
        }
    }, [isCreatingStop]);

    const handleCreateStop = () => {
        if (newStopPosition && newStopName.trim()) {
            onCreateStop({
                name: newStopName.trim(),
                address: newStopAddress.trim() || `${newStopPosition.lat.toFixed(6)}, ${newStopPosition.lng.toFixed(6)}`,
                latitude: newStopPosition.lat,
                longitude: newStopPosition.lng
            });

            setIsCreatingStop(false);
            setNewStopPosition(null);
            setNewStopName('');
            setNewStopAddress('');
        }
    };

    const handleCancelCreation = () => {
        setIsCreatingStop(false);
        setNewStopPosition(null);
        setNewStopName('');
        setNewStopAddress('');
    };

    const getMapCenter = (): [number, number] => {
        const validStops = stops.filter(stop => stop.latitude !== null && stop.longitude !== null);
        if (validStops.length === 0) {
            return MAP_DEFAULT_CENTER;
        }

        const avgLat = validStops.reduce((sum, stop) => sum + (stop.latitude || 0), 0) / validStops.length;
        const avgLng = validStops.reduce((sum, stop) => sum + (stop.longitude || 0), 0) / validStops.length;
        return [avgLat, avgLng];
    };

    return (
        <div className={`space-y-4 ${className}`}>
            <MapControls
                isCreatingStop={isCreatingStop}
                onToggleCreate={() => setIsCreatingStop(!isCreatingStop)}
                onLocate={getCurrentLocation}
            />

            <LocationSearch
                stops={stops}
                selectedStops={selectedStops}
                searchGeocoding={searchGeocoding}
                onSelectStop={handleSelectStop}
                onSelectGeocodingResult={handleSelectGeocodingResult}
            />

            {isCreatingStop && (
                <CreateStopForm
                    newStopPosition={newStopPosition}
                    newStopName={newStopName}
                    newStopAddress={newStopAddress}
                    onNameChange={setNewStopName}
                    onAddressChange={setNewStopAddress}
                    onCreate={handleCreateStop}
                    onCancel={handleCancelCreation}
                />
            )}

            <div className="h-96 rounded-lg overflow-hidden border">
                <MapContainer
                    center={getMapCenter()}
                    zoom={MAP_DEFAULT_ZOOM}
                    minZoom={MAP_MIN_ZOOM}
                    maxBounds={MAP_BOUNDS}
                    maxBoundsViscosity={1.0}
                    className="h-full w-full"
                    ref={mapRef}
                >
                    <TileLayer
                        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    />

                    <MapClickHandler onMapClick={handleMapClick} />

                    {stops
                        .filter(stop => stop.latitude !== null && stop.longitude !== null)
                        .map(stop => (
                            <Marker
                                key={stop.id}
                                position={[stop.latitude!, stop.longitude!]}
                                icon={stopIcon}
                                eventHandlers={{
                                    click: () => {
                                        if (!isCreatingStop) {
                                            onStopSelect(stop);
                                        }
                                    }
                                }}
                            >
                                <Popup>
                                    <div className="p-2">
                                        <h3 className="font-semibold">{stop.name}</h3>
                                        <p className="text-sm text-gray-600">{stop.address}</p>
                                        {isStopSelectedIn(selectedStops, stop.id) && (
                                            <Badge variant="secondary" className="mt-2">
                                                Order: {getStopOrderIn(selectedStops, stop.id)}
                                            </Badge>
                                        )}
                                        {!isCreatingStop && !isStopSelectedIn(selectedStops, stop.id) && (
                                            <Button
                                                size="sm"
                                                className="mt-2 w-full"
                                                onClick={() => onStopSelect(stop)}
                                            >
                                                Add to Route
                                            </Button>
                                        )}
                                    </div>
                                </Popup>
                            </Marker>
                        ))
                    }

                    {newStopPosition && (
                        <Marker
                            position={[newStopPosition.lat, newStopPosition.lng]}
                            icon={newStopIcon}
                        >
                            <Popup>
                                <div className="p-2">
                                    <h3 className="font-semibold text-red-600">New Stop Location</h3>
                                    <p className="text-sm text-gray-600">
                                        {newStopPosition.lat.toFixed(6)}, {newStopPosition.lng.toFixed(6)}
                                    </p>
                                </div>
                            </Popup>
                        </Marker>
                    )}

                    {userLocation && (
                        <Marker
                            position={[userLocation.lat, userLocation.lng]}
                            icon={new L.Icon({
                                iconUrl: USER_LOCATION_ICON_URL,
                                iconSize: [20, 20],
                                iconAnchor: [10, 10],
                            })}
                        >
                            <Popup>
                                <div className="p-2">
                                    <h3 className="font-semibold text-blue-600">Your Location</h3>
                                </div>
                            </Popup>
                        </Marker>
                    )}
                </MapContainer>
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------------
// Google Maps implementation (opt-in via VITE_MAP_PROVIDER=google + an API
// key with the Maps JavaScript API and Places API enabled on a billed
// Google Cloud project)
// ---------------------------------------------------------------------------

const googleMapOptions: google.maps.MapOptions = {
    minZoom: MAP_MIN_ZOOM,
    streetViewControl: false,
    mapTypeControl: false,
    restriction: {
        latLngBounds: {
            north: MAP_BOUNDS_NORTH,
            south: MAP_BOUNDS_SOUTH,
            east: MAP_BOUNDS_EAST,
            west: MAP_BOUNDS_WEST,
        },
        strictBounds: true,
    },
};

function GoogleStopMap({
    stops,
    selectedStops,
    onStopSelect,
    onCreateStop,
    className = ''
}: MapStopPickerProps) {
    const { isLoaded, loadError } = useJsApiLoader({
        id: 'vems-google-map-script',
        googleMapsApiKey: GOOGLE_MAPS_API_KEY,
        libraries: GOOGLE_MAP_LIBRARIES,
    });

    const [isCreatingStop, setIsCreatingStop] = useState(false);
    const [newStopPosition, setNewStopPosition] = useState<{ lat: number; lng: number } | null>(null);
    const [newStopName, setNewStopName] = useState('');
    const [newStopAddress, setNewStopAddress] = useState('');
    const [userLocation, setUserLocation] = useState<{ lat: number; lng: number } | null>(null);
    const [activeInfoStopId, setActiveInfoStopId] = useState<number | null>(null);

    const mapRef = useRef<google.maps.Map | null>(null);

    const handleMapLoad = useCallback((map: google.maps.Map) => {
        mapRef.current = map;
    }, []);

    const getCurrentLocation = useCallback(() => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const { latitude, longitude } = position.coords;
                    setUserLocation({ lat: latitude, lng: longitude });
                    mapRef.current?.panTo({ lat: latitude, lng: longitude });
                    mapRef.current?.setZoom(13);
                },
                (error) => {
                    console.error('Error getting location:', error);
                }
            );
        }
    }, []);

    // Google's Places Web Service (REST) blocks browser-origin requests, so
    // free-text search must go through the client-side Places library
    // instead of a fetch() call (unlike the Nominatim path). Uses the New
    // Places API (AutocompleteSuggestion/Place) rather than the deprecated
    // AutocompleteService/PlacesService — Google stopped allowing new Cloud
    // projects to enable the legacy Places API as of March 2025.
    const searchGeocoding = useCallback(async (query: string): Promise<GeocodingResult[]> => {
        if (!isLoaded || !google.maps.places?.AutocompleteSuggestion) {
            return [];
        }

        try {
            const { suggestions } = await google.maps.places.AutocompleteSuggestion.fetchAutocompleteSuggestions({
                input: query,
                includedRegionCodes: MAP_COUNTRY_CODE ? [MAP_COUNTRY_CODE] : undefined,
            });

            return suggestions
                .filter((suggestion) => suggestion.placePrediction)
                .map((suggestion) => ({
                    display_name: suggestion.placePrediction!.text.text,
                    place_id: suggestion.placePrediction!.placeId,
                }));
        } catch (error) {
            console.error('Places Autocomplete request failed:', error);
            return [];
        }
    }, [isLoaded]);

    // Autocomplete predictions only carry a place_id — resolve the actual
    // lat/lng via Place.fetchFields() once the user picks one.
    const handleSelectGeocodingResult = useCallback(async (result: GeocodingResult) => {
        if (!isLoaded || !google.maps.places?.Place) {
            return;
        }

        try {
            const place = new google.maps.places.Place({ id: result.place_id });
            await place.fetchFields({ fields: ['location', 'formattedAddress', 'displayName'] });

            if (!place.location) {
                console.error('Could not resolve location for the selected place');
                return;
            }

            const lat = place.location.lat();
            const lng = place.location.lng();
            mapRef.current?.panTo({ lat, lng });
            mapRef.current?.setZoom(15);

            onCreateStop({
                name: (place.displayName || result.display_name).split(',')[0].trim(),
                address: place.formattedAddress || result.display_name,
                latitude: lat,
                longitude: lng,
            });
        } catch (error) {
            console.error('Place Details request failed:', error);
        }
    }, [isLoaded, onCreateStop]);

    const handleMapClick = useCallback((e: google.maps.MapMouseEvent) => {
        if (isCreatingStop && e.latLng) {
            const lat = e.latLng.lat();
            const lng = e.latLng.lng();
            setNewStopPosition({ lat, lng });
            setNewStopAddress(`${lat.toFixed(6)}, ${lng.toFixed(6)}`);
        }
    }, [isCreatingStop]);

    const handleCreateStop = () => {
        if (newStopPosition && newStopName.trim()) {
            onCreateStop({
                name: newStopName.trim(),
                address: newStopAddress.trim() || `${newStopPosition.lat.toFixed(6)}, ${newStopPosition.lng.toFixed(6)}`,
                latitude: newStopPosition.lat,
                longitude: newStopPosition.lng
            });

            setIsCreatingStop(false);
            setNewStopPosition(null);
            setNewStopName('');
            setNewStopAddress('');
        }
    };

    const handleCancelCreation = () => {
        setIsCreatingStop(false);
        setNewStopPosition(null);
        setNewStopName('');
        setNewStopAddress('');
    };

    const handleSelectStop = (stop: Stop) => {
        if (stop.latitude !== null && stop.longitude !== null) {
            mapRef.current?.panTo({ lat: stop.latitude, lng: stop.longitude });
            mapRef.current?.setZoom(15);
        }
        onStopSelect(stop);
    };

    const getMapCenter = (): google.maps.LatLngLiteral => {
        const validStops = stops.filter(stop => stop.latitude !== null && stop.longitude !== null);
        if (validStops.length === 0) {
            return { lat: MAP_DEFAULT_CENTER[0], lng: MAP_DEFAULT_CENTER[1] };
        }

        const avgLat = validStops.reduce((sum, stop) => sum + (stop.latitude || 0), 0) / validStops.length;
        const avgLng = validStops.reduce((sum, stop) => sum + (stop.longitude || 0), 0) / validStops.length;
        return { lat: avgLat, lng: avgLng };
    };

    return (
        <div className={`space-y-4 ${className}`}>
            <MapControls
                isCreatingStop={isCreatingStop}
                onToggleCreate={() => setIsCreatingStop(!isCreatingStop)}
                onLocate={getCurrentLocation}
            />

            <LocationSearch
                stops={stops}
                selectedStops={selectedStops}
                searchGeocoding={searchGeocoding}
                onSelectStop={handleSelectStop}
                onSelectGeocodingResult={handleSelectGeocodingResult}
            />

            {isCreatingStop && (
                <CreateStopForm
                    newStopPosition={newStopPosition}
                    newStopName={newStopName}
                    newStopAddress={newStopAddress}
                    onNameChange={setNewStopName}
                    onAddressChange={setNewStopAddress}
                    onCreate={handleCreateStop}
                    onCancel={handleCancelCreation}
                />
            )}

            <div className="h-96 rounded-lg overflow-hidden border">
                {loadError && (
                    <div className="h-full w-full flex items-center justify-center text-sm text-destructive text-center p-4">
                        Failed to load Google Maps. Check VITE_GOOGLE_MAPS_API_KEY and that billing
                        is enabled on the Google Cloud project.
                    </div>
                )}

                {!loadError && !isLoaded && (
                    <div className="h-full w-full flex items-center justify-center">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    </div>
                )}

                {!loadError && isLoaded && (
                    <GoogleMap
                        mapContainerClassName="h-full w-full"
                        center={getMapCenter()}
                        zoom={MAP_DEFAULT_ZOOM}
                        options={googleMapOptions}
                        onClick={handleMapClick}
                        onLoad={handleMapLoad}
                    >
                        {stops
                            .filter(stop => stop.latitude !== null && stop.longitude !== null)
                            .map(stop => (
                                <GMarker
                                    key={stop.id}
                                    position={{ lat: stop.latitude!, lng: stop.longitude! }}
                                    onClick={() => {
                                        if (isCreatingStop) {
                                            return;
                                        }
                                        setActiveInfoStopId(stop.id);
                                    }}
                                >
                                    {activeInfoStopId === stop.id && (
                                        <InfoWindow onCloseClick={() => setActiveInfoStopId(null)}>
                                            <div className="p-2">
                                                <h3 className="font-semibold">{stop.name}</h3>
                                                <p className="text-sm text-gray-600">{stop.address}</p>
                                                {isStopSelectedIn(selectedStops, stop.id) && (
                                                    <Badge variant="secondary" className="mt-2">
                                                        Order: {getStopOrderIn(selectedStops, stop.id)}
                                                    </Badge>
                                                )}
                                                {!isStopSelectedIn(selectedStops, stop.id) && (
                                                    <Button
                                                        size="sm"
                                                        className="mt-2 w-full"
                                                        onClick={() => onStopSelect(stop)}
                                                    >
                                                        Add to Route
                                                    </Button>
                                                )}
                                            </div>
                                        </InfoWindow>
                                    )}
                                </GMarker>
                            ))
                        }

                        {newStopPosition && (
                            <GMarker
                                position={{ lat: newStopPosition.lat, lng: newStopPosition.lng }}
                                icon={{
                                    url: NEW_STOP_ICON_URL,
                                    scaledSize: new google.maps.Size(25, 41),
                                }}
                            />
                        )}

                        {userLocation && (
                            <GMarker
                                position={{ lat: userLocation.lat, lng: userLocation.lng }}
                                icon={{
                                    url: USER_LOCATION_ICON_URL,
                                    scaledSize: new google.maps.Size(20, 20),
                                }}
                            />
                        )}
                    </GoogleMap>
                )}
            </div>
        </div>
    );
}

export default function MapStopPicker(props: MapStopPickerProps) {
    return USE_GOOGLE_MAPS ? <GoogleStopMap {...props} /> : <LeafletStopMap {...props} />;
}
