import type { Permission } from './permission';
import type { Department } from './common';
import type { Vendor } from './vehicle';

export interface User {
    id: number;
    name: string;
    username?: string;
    employee_id?: string;
    email?: string;
    phone?: string;
    official_phone?: string;
    personal_phone?: string;
    emergency_phone?: string;
    user_type?: string;
    blood_group?: string;
    image?: string;
    avatar?: string;
    status: string;
    address?: string;
    area?: string;
    whatsapp_id?: string;
    // Driver-specific fields
    driving_license_no?: string;
    driving_license_file?: string | null;
    license_class?: string;
    license_issue_date?: string;
    license_expiry_date?: string;
    license_status?: 'not_provided' | 'expired' | 'expiring_soon' | 'valid';
    nid_number?: string;
    nid_file?: string | null;
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
    vendor_id?: number | null;
    vendor?: Vendor | null;
    driver_status?: string;
    total_distance_covered?: number;
    total_trips_completed?: number;
    average_rating?: number | string;
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
    department?: Department;
    roles?: Role[];
    permissions?: Permission[];
    [key: string]: unknown;
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
