<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripRecurringGroup;
use App\Notifications\TripStatusUpdated;
use App\Services\TripNotifier;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripStateController extends Controller implements HasMiddleware
{
    /** Longest believable single trip; a bigger odometer jump is almost certainly a typo */
    private const MAX_TRIP_KM = 3000;

    public static function middleware(): array
    {
        return [
            // Department heads (approve-department-trips) may decide on their
            // own departments' trips — checked per trip below
            new Middleware('permission:approve-trips|approve-department-trips', only: ['approve', 'bulkApprove']),
            new Middleware('permission:reject-trips|approve-department-trips', only: ['reject']),
            new Middleware('permission:edit-trips', only: ['cancel', 'cancelSeries']),
        ];
    }

    /**
     * start()/complete() are reachable by anyone with edit-trips (managers/officers)
     * or by the driver currently assigned to the trip's vehicle — they're the one who
     * actually knows when the trip departed/ended. cancel() stays manager-only.
     */
    private function authorizeStartOrComplete(Request $request, Trip $trip): void
    {
        $user = $request->user();
        $isAssignedDriver = $trip->vehicle && $trip->vehicle->driver_id === $user->id;
        // The assigned driver may start/complete their own trip if their
        // role allows it; anyone with edit-trips may do it for any trip.
        $driverPermission = $request->routeIs('trips.complete') ? 'check-out-trips' : 'check-in-trips';

        abort_unless($user->can('edit-trips') || ($isAssignedDriver && $user->can($driverPermission)), 403);
    }

    /**
     * Approve a trip
     */
    public function approve(Request $request, Trip $trip, TripNotifier $notifier)
    {
        abort_unless($request->user()->canApproveTrip($trip), 403);

        try {
            if (! $trip->canTransitionTo('approved')) {
                return back()->with('error', 'Trip cannot be approved in current status.');
            }

            $trip->transitionTo('approved', ['approved_by' => auth()->id(), 'approved_at' => now()]);
            $trip->requester?->notify(new TripStatusUpdated($trip, 'approved'));
            $notifier->needVehicles(collect([$trip]));

            return back()->with('success', 'Trip approved successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to approve trip.');
        }
    }

    /**
     * Approve every trip_id given that is still pending. Trips already
     * approved/rejected/etc by someone else since the page loaded are
     * silently skipped (Trip::transitionTo() just returns false for those).
     */
    public function bulkApprove(Request $request, TripNotifier $notifier)
    {
        $validated = $request->validate([
            'trip_ids' => 'required|array|min:1|max:100',
            'trip_ids.*' => 'integer|exists:trips,id',
        ]);

        $approved = collect();

        DB::transaction(function () use ($validated, $approved, $request) {
            $trips = Trip::whereIn('id', $validated['trip_ids'])->lockForUpdate()->get();

            foreach ($trips as $trip) {
                // A department head only approves their own departments' trips
                if ($request->user()->canApproveTrip($trip) && $trip->transitionTo('approved', ['approved_by' => auth()->id(), 'approved_at' => now()])) {
                    $approved->push($trip);
                    $trip->requester?->notify(new TripStatusUpdated($trip, 'approved'));
                }
            }
        });

        $approvedCount = $approved->count();
        $notifier->needVehicles($approved);

        if ($approvedCount === 0) {
            return back()->with('error', 'No selected trips could be approved (already approved, rejected, or otherwise not pending).');
        }

        return back()->with('success', "{$approvedCount} trip(s) approved.");
    }

    /**
     * Reject a trip
     */
    public function reject(Request $request, Trip $trip)
    {
        abort_unless($request->user()->canRejectTrip($trip), 403);

        try {
            if (! $trip->canTransitionTo('rejected')) {
                return back()->with('error', 'Trip cannot be rejected in current status.');
            }

            $validated = $request->validate([
                'rejection_reason' => 'required|string',
            ]);

            $trip->transitionTo('rejected', ['rejection_reason' => $validated['rejection_reason']]);
            $trip->requester?->notify(new TripStatusUpdated($trip, 'rejected'));

            return back()->with('success', 'Trip rejected.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to reject trip.');
        }
    }

    /**
     * Start a trip
     */
    public function start(Request $request, Trip $trip)
    {
        $this->authorizeStartOrComplete($request, $trip);

        try {
            if (! $trip->canTransitionTo('in_progress')) {
                return back()->with('error', 'Trip cannot be started in current status.');
            }

            if (! $trip->vehicle_id) {
                return back()->with('error', 'Assign a vehicle before starting the trip.');
            }

            // Optional, but if entered it can't be below the vehicle's last recorded reading (catches typos)
            $lastReading = (float) Trip::where('vehicle_id', $trip->vehicle_id)
                ->whereKeyNot($trip->id)
                ->max('odometer_end');

            $validated = $request->validate([
                'odometer_start' => 'nullable|numeric|min:'.$lastReading,
            ], [
                'odometer_start.min' => "The reading can't be below this vehicle's last recorded reading (".number_format($lastReading).' km).',
            ]);

            $trip->startTrip($validated['odometer_start'] ?? null);

            return back()->with('success', 'Trip started successfully!');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to start trip.');
        }
    }

    /**
     * Complete a trip
     */
    public function complete(Request $request, Trip $trip)
    {
        $this->authorizeStartOrComplete($request, $trip);

        try {
            if (! $trip->canTransitionTo('completed')) {
                return back()->with('error', 'Trip cannot be completed in current status.');
            }

            // Optional; when there is a start reading, the end must be within one believable trip of it
            $odometerEnd = ['nullable', 'numeric', 'min:'.($trip->odometer_start ?? 0)];
            if ($trip->odometer_start !== null) {
                $odometerEnd[] = 'max:'.($trip->odometer_start + self::MAX_TRIP_KM);
            }

            $validated = $request->validate([
                'odometer_end' => $odometerEnd,
                'fuel_consumed' => 'nullable|numeric|min:0|max:2000',
                'fuel_cost' => 'nullable|numeric|min:0|max:1000000',
                'other_costs' => 'nullable|numeric|min:0|max:1000000',
                'notes' => 'nullable|string',
            ], [
                'odometer_end.min' => "The end reading can't be below the start reading.",
                'odometer_end.max' => 'That is more than '.number_format(self::MAX_TRIP_KM).' km for one trip. Please check the reading.',
            ]);

            $trip->completeTrip($validated);

            return back()->with('success', 'Trip completed successfully!');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to complete trip.');
        }
    }

    /**
     * Cancel a trip
     */
    public function cancel(Request $request, Trip $trip, TripNotifier $notifier)
    {
        try {
            if (! $trip->canTransitionTo('cancelled')) {
                return back()->with('error', 'Trip cannot be cancelled in current status.');
            }

            $validated = $request->validate([
                'cancellation_reason' => 'required|in:passenger_no_show,vehicle_breakdown,driver_unavailable,route_blocked,weather_conditions,emergency,other',
                'cancellation_notes' => 'nullable|string',
            ]);

            $trip->cancel($validated['cancellation_reason'], $validated['cancellation_notes'] ?? null);
            $notifier->cancelled(collect([$trip]));

            return back()->with('success', 'Trip cancelled.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to cancel trip.');
        }
    }

    /**
     * Cancel every trip in a recurring series that's still cancellable.
     * Trips already in_progress/completed/cancelled/rejected are left alone
     * (Trip::cancel() just returns false for those — no error, just skipped).
     */
    public function cancelSeries(Request $request, TripRecurringGroup $group, TripNotifier $notifier)
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|in:passenger_no_show,vehicle_breakdown,driver_unavailable,route_blocked,weather_conditions,emergency,other',
            'cancellation_notes' => 'nullable|string',
        ]);

        $cancelled = collect();

        DB::transaction(function () use ($group, $validated, $cancelled) {
            $trips = $group->trips()->lockForUpdate()->get();

            foreach ($trips as $trip) {
                if ($trip->cancel($validated['cancellation_reason'], $validated['cancellation_notes'] ?? null)) {
                    $cancelled->push($trip);
                }
            }
        });

        $cancelledCount = $cancelled->count();
        $notifier->cancelled($cancelled);

        if ($cancelledCount === 0) {
            return back()->with('error', 'No trips in this series could be cancelled (already in progress, completed, rejected, or cancelled).');
        }

        return back()->with('success', "{$cancelledCount} trip(s) in the series cancelled.");
    }
}
