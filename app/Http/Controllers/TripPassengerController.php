<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\TripPassengerEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TripPassengerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:capture-passenger-attendance', only: ['checkIn', 'checkOut', 'markNoShow']),
            new Middleware('permission:correct-passenger-attendance', only: ['correctEvent']),
        ];
    }

    /**
     * Check in a passenger
     */
    public function checkIn(Request $request, Trip $trip, TripPassenger $tripPassenger)
    {
        $this->authorizeAttendanceAction($request->user(), $trip);
        $this->ensurePassengerBelongsToTrip($trip, $tripPassenger);
        $this->ensureTripAcceptsAttendance($trip);

        if (!in_array($tripPassenger->status, ['pending', 'no_show'])) {
            return back()->with('error', 'Passenger has already checked in.');
        }

        $validated = $request->validate([
            'event_time' => 'nullable|date',
            'stop_id' => 'nullable|exists:stops,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'gps_accuracy_meters' => 'nullable|numeric|min:0',
            'area_name' => 'nullable|string|max:255',
            'device_id' => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        $tripPassenger->markAsBoarded($this->buildAttendanceAttributes($request, $tripPassenger, $validated, 'check_in'));

        return back()->with('success', 'Passenger checked in successfully.');
    }

    /**
     * Check out a passenger
     */
    public function checkOut(Request $request, Trip $trip, TripPassenger $tripPassenger)
    {
        $this->authorizeAttendanceAction($request->user(), $trip);
        $this->ensurePassengerBelongsToTrip($trip, $tripPassenger);
        $this->ensureTripAcceptsAttendance($trip);

        if ($tripPassenger->status !== 'boarded') {
            return back()->with('error', 'Passenger must be checked in before checking out.');
        }

        $validated = $request->validate([
            'event_time' => 'nullable|date',
            'stop_id' => 'nullable|exists:stops,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'gps_accuracy_meters' => 'nullable|numeric|min:0',
            'area_name' => 'nullable|string|max:255',
            'device_id' => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        $tripPassenger->markAsDropped($this->buildAttendanceAttributes($request, $tripPassenger, $validated, 'check_out'));

        return back()->with('success', 'Passenger checked out successfully.');
    }

    /**
     * Mark passenger as no-show
     */
    public function markNoShow(Request $request, Trip $trip, TripPassenger $tripPassenger)
    {
        $this->authorizeAttendanceAction($request->user(), $trip);
        $this->ensurePassengerBelongsToTrip($trip, $tripPassenger);
        $this->ensureTripAcceptsAttendance($trip);

        $validated = $request->validate([
            'event_time' => 'nullable|date',
            'area_name' => 'nullable|string|max:255',
            'device_id' => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        $tripPassenger->markAsNoShow($this->buildAttendanceAttributes($request, $tripPassenger, $validated, 'no_show'));

        return back()->with('success', 'Passenger marked as no-show.');
    }

    /**
     * Correct a passenger event
     */
    public function correctEvent(Request $request, Trip $trip, TripPassenger $tripPassenger, TripPassengerEvent $tripPassengerEvent)
    {
        $this->authorizeAttendanceAction($request->user(), $trip);
        $this->ensurePassengerBelongsToTrip($trip, $tripPassenger);
        $this->ensureEventBelongsToPassenger($tripPassenger, $tripPassengerEvent);

        $validated = $request->validate([
            'event_type' => 'required|in:check_in,check_out,no_show,manual_override',
            'event_time' => 'required|date',
            'stop_id' => 'nullable|exists:stops,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'gps_accuracy_meters' => 'nullable|numeric|min:0',
            'area_name' => 'nullable|string|max:255',
            'device_id' => 'nullable|string|max:255',
            'reason' => 'required|string|max:1000',
        ]);

        $tripPassenger->correctPassengerEvent($tripPassengerEvent, array_merge(
            $this->buildAttendanceAttributes($request, $tripPassenger, $validated, $validated['event_type']),
            [
                'event_type' => $validated['event_type'],
                'reason' => $validated['reason'],
                'source' => 'admin_correction',
            ]
        ));

        return back()->with('success', 'Passenger event corrected successfully.');
    }

    /**
     * Build attendance event attributes
     */
    protected function buildAttendanceAttributes(Request $request, TripPassenger $tripPassenger, array $validated, string $eventType): array
    {
        $defaultStopId = match ($eventType) {
            'check_in' => $tripPassenger->pickup_stop_id,
            'check_out' => $tripPassenger->dropoff_stop_id,
            default => null,
        };

        return [
            'event_time' => $validated['event_time'] ?? now(),
            'stop_id' => $validated['stop_id'] ?? $defaultStopId,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'gps_accuracy_meters' => $validated['gps_accuracy_meters'] ?? null,
            'ip_address' => $request->ip(),
            'area_name' => $validated['area_name'] ?? null,
            'source' => 'web',
            'actor_user_id' => $request->user()?->id,
            'device_id' => $validated['device_id'] ?? null,
            'idempotency_key' => $validated['idempotency_key'] ?? null,
            'metadata' => [
                'user_agent' => $request->userAgent(),
            ],
        ];
    }

    /**
     * capture-passenger-attendance/correct-passenger-attendance are held broadly
     * (e.g. by every driver), so the permission alone doesn't stop a driver from
     * acting on a trip that isn't theirs. Restrict to: users with edit-trips
     * (managers/officers, who legitimately act on any trip) or the driver
     * currently assigned to the trip's vehicle.
     */
    protected function authorizeAttendanceAction(User $user, Trip $trip): void
    {
        $isAssignedDriver = $trip->vehicle && $trip->vehicle->driver_id === $user->id;

        abort_unless($user->can('edit-trips') || $isAssignedDriver, 403);
    }

    /**
     * Ensure passenger belongs to trip
     */
    protected function ensurePassengerBelongsToTrip(Trip $trip, TripPassenger $tripPassenger): void
    {
        abort_unless((int) $tripPassenger->trip_id === (int) $trip->id, 404);
    }

    /**
     * Ensure event belongs to passenger
     */
    protected function ensureEventBelongsToPassenger(TripPassenger $tripPassenger, TripPassengerEvent $tripPassengerEvent): void
    {
        abort_unless((int) $tripPassengerEvent->trip_passenger_id === (int) $tripPassenger->id, 404);
    }

    /**
     * Ensure trip accepts attendance capture
     */
    protected function ensureTripAcceptsAttendance(Trip $trip): void
    {
        if (in_array($trip->status, ['rejected', 'cancelled'], true)) {
            abort(422, 'Attendance cannot be captured for rejected or cancelled trips.');
        }
    }
}
