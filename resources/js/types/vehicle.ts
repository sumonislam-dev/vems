import type { User } from './user';

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
    trade_license: string | null;
    trade_license_file: string | null;
    tin: string | null;
    tin_file: string | null;
    bin: string | null;
    bin_file: string | null;
    tax_return: string | null;
    tax_return_file: string | null;
    bank_details: string | null;
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
    color: string | null;
    registration_number: string;
    vehicle_type: string;
    rental_type: string;
    capacity: number | null;
    vendor?: Vendor | null;
    vendor_id: number | null;
    driver_id: number;
    driver?: User;
    is_active: boolean;
    status: 'available' | 'assigned' | 'in_transit' | 'maintenance' | 'out_of_service';
    parking_address: string | null;
    parking_latitude: number | null;
    parking_longitude: number | null;
    tax_token_last_date: string | null;
    tax_token_number: string | null;
    tax_token_file: string | null;
    fitness_certificate_last_date: string | null;
    fitness_certificate_number: string | null;
    fitness_certificate_file: string | null;
    insurance_type: '1st_party' | '3rd_party' | 'comprehensive' | null;
    insurance_last_date: string | null;
    insurance_policy_number: string | null;
    insurance_policy_file: string | null;
    insurance_company: string | null;
    registration_certificate_number: string | null;
    registration_certificate_file: string | null;
    owner_name: string | null;
    owner_address: string | null;
    owner_phone: string | null;
    owner_email: string | null;
    owner_nid: string | null;
    manufacture_year: number | null;
    engine_number: string | null;
    chassis_number: string | null;
    fuel_type: 'petrol' | 'diesel' | 'cng' | 'electric' | 'hybrid' | null;
    tax_token_alert_enabled: boolean;
    fitness_alert_enabled: boolean;
    insurance_alert_enabled: boolean;
    alert_days_before: number;
    expiring_documents?: Array<{
        type: 'tax_token' | 'fitness' | 'insurance';
        name: string;
        date: string;
        days_left: number | null;
    }>;
    created_at: string;
    updated_at: string;
}
