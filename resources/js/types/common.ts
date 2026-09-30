import * as React from 'react';

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

export interface Department {
    id: number;
    name: string;
    code?: string;
    description?: string;
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
}

export interface Zone {
    id: number;
    name: string;
    code: string;
    is_active: boolean;
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
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

export interface Logistics {
    id: number;
    name: string;
    department_id?: number;
    description?: string;
    status: boolean;
    created_by?: number;
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
    value: string | number | boolean;
}

export interface ColumnFilter {
    key: string;
    label: string;
    options: FilterOption[];
    type: 'select' | 'multiselect' | string;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export interface DataTableColumn<T = any> {
    key: keyof T | 'actions';
    label: string;
    sortable?: boolean;
    filterable?: boolean;
    filterOptions?: FilterOption[];
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
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

// eslint-disable-next-line @typescript-eslint/no-explicit-any
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

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}
