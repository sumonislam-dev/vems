export interface ActivityLogEntry {
    id: number;
    log_name: string;
    event: string | null;
    description: string;
    subject_type: string | null;
    subject_id: number | null;
    causer: {
        id: number;
        name: string;
    } | null;
    properties: {
        old: Record<string, unknown> | null;
        new: Record<string, unknown> | null;
        other: Record<string, unknown> | null;
    };
    created_at: string;
}
