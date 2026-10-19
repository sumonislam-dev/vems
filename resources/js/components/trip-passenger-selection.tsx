import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { Check, ChevronDown, UserPlus, Users, X } from 'lucide-react';
import React from 'react';

interface Passenger {
    user_id: string;
}

interface EmployeeDetails {
    name: string;
    employeeId: string;
    department: string;
}

interface TripPassengerSelectionProps {
    selectedPassengerIds: (string | number)[];
    setSelectedPassengerIds: React.Dispatch<React.SetStateAction<(string | number)[]>>;
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    employees: any[];
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    userGroups: any[];
    selectedGroupId: string;
    onGroupSelect: (groupId: string) => void;
    passengers: Passenger[];
    getEmployeeDetails: (userId: string | number) => EmployeeDetails;
    errors: { passengers?: string };
}

export function TripPassengerSelection({
    selectedPassengerIds,
    setSelectedPassengerIds,
    employees,
    userGroups,
    selectedGroupId,
    onGroupSelect,
    passengers,
    getEmployeeDetails,
    errors,
}: TripPassengerSelectionProps) {
    const [passengerOpen, setPassengerOpen] = React.useState(false);
    const [passengerSearch, setPassengerSearch] = React.useState('');
    const passengerRef = React.useRef<HTMLDivElement>(null);

    React.useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (passengerRef.current && !passengerRef.current.contains(e.target as Node)) {
                setPassengerOpen(false);
                setPassengerSearch('');
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const passengerOptions =
        employees?.map((emp) => ({
            label: `${emp.employee_id ? `[${emp.employee_id}] ` : ''}${emp.label}${emp.department ? ` (${emp.department})` : ''}`,
            value: emp.value.toString(),
        })) || [];

    const filteredOptions = passengerOptions.filter((o) => o.label.toLowerCase().includes(passengerSearch.toLowerCase()));

    const selectedSet = new Set(selectedPassengerIds.map(String));

    const togglePassenger = (val: string) => {
        setSelectedPassengerIds((prev) => {
            const s = prev.map(String);
            return s.includes(val) ? s.filter((v) => v !== val) : [...s, val];
        });
    };

    return (
        <Card>
            <CardHeader className="pb-2 pt-4 px-4">
                <div className="flex items-center justify-between gap-3">
                    <CardTitle className="text-sm font-semibold uppercase tracking-wide text-muted-foreground flex items-center gap-2">
                        <UserPlus className="h-3.5 w-3.5" />
                        Passengers
                        {selectedPassengerIds.length > 0 && (
                            <Badge variant="secondary" className="text-xs px-1.5 py-0">
                                {selectedPassengerIds.length} named
                            </Badge>
                        )}
                    </CardTitle>
                    <div className="flex items-center gap-2">
                        {userGroups && userGroups.length > 0 && (
                            <div className="flex items-center gap-1.5">
                                <Users className="h-3.5 w-3.5 text-muted-foreground shrink-0" />
                                <div className="w-48">
                                    <Select
                                        name="passenger_group"
                                        value={selectedGroupId || 'none'}
                                        onValueChange={(v) => onGroupSelect(v === 'none' ? '' : v)}
                                    >
                                        <SelectTrigger id="passenger_group" aria-label="Add passengers by group" className="h-10 text-sm">
                                            <SelectValue placeholder="Add by group..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none" className="text-sm text-muted-foreground">
                                                Add by group...
                                            </SelectItem>
                                            {userGroups.map((g) => (
                                                <SelectItem key={g.value} value={g.value.toString()} className="text-sm">
                                                    {g.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        )}
                        <div className="w-56 relative" ref={passengerRef}>
                            <button
                                type="button"
                                onClick={() => setPassengerOpen((o) => !o)}
                                className={cn(
                                    'flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm',
                                    'ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                                    passengerOpen && 'ring-2 ring-ring ring-offset-2',
                                    errors.passengers && 'border-destructive',
                                )}
                            >
                                <span className={cn(selectedSet.size === 0 && 'text-muted-foreground')}>
                                    {selectedSet.size === 0 ? 'Add passengers...' : `${selectedSet.size} passenger${selectedSet.size > 1 ? 's' : ''} selected`}
                                </span>
                                <ChevronDown className="h-4 w-4 opacity-50 shrink-0" />
                            </button>
                            {passengerOpen && (
                                <div className="absolute right-0 z-50 mt-1 w-72 rounded-md border bg-popover shadow-lg">
                                    <div className="p-2 border-b">
                                        <Input
                                            id="passenger_search"
                                            name="passenger_search"
                                            aria-label="Search passengers"
                                            type="text"
                                            placeholder="Search by name or ID..."
                                            value={passengerSearch}
                                            onChange={(e) => setPassengerSearch(e.target.value)}
                                            className="h-8"
                                            autoFocus
                                        />
                                    </div>
                                    <div className="max-h-60 overflow-auto">
                                        {filteredOptions.length === 0 ? (
                                            <div className="px-3 py-2 text-sm text-muted-foreground">No employees found</div>
                                        ) : (
                                            filteredOptions.map((opt) => (
                                                <div
                                                    key={opt.value}
                                                    onClick={() => togglePassenger(opt.value)}
                                                    className={cn(
                                                        'flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-accent hover:text-accent-foreground',
                                                        selectedSet.has(opt.value) && 'bg-accent text-accent-foreground',
                                                    )}
                                                >
                                                    <div
                                                        className={cn(
                                                            'flex h-4 w-4 shrink-0 items-center justify-center rounded border border-primary',
                                                            selectedSet.has(opt.value) && 'bg-primary text-primary-foreground',
                                                        )}
                                                    >
                                                        {selectedSet.has(opt.value) && <Check className="h-3 w-3" />}
                                                    </div>
                                                    <span className="truncate">{opt.label}</span>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="px-4 pb-4">
                {selectedPassengerIds.length === 0 ? (
                    <div className="text-center py-5 text-muted-foreground border-2 border-dashed rounded-lg">
                        <UserPlus className="h-8 w-8 mx-auto mb-1.5 opacity-25" />
                        <p className="text-xs">No passengers added. Use the dropdown above to add employees.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
                        {passengers.map((passenger, index) => {
                            const emp = getEmployeeDetails(passenger.user_id);
                            return (
                                <div
                                    key={passenger.user_id}
                                    className="group flex items-center justify-between gap-2 px-3 py-2 rounded-md border bg-muted/20 hover:bg-muted/50 transition-colors"
                                >
                                    <div className="flex items-center gap-2 min-w-0">
                                        <span className="flex-shrink-0 w-6 h-6 rounded-full bg-primary/10 text-primary text-[10px] font-bold flex items-center justify-center">
                                            {index + 1}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="text-sm font-medium truncate leading-tight">{emp.name}</p>
                                            <div className="flex items-center gap-1 mt-0.5">
                                                {emp.employeeId && (
                                                    <span className="text-[10px] font-mono px-1 py-0 bg-muted text-muted-foreground rounded">
                                                        {emp.employeeId}
                                                    </span>
                                                )}
                                                {emp.department && <span className="text-[10px] text-muted-foreground truncate">{emp.department}</span>}
                                            </div>
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => setSelectedPassengerIds((prev) => prev.filter((id) => id.toString() !== passenger.user_id.toString()))}
                                        className="h-6 w-6 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0 text-muted-foreground hover:text-destructive"
                                    >
                                        <X className="h-3 w-3" />
                                        <span className="sr-only">Remove</span>
                                    </Button>
                                </div>
                            );
                        })}
                    </div>
                )}
                {errors.passengers && <p className="text-xs text-red-500 mt-2">{errors.passengers}</p>}
            </CardContent>
        </Card>
    );
}
