import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
    permissions?: string[];
    roles?: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    children?: NavItem[];
    /** Permission name(s) required to show this item. An array means "any of". Omit to always show. */
    permission?: string | string[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    flash: {
        success?: string;
        error?: string;
        warning?: string;
        info?: string;
    };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    username?: string;
    employee_id?: string;
    email?: string; // Made optional for drivers
    phone?: string; // Keep for backward compatibility
    official_phone?: string;
    personal_phone?: string;
    emergency_phone?: string;
    user_type?: string;
    blood_group?: string;
    image?: string;
    avatar?: string; // Keep for backward compatibility
    status: string;
    address?: string;
    whatsapp_id?: string;
    // Driver-specific fields
    driving_license_no?: string;
    license_class?: string;
    license_issue_date?: string;
    license_expiry_date?: string;
    nid_number?: string;
    passport_number?: string;
    present_address?: string;
    permanent_address?: string;
    emergency_contact_name?: string;
    emergency_contact_phone?: string;
    emergency_contact_relation?: string;
    // System fields
    joining_date?: string;
    probation_end_date?: string;
    department_id?: number;
    driver_status?: string;
    total_distance_covered?: number;
    total_trips_completed?: number;
    average_rating?: number;
    // Authentication and tracking
    last_login_at?: string;
    last_login_ip?: string;
    last_login_location?: string;
    last_login_device?: string;
    last_login_country?: string;
    last_login_timezone?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface Product {
    id: number;
    name: string;
    description: string;
    price: number;
    category: string;
    status: 'active' | 'inactive' | 'pending';
    created_at: string;
    updated_at: string;
}

export interface VendorContactPerson {
    id: number;
    vendor_id: number;
    name: string;
    position: string | null;
    phone: string | null;
    email: string | null;
    is_primary: boolean;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

export interface Vendor {
    id: number;
    name: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    website: string | null;
    description: string | null;
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
    contact_persons?: VendorContactPerson[];
    vehicles?: Vehicle[];
    vehicles_count?: number;
}

export interface Vehicle {
    id: number;
    brand: string;
    model: string;
    color: string | null; // Made optional
    registration_number: string;
    vendor: string | null; // Keep for backward compatibility
    vendor_id: number | null;
    vendor?: Vendor;
    driver_id: number;
    driver?: User;
    is_active: boolean;
    // Tax Token
    tax_token_last_date: string | null;
    tax_token_number: string | null;
    // Fitness Certificate
    fitness_certificate_last_date: string | null;
    fitness_certificate_number: string | null;
    // Insurance
    insurance_type: '1st_party' | '3rd_party' | 'comprehensive' | null;
    insurance_last_date: string | null;
    insurance_policy_number: string | null;
    insurance_company: string | null;
    // Registration Certificate & Owner Info
    registration_certificate_number: string | null;
    owner_name: string | null;
    owner_address: string | null;
    owner_phone: string | null;
    owner_email: string | null;
    owner_nid: string | null;
    // Additional Vehicle Info
    manufacture_year: number | null;
    engine_number: string | null;
    chassis_number: string | null;
    fuel_type: 'petrol' | 'diesel' | 'cng' | 'electric' | 'hybrid' | null;
    // Alert Settings
    tax_token_alert_enabled: boolean;
    fitness_alert_enabled: boolean;
    insurance_alert_enabled: boolean;
    alert_days_before: number;
    created_at: string;
    updated_at: string;
}

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
    permissions?: Permission[];
    users?: User[];
    users_count?: number;
    permissions_count?: number;
}

export interface Permission {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
    roles?: Role[];
    users?: User[];
    roles_count?: number;
    users_count?: number;
}

export interface ActionButton {
    label: string;
    icon: React.ReactNode;
    href?: string;
    onClick?: () => void;
    variant?: 'default' | 'outline' | 'ghost' | 'link' | 'destructive';
}


// DataTable Types
export type SortDirection = 'asc' | 'desc' | null;

export interface SortConfig {
    key: string;
    direction: SortDirection;
}

export interface FilterOption {
    label: string;
    value: string | number;
}

export interface ColumnFilter {
    key: string;
    label: string;
    options: FilterOption[];
    type: 'select' | 'multiselect';
}

export interface DataTableColumn<T = any> {
    key: keyof T;
    label: string;
    sortable?: boolean;
    filterable?: boolean;
    filterOptions?: FilterOption[];
    render?: (value: any, row: T) => React.ReactNode;
    className?: string;
    headerClassName?: string;
}

export interface PaginationInfo {
    currentPage: number;
    totalPages: number;
    totalItems: number;
    itemsPerPage: number;
    startItem: number;
    endItem: number;
}

export interface DataTableProps<T = any> {
    data: T[];
    columns: DataTableColumn<T>[];
    searchable?: boolean;
    searchPlaceholder?: string;
    filterable?: boolean;
    filters?: ColumnFilter[];
    sortable?: boolean;
    paginated?: boolean;
    itemsPerPage?: number;
    loading?: boolean;
    emptyMessage?: string;
    className?: string;
    onRowClick?: (row: T) => void;
}

// Trip Types
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
    schedule_type: 'pick-and-drop' | 'engineer' | 'training' | 'adhoc' | 'reposition';
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

export interface Factory {
    id: number;
    name: string;
    code?: string;
    address?: string;
    contact_person?: string;
    contact_phone?: string;
    latitude?: number;
    longitude?: number;
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
}

export interface TripAuditLog {
    id: number;
    trip_id: number;
    user_id: number;
    action: string;
    old_values?: Record<string, any>;
    new_values?: Record<string, any>;
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
    assignedBy?: User;
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
    vehicleRoute?: VehicleRoute;
    assignedBy?: User;
}

export interface Department {
    id: number;
    name: string;
    code?: string;
    description?: string;
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
}

export interface Stop {
    id: number;
    name: string;
    address?: string;
    latitude?: number;
    longitude?: number;
    type: 'pickup' | 'dropoff' | 'both';
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
}

export interface VehicleRoute {
    id: number;
    name: string;
    code?: string;
    description?: string;
    total_distance?: number;
    estimated_duration?: number;
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
    stops?: RouteStop[];
}

export interface RouteStop {
    id: number;
    vehicle_route_id: number;
    stop_id: number;
    sequence: number;
    distance_from_previous?: number;
    estimated_time_from_previous?: number;
    created_at: string;
    updated_at: string;
    stop?: Stop;
}
