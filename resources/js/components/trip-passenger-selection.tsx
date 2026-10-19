import { SearchableMultiSelect } from '@/components/searchable-multi-select';
import { SearchableSelect } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { UserPlus, X } from 'lucide-react';
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
    const hasGroups = (userGroups?.length ?? 0) > 0;

    const passengerOptions =
        employees?.map((emp) => ({
            label: `${emp.employee_id ? `[${emp.employee_id}] ` : ''}${emp.label}${emp.department ? ` (${emp.department})` : ''}`,
            value: emp.value.toString(),
        })) || [];

    return (
        <Card>
            <CardHeader className="px-4 pt-4 pb-2">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <CardTitle className="flex items-center gap-2 pt-2.5 text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                        <UserPlus className="h-3.5 w-3.5" />
                        Passengers
                        {selectedPassengerIds.length > 0 && (
                            <Badge variant="secondary" className="px-1.5 py-0 text-xs">
                                {selectedPassengerIds.length} named
                            </Badge>
                        )}
                    </CardTitle>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        {hasGroups && (
                            <div className="sm:w-56">
                                <SearchableSelect
                                    label="Add by Group"
                                    name="passenger_group"
                                    value={selectedGroupId}
                                    onChange={(v) => onGroupSelect(v === 'none' ? '' : v)}
                                    options={userGroups.map((g) => ({ label: g.label, value: g.value.toString() }))}
                                    placeholder="Add by group..."
                                    hideLabel
                                />
                            </div>
                        )}
                        <div className="sm:w-64">
                            <SearchableMultiSelect
                                label="Passengers"
                                name="passenger_ids"
                                value={selectedPassengerIds}
                                onChange={setSelectedPassengerIds}
                                options={passengerOptions}
                                placeholder="Add passengers..."
                                selectedText={(count) => `${count} passenger${count > 1 ? 's' : ''} selected`}
                                error={errors.passengers}
                                hideLabel
                            />
                        </div>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="px-4 pb-4">
                {selectedPassengerIds.length === 0 ? (
                    <div className="rounded-lg border-2 border-dashed py-5 text-center text-muted-foreground">
                        <UserPlus className="mx-auto mb-1.5 h-8 w-8 opacity-25" />
                        <p className="text-xs">No passengers added. Search above, or add a whole group.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
                        {passengers.map((passenger, index) => {
                            const emp = getEmployeeDetails(passenger.user_id);
                            return (
                                <div
                                    key={passenger.user_id}
                                    className="group flex items-center justify-between gap-2 rounded-md border bg-muted/20 px-3 py-2 transition-colors hover:bg-muted/50"
                                >
                                    <div className="flex min-w-0 items-center gap-2">
                                        <span className="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-[10px] font-bold text-primary">
                                            {index + 1}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate text-sm leading-tight font-medium">{emp.name}</p>
                                            <div className="mt-0.5 flex items-center gap-1">
                                                {emp.employeeId && (
                                                    <span className="rounded bg-muted px-1 py-0 font-mono text-[10px] text-muted-foreground">
                                                        {emp.employeeId}
                                                    </span>
                                                )}
                                                {emp.department && (
                                                    <span className="truncate text-[10px] text-muted-foreground">{emp.department}</span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() =>
                                            setSelectedPassengerIds((prev) => prev.filter((id) => id.toString() !== passenger.user_id.toString()))
                                        }
                                        className="h-6 w-6 flex-shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-destructive"
                                    >
                                        <X className="h-3 w-3" />
                                        <span className="sr-only">Remove</span>
                                    </Button>
                                </div>
                            );
                        })}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
