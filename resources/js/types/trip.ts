import type { User } from './user';
import type { Vehicle } from './vehicle';
import type { VehicleRoute, Stop, Department, Factory, Logistics } from './common';

export interface Trip {
    id: number;
    trip_number: string;
    vehicle_route_id?: number;
    vehicle_id?: number;
    driver_id?: number;
    department_id?: number;
    requested_by: number;
    approved_by?: number;
    purpose: string;
    trip_type?: 'inspection' | 'pick-up' | 'drop-off' | 'training' | 'complaints' | 'CVV' | 'Incident Inspection' | 'officials' | 'Assigned';
    remarks?: string;
    description?: string;
    schedule_type:
        | 'pick-and-drop'
        | 'pick-up'
        | 'drop-off'
        | 'engineer'
        | 'training'
        | 'adhoc'
        | 'reposition'
        | 'inspection'
        | 'complaints'
        | 'CVV'
        | 'Incident Inspection'
        | 'officials'
        | 'Assigned';
    priority: 'low' | 'medium' | 'high' | 'urgent';
    scheduled_date: string;
    scheduled_start_time: string;
    scheduled_end_time: string;
    start_location?: string;
    end_location?: string;
    is_return: boolean;
    is_completed: boolean;
    is_recurring: boolean;
    recurring_group_id?: number;
    recurring_start_date?: string;
    recurring_end_date?: string;
    original_trip_id?: number;
    start_time?: string;
    end_time?: string;
    actual_start_time?: string;
    actual_end_time?: string;
    odometer_start?: number;
    odometer_end?: number;
    distance_traveled?: number;
    actual_duration?: number;
    fuel_consumed?: number;
    fuel_cost?: number;
    other_costs?: number;
    total_cost?: number;
    status: 'pending' | 'approved' | 'rejected' | 'assigned' | 'in_progress' | 'completed' | 'cancelled';
    cancellation_reason?: 'passenger_no_show' | 'vehicle_breakdown' | 'driver_unavailable' | 'route_blocked' | 'weather_conditions' | 'emergency' | 'other';
    cancellation_notes?: string;
    cancelled_by?: number;
    cancelled_at?: string;
    multiple_departments?: number[];
    team_number?: string;
    comments?: string;
    driver_rating?: number;
    vehicle_rating?: number;
    feedback?: string;
    rejection_reason?: string;
    notes?: string;
    trip_documents?: Array<{
        name: string;
        path: string;
        uploaded_at: string;
    }>;
    created_at: string;
    updated_at: string;
    // Relationships
    requester?: User;
    approver?: User;
    vehicle?: Vehicle;
    driver?: User;
    department?: Department;
    departments?: Department[];
    logistics?: Logistics[];
    passengers?: TripPassenger[];
    trip_stops?: TripStop[];
    audit_logs?: TripAuditLog[];
    vehicle_assignments?: TripVehicleAssignment[];
    route_assignments?: TripRouteAssignment[];
    recurring_group?: TripRecurringGroup;
    original_trip?: Trip;
}

export interface TripPassenger {
    id: number;
    trip_id: number;
    user_id: number;
    pickup_stop_id?: number;
    dropoff_stop_id?: number;
    status?: 'pending' | 'confirmed' | 'boarded' | 'completed' | 'cancelled' | 'no_show';
    boarded_at?: string;
    dropped_at?: string;
    notes?: string;
    created_at: string;
    updated_at: string;
    user?: User;
    pickup_stop?: Stop;
    dropoff_stop?: Stop;
    passenger_events?: TripPassengerEvent[];
}

export interface TripPassengerEvent {
    id: number;
    trip_passenger_id: number;
    trip_id: number;
    user_id: number;
    event_type: 'check_in' | 'check_out' | 'no_show' | 'manual_override' | 'correction';
    event_time: string;
    stop_id?: number;
    latitude?: number;
    longitude?: number;
    gps_accuracy_meters?: number;
    ip_address?: string;
    area_name?: string;
    source?: string;
    actor_user_id?: number;
    device_id?: string;
    idempotency_key?: string;
    is_valid: boolean;
    voided_at?: string;
    void_reason?: string;
    superseded_by_event_id?: number;
    metadata?: Record<string, unknown>;
    created_at: string;
    updated_at: string;
    stop?: Stop;
    actor?: User;
}

export interface TripStop {
    id: number;
    trip_id: number;
    stop_id: number;
    factory_id?: number;
    stop_order: number;
    estimated_arrival?: string;
    actual_arrival?: string;
    departure_time?: string;
    is_destination: boolean;
    visit_purpose?: string;
    notes?: string;
    created_at: string;
    updated_at: string;
    stop?: Stop;
    factory?: Factory;
}

export interface TripRecurringGroup {
    id: number;
    group_name?: string;
    created_by: number;
    start_date: string;
    end_date: string;
    total_trips: number;
    notes?: string;
    created_at: string;
    updated_at: string;
    creator?: User;
    trips?: Trip[];
}

export interface TripAuditLog {
    id: number;
    trip_id: number;
    user_id: number;
    action: string;
    old_values?: Record<string, unknown>;
    new_values?: Record<string, unknown>;
    reason?: string;
    created_at: string;
    updated_at: string;
    user?: User;
}

export interface TripVehicleAssignment {
    id: number;
    trip_id: number;
    vehicle_id: number;
    assigned_by: number;
    assigned_at: string;
    unassigned_at?: string;
    is_current: boolean;
    reason?: string;
    notes?: string;
    created_at: string;
    updated_at: string;
    vehicle?: Vehicle;
    assigned_by_user?: User;
}

export interface TripRouteAssignment {
    id: number;
    trip_id: number;
    vehicle_route_id: number;
    assigned_by: number;
    assigned_at: string;
    unassigned_at?: string;
    is_current: boolean;
    reason?: string;
    notes?: string;
    created_at: string;
    updated_at: string;
    vehicle_route?: VehicleRoute;
    assigned_by_user?: User;
}
