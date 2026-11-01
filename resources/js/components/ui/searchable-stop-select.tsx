import React, { useState, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Plus, Search, MapPin } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Stop {
    id: number;
    name: string;
    description: string | null;
    latitude: number | null;
    longitude: number | null;
}

interface SearchableStopSelectProps {
    stops: Stop[];
    value: number;
    onChange: (stopId: number) => void;
    onCreateStop: (stopData: { name: string; description: string }) => void;
    placeholder?: string;
    className?: string;
}

export function SearchableStopSelect({
    stops,
    value,
    onChange,
    onCreateStop,
    placeholder = "Search or add new stop...",
    className
}: SearchableStopSelectProps) {
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

    const handleCreateStop = () => {
        if (newStopName.trim()) {
            onCreateStop({
                name: newStopName.trim(),
                description: newStopDescription.trim() || ''
            });
            setNewStopName('');
            setNewStopDescription('');
            setShowCreateForm(false);
            setIsOpen(false);
            setSearchTerm('');
        }
    };

    const handleInputFocus = () => {
        setIsOpen(true);
        if (selectedStop) {
            setSearchTerm(selectedStop.name);
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
                    className="pl-10 pr-4"
                />
            </div>

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
                                        {filteredStops.map((stop) => (
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
                                                </div>
                                            </button>
                                        ))}
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
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}
