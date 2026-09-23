<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripRecurringGroup;
use App\Notifications\TripStatusUpdated;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TripStateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:approve-trips', only: ['approve', 'reject', 'bulkApprove']),
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

        abort_unless($user->can('edit-trips') || $isAssignedDriver, 403);
    }

    /**
     * Approve a trip
     */
    public function approve(Request $request, Trip $trip)
    {
        try {
            if (!$trip->canTransitionTo('approved')) {
                return back()->with('error', 'Trip cannot be approved in current status.');
            }

            $trip->transitionTo('approved', ['approved_by' => auth()->id()]);
            $trip->requester?->notify(new TripStatusUpdated($trip, 'approved'));

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
    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'trip_ids' => 'required|array|min:1|max:100',
            'trip_ids.*' => 'integer|exists:trips,id',
        ]);

        $approvedCount = 0;

        DB::transaction(function () use ($validated, &$approvedCount) {
            $trips = Trip::whereIn('id', $validated['trip_ids'])->lockForUpdate()->get();

            foreach ($trips as $trip) {
                if ($trip->transitionTo('approved', ['approved_by' => auth()->id()])) {
                    $approvedCount++;
                    $trip->requester?->notify(new TripStatusUpdated($trip, 'approved'));
                }
            }
        });

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
        try {
            if (!$trip->canTransitionTo('rejected')) {
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
            if (!$trip->canTransitionTo('in_progress')) {
                return back()->with('error', 'Trip cannot be started in current status.');
            }

            $validated = $request->validate([
                'odometer_start' => 'nullable|numeric|min:0',
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
            if (!$trip->canTransitionTo('completed')) {
                return back()->with('error', 'Trip cannot be completed in current status.');
            }

            $validated = $request->validate([
                'odometer_end' => 'nullable|numeric|min:' . ($trip->odometer_start ?? 0),
                'fuel_consumed' => 'nullable|numeric|min:0|max:2000',
                'fuel_cost' => 'nullable|numeric|min:0|max:1000000',
                'other_costs' => 'nullable|numeric|min:0|max:1000000',
                'notes' => 'nullable|string',
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
    public function cancel(Request $request, Trip $trip)
    {
        try {
            if (!$trip->canTransitionTo('cancelled')) {
                return back()->with('error', 'Trip cannot be cancelled in current status.');
            }

            $validated = $request->validate([
                'cancellation_reason' => 'required|in:passenger_no_show,vehicle_breakdown,driver_unavailable,route_blocked,weather_conditions,emergency,other',
                'cancellation_notes' => 'nullable|string',
            ]);

            $trip->cancel($validated['cancellation_reason'], $validated['cancellation_notes'] ?? null);

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
    public function cancelSeries(Request $request, TripRecurringGroup $group)
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|in:passenger_no_show,vehicle_breakdown,driver_unavailable,route_blocked,weather_conditions,emergency,other',
            'cancellation_notes' => 'nullable|string',
        ]);

        $cancelledCount = 0;

        DB::transaction(function () use ($group, $validated, &$cancelledCount) {
            $trips = $group->trips()->lockForUpdate()->get();

            foreach ($trips as $trip) {
                if ($trip->cancel($validated['cancellation_reason'], $validated['cancellation_notes'] ?? null)) {
                    $cancelledCount++;
                }
            }
        });

        if ($cancelledCount === 0) {
            return back()->with('error', 'No trips in this series could be cancelled (already in progress, completed, rejected, or cancelled).');
        }

        return back()->with('success', "{$cancelledCount} trip(s) in the series cancelled.");
    }
}
