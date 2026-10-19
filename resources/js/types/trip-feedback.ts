export type TripFeedbackType = 'feedback' | 'complaint';

export type TripFeedbackCategory = 'driver_behavior' | 'vehicle_condition' | 'punctuality' | 'safety' | 'route' | 'other';

export type TripFeedbackPriority = 'low' | 'medium' | 'high' | 'critical';

export type TripFeedbackStatus = 'open' | 'in_review' | 'resolved' | 'closed';

export interface TripFeedback {
    id: number;
    trip_id: number;
    submitted_by: number;
    type: TripFeedbackType;
    category: TripFeedbackCategory;
    subject: string;
    description: string;
    driver_rating?: number | null;
    vehicle_rating?: number | null;
    priority: TripFeedbackPriority;
    status: TripFeedbackStatus;
    assigned_to?: number | null;
    resolution_notes?: string | null;
    resolved_at?: string | null;
    resolved_by?: number | null;
    is_anonymous: boolean;
    created_at: string;
    updated_at: string;
    trip?: {
        id: number;
        trip_number: string;
        scheduled_date: string;
        description?: string | null;
    };
    submitter?: { id: number; name: string };
    assignee?: { id: number; name: string } | null;
    resolver?: { id: number; name: string } | null;
}
