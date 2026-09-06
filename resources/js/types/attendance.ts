export type AttendanceStatus = 'not_checked_in' | 'checked_in' | 'on_break' | 'checked_out';

export type AttendanceSource = 'biometric_device' | 'self_service' | 'manual';

export interface AttendanceTripContext {
    trip_number: string | null;
    stage: 'pickup' | 'dropoff';
    stop_name: string | null;
}

export interface AttendanceStatusSummary {
    attendance_mode: 'biometric' | 'self_service';
    status: AttendanceStatus;
    check_in_at: string | null;
    check_out_at: string | null;
    break_minutes: number;
    net_minutes: number | null;
    overtime_minutes: number;
    trip: AttendanceTripContext | null;
}

export interface AttendanceEvent {
    id: number;
    event_type: 'check_in' | 'check_out' | 'break_start' | 'break_end' | 'correction';
    event_time: string;
    latitude: number | null;
    longitude: number | null;
    factory_id: number | null;
    location_name: string | null;
    is_valid: boolean;
    void_reason: string | null;
}

export interface AttendanceRecord {
    id: number;
    work_date: string;
    status: AttendanceStatus;
    check_in_at: string | null;
    check_out_at: string | null;
    break_minutes: number;
    gross_minutes: number | null;
    net_minutes: number | null;
    overtime_minutes: number;
    source: AttendanceSource | null;
    used_transport: boolean;
    has_anomaly: boolean;
    trip: { id: number; trip_number: string } | null;
    events: AttendanceEvent[];
}

export interface Factory {
    id: number;
    name: string;
}
