// Re-export all domain types for backward compatibility
export type { Auth, BreadcrumbItem, NavGroup, NavItem, SharedData } from './app';
export type { User, Role } from './user';
export type { Permission } from './permission';
export type { VendorContactPerson, Vendor, Vehicle } from './vehicle';
export type {
    Product, Department, Factory, Stop, VehicleRoute, RouteStop, Logistics,
    ActionButton, SortDirection, SortConfig, FilterOption, ColumnFilter,
    DataTableColumn, PaginationInfo, DataTableProps, PaginatedData,
} from './common';
export type {
    Trip, TripPassenger, TripPassengerEvent, TripStop, TripRecurringGroup,
    TripAuditLog, TripVehicleAssignment, TripRouteAssignment,
} from './trip';
export type {
    TripFeedback, TripFeedbackType, TripFeedbackCategory, TripFeedbackPriority, TripFeedbackStatus,
} from './trip-feedback';
