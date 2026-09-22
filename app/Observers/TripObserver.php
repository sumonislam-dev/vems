<?php

namespace App\Observers;

use App\Models\Trip;
use App\Models\TripVehicleAssignment;

class TripObserver
{
    /**
     * Handle the Trip "created" event.
     */
    public function created(Trip $trip): void
    {
        if ($trip->vehicle_id) {
            TripVehicleAssignment::create([
                'trip_id'      => $trip->id,
                'vehicle_id'   => $trip->vehicle_id,
                'assigned_at'  => now(),
                'is_current'   => true,
                'assigned_by'  => auth()->id(),
                'reason'       => 'initial',
            ]);
        }
    }

    /**
     * Handle the Trip "updating" event.
     */
    public function updating(Trip $trip): void
    {
        // Auto-update end_time when trip is marked as completed
        if ($trip->isDirty('is_completed') && $trip->is_completed && !$trip->end_time) {
            $trip->end_time = now();
            $trip->status = 'completed'; // Update status as well
        }

        // Auto-update start_time when trip starts (status = in_progress)
        if ($trip->isDirty('status') && $trip->status === 'in_progress' && !$trip->start_time) {
            $trip->start_time = now();
        }

        if ($trip->isDirty('vehicle_id')) {
            $now = now();

            // Close previous current assignment for this trip (if any)
            $prev = $trip->vehicleAssignments()
                ->where('is_current', true)
                ->latest('assigned_at')
                ->first();

            if ($prev) {
                $prev->update([
                    'unassigned_at' => $now,
                    'is_current'    => false,
                ]);
            }

            // Create new current assignment for the new vehicle
            if ($trip->vehicle_id) {
                TripVehicleAssignment::create([
                    'trip_id'      => $trip->id,
                    'vehicle_id'   => $trip->vehicle_id,
                    'assigned_at'  => $now,
                    'is_current'   => true,
                    'assigned_by'  => auth()->id(),
                    'reason'       => $trip->pendingVehicleReassignReason ?? 'replacement',
                    'notes'        => $trip->pendingVehicleReassignNotes,
                ]);
            }
        }
    }
}
