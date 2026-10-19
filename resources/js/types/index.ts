// Re-export all domain types for backward compatibility
export type { Auth, AvailableZone, BreadcrumbItem, NavGroup, NavItem, SharedData } from './app';
export type {
    ActionButton,
    ColumnFilter,
    DataTableColumn,
    DataTableProps,
    Department,
    Factory,
    FilterOption,
    Logistics,
    PaginatedData,
    PaginationInfo,
    RouteStop,
    SortConfig,
    SortDirection,
    Stop,
    VehicleRoute,
    Zone,
} from './common';
export type { Permission } from './permission';
export type {
    Trip,
    TripAuditLog,
    TripPassenger,
    TripPassengerEvent,
    TripRecurringGroup,
    TripRouteAssignment,
    TripStop,
    TripVehicleAssignment,
} from './trip';
export type { TripFeedback, TripFeedbackCategory, TripFeedbackPriority, TripFeedbackStatus, TripFeedbackType } from './trip-feedback';
export type { Role, User } from './user';
export type { Vehicle, Vendor, VendorContactPerson } from './vehicle';
