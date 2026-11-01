import React, { useState, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Plus, Search, MapPin, Map } from 'lucide-react';
import { cn } from '@/lib/utils';
import { calculateDistance } from '@/lib/distance-calculator';

interface Stop {
    id: number;
    name: string;
    description: string | null;
    latitude: number | string | null;
    longitude: number | string | null;
}

interface EnhancedStopSelectProps {
    stops: Stop[];
    value: number;
    onChange: (stopId: number) => void;
    onCreateStop: (stopData: { name: string; description: string; latitude?: number; longitude?: number }) => Promise<Stop | undefined>;
    placeholder?: string;
    className?: string;
    previousStop?: Stop | null; // For distance calculation
    showDistances?: boolean;
    onOpenMap?: () => void; // Callback to open map picker
}

export function EnhancedStopSelect({
    stops,
    value,
    onChange,
    onCreateStop,
    placeholder = "Search or add new stop...",
    className,
    previousStop = null,
    showDistances = true,
    onOpenMap
}: EnhancedStopSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [showCreateForm, setShowCreateForm] = useState(false);
    const [newStopName, setNewStopName] = useState('');
    const [newStopDescription, setNewStopDescription] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const selectedStop = stops.find(stop => stop.id === value);

    const filteredStops = stops.filter(stop =>
        (stop.name ?? '').toLowerCase().includes(searchTerm.toLowerCase()) ||
        (stop.description && stop.description.toLowerCase().includes(searchTerm.toLowerCase()))
    );

    const noResults = searchTerm && filteredStops.length === 0;

    const normalizeCoordinate = (coordinate: number | string | null): number | null => {
        if (coordinate === null || coordinate === '') {
            return null;
        }

        const parsed = typeof coordinate === 'number' ? coordinate : Number(coordinate);
        return Number.isFinite(parsed) ? parsed : null;
    };

    // Calculate distance from previous stop
    const getDistanceFromPrevious = (stop: Stop): number | null => {
        const previousLatitude = previousStop ? normalizeCoordinate(previousStop.latitude) : null;
        const previousLongitude = previousStop ? normalizeCoordinate(previousStop.longitude) : null;
        const currentLatitude = normalizeCoordinate(stop.latitude);
        const currentLongitude = normalizeCoordinate(stop.longitude);

        if (previousLatitude === null || previousLongitude === null ||
            currentLatitude === null || currentLongitude === null) {
            return null;
        }

        return calculateDistance(
            previousLatitude,
            previousLongitude,
            currentLatitude,
            currentLongitude
        );
    };

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
                setShowCreateForm(false);
                setSearchTerm('');
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleStopSelect = (stop: Stop) => {
        onChange(stop.id);
        setIsOpen(false);
        setSearchTerm('');
        setShowCreateForm(false);
    };

    const handleCreateStop = async () => {
        if (newStopName.trim()) {
            try {
                const newStop = await onCreateStop({
                    name: newStopName.trim(),
                    description: newStopDescription.trim() || ''
                });

                if (newStop) {
                    onChange(newStop.id);
                }

                setNewStopName('');
                setNewStopDescription('');
                setShowCreateForm(false);
                setIsOpen(false);
                setSearchTerm('');
            } catch (error) {
                console.error('Error creating stop:', error);
            }
        }
    };

    const handleInputFocus = () => {
        setIsOpen(true);
        if (selectedStop) {
            setSearchTerm(selectedStop.name);
        }
    };

    const handleOpenMap = () => {
        if (onOpenMap) {
            setIsOpen(false);
            onOpenMap();
        }
    };

    return (
        <div ref={containerRef} className={cn("relative", className)}>
            <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                    ref={inputRef}
                    value={isOpen ? searchTerm : (selectedStop ? selectedStop.name : '')}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    onFocus={handleInputFocus}
                    placeholder={placeholder}
                    className="pl-10 pr-12"
                />
                {onOpenMap && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={handleOpenMap}
                        className="absolute right-1 top-1/2 transform -translate-y-1/2 h-8 w-8 p-0"
                        title="Select from map"
                    >
                        <Map className="h-4 w-4" />
                    </Button>
                )}
            </div>

            {/* Show distance info for selected stop */}
            {selectedStop && showDistances && previousStop && (
                <div className="mt-1 text-xs text-muted-foreground">
                    {(() => {
                        const distance = getDistanceFromPrevious(selectedStop);
                        return distance !== null ? `Distance from previous: ${distance} km` : 'Coordinates missing for distance calculation';
                    })()}
                </div>
            )}

            {isOpen && (
                <Card className="absolute z-50 w-full mt-1 shadow-lg border">
                    <CardContent className="p-0 max-h-60 overflow-auto">
                        {showCreateForm ? (
                            <div className="p-4 space-y-3 border-b">
                                <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                                    <Plus className="h-4 w-4" />
                                    Create New Stop
                                </div>
                                <div className="space-y-2">
                                    <Input
                                        value={newStopName}
                                        onChange={(e) => setNewStopName(e.target.value)}
                                        placeholder="Stop name"
                                        className="text-sm"
                                    />
                                    <Input
                                        value={newStopDescription}
                                        onChange={(e) => setNewStopDescription(e.target.value)}
                                        placeholder="Description (optional)"
                                        className="text-sm"
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        size="sm"
                                        onClick={handleCreateStop}
                                        disabled={!newStopName.trim()}
                                        className="flex-1"
                                    >
                                        Create Stop
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            setShowCreateForm(false);
                                            setNewStopName('');
                                            setNewStopDescription('');
                                        }}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <>
                                {filteredStops.length > 0 && (
                                    <div className="py-1">
                                        {filteredStops.map((stop) => {
                                            const distance = showDistances && previousStop ? getDistanceFromPrevious(stop) : null;
                                            const latitude = normalizeCoordinate(stop.latitude);
                                            const longitude = normalizeCoordinate(stop.longitude);

                                            return (
                                                <button
                                                    key={stop.id}
                                                    onClick={() => handleStopSelect(stop)}
                                                    className={cn(
                                                        "w-full px-4 py-2 text-left hover:bg-muted transition-colors",
                                                        "flex items-start gap-3",
                                                        value === stop.id && "bg-muted"
                                                    )}
                                                >
                                                    <MapPin className="h-4 w-4 mt-0.5 text-muted-foreground flex-shrink-0" />
                                                    <div className="min-w-0 flex-1">
                                                        <div className="font-medium text-sm">{stop.name}</div>
                                                        {stop.description && (
                                                            <div className="text-xs text-muted-foreground truncate">
                                                                {stop.description}
                                                            </div>
                                                        )}
                                                        {distance !== null && (
                                                            <div className="text-xs text-blue-600 font-medium">
                                                                {distance} km from previous
                                                            </div>
                                                        )}
                                                        {latitude !== null && longitude !== null && (
                                                            <div className="text-xs text-muted-foreground">
                                                                {latitude.toFixed(4)}, {longitude.toFixed(4)}
                                                            </div>
                                                        )}
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                )}

                                {noResults && (
                                    <div className="py-3 px-4 text-sm text-muted-foreground text-center">
                                        No stops found matching "{searchTerm}"
                                    </div>
                                )}

                                <div className="border-t">
                                    <button
                                        onClick={() => {
                                            setShowCreateForm(true);
                                            setNewStopName(searchTerm);
                                        }}
                                        className="w-full px-4 py-2 text-left hover:bg-muted transition-colors flex items-center gap-2 text-sm text-blue-600"
                                    >
                                        <Plus className="h-4 w-4" />
                                        Create new stop
                                        {searchTerm && (
                                            <span className="text-muted-foreground">
                                                "{searchTerm}"
                                            </span>
                                        )}
                                    </button>

                                    {onOpenMap && (
                                        <button
                                            onClick={handleOpenMap}
                                            className="w-full px-4 py-2 text-left hover:bg-muted transition-colors flex items-center gap-2 text-sm text-green-600 border-t"
                                        >
                                            <Map className="h-4 w-4" />
                                            Select from map
                                        </button>
                                    )}
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
