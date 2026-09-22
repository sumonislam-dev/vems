import { Trip } from '@/types';

/**
 * Statuses from which a trip can be started (see TripStateController::start()
 * and Trip::canTransitionTo() on the backend — this must stay in sync with those).
 */
export const STARTABLE_TRIP_STATUSES: Trip['status'][] = ['approved', 'assigned'];

export function canStartTrip(status: Trip['status']): boolean {
    return STARTABLE_TRIP_STATUSES.includes(status);
}

/**
 * Statuses from which a trip can be completed (see TripStateController::complete()
 * and Trip::canTransitionTo() on the backend — this must stay in sync with those).
 */
export const COMPLETABLE_TRIP_STATUSES: Trip['status'][] = ['in_progress'];

export function canCompleteTrip(status: Trip['status']): boolean {
    return COMPLETABLE_TRIP_STATUSES.includes(status);
}

/**
 * Statuses from which a trip can be cancelled (see TripStateController::cancel()
 * and Trip::canTransitionTo() on the backend — this must stay in sync with those).
 */
export const CANCELLABLE_TRIP_STATUSES: Trip['status'][] = ['pending', 'approved', 'assigned', 'in_progress'];

export function canCancelTrip(status: Trip['status']): boolean {
    return CANCELLABLE_TRIP_STATUSES.includes(status);
}

/**
 * Mirrors the 'cancellation_reason' enum validated in TripStateController::cancel()/cancelSeries().
 */
export const CANCELLATION_REASONS: { value: NonNullable<Trip['cancellation_reason']>; label: string }[] = [
    { value: 'passenger_no_show', label: 'Passenger No-Show' },
    { value: 'vehicle_breakdown', label: 'Vehicle Breakdown' },
    { value: 'driver_unavailable', label: 'Driver Unavailable' },
    { value: 'route_blocked', label: 'Route Blocked' },
    { value: 'weather_conditions', label: 'Weather Conditions' },
    { value: 'emergency', label: 'Emergency' },
    { value: 'other', label: 'Other' },
];
