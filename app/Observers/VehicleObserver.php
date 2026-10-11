<?php

namespace App\Observers;

use App\Models\Vehicle;
use App\Models\VehicleDowntime;
use App\Models\VehicleDriverAssignment;

class VehicleObserver
{
    /**
     * Handle the Vehicle "created" event.
     */
    public function created(Vehicle $vehicle): void
    {
        if ($vehicle->driver_id) {
            VehicleDriverAssignment::create([
                'vehicle_id'  => $vehicle->id,
                'driver_id'   => $vehicle->driver_id,
                'started_at'  => now(),
                'is_current'  => true,
                'assigned_by' => auth()->id(),
            ]);
        }

        $this->syncDowntime($vehicle);
    }

    /**
     * Handle the Vehicle "updated" event.
     */
    public function updated(Vehicle $vehicle): void
    {
        if ($vehicle->wasChanged('status')) {
            $this->syncDowntime($vehicle);
        }
    }

    /**
     * Handle the Vehicle "updating" event.
     */
    public function updating(Vehicle $vehicle): void
    {
        if ($vehicle->isDirty('driver_id')) {
            $now = now();

            // Close previous current assignment for this vehicle (if any)
            $prev = $vehicle->driverAssignments()
                ->where('is_current', true)
                ->latest('started_at')
                ->first();

            if ($prev) {
                $prev->update([
                    'ended_at'   => $now,
                    'is_current' => false,
                ]);
            }

            // Create new current assignment for the new driver
            if ($vehicle->driver_id) {
                VehicleDriverAssignment::create([
                    'vehicle_id'  => $vehicle->id,
                    'driver_id'   => $vehicle->driver_id,
                    'started_at'  => $now,
                    'is_current'  => true,
                    'assigned_by' => auth()->id(),
                ]);
            }
        }
    }

    /**
     * Keep downtime in step with the vehicle's status: going into
     * maintenance/out-of-service starts a downtime from today (unless one is
     * already open), and coming back into service ends the open one today.
     * The auto-started record gets a default reason; users are prompted to
     * review it on the vehicle page.
     */
    private function syncDowntime(Vehicle $vehicle): void
    {
        $open = $vehicle->downtimes()->open()->first();

        if (in_array($vehicle->status, Vehicle::DOWN_STATUSES, true)) {
            if (! $open) {
                VehicleDowntime::create([
                    'vehicle_id' => $vehicle->id,
                    'starts_on' => today(),
                    'reason' => $vehicle->status === 'maintenance' ? 'servicing' : 'repair',
                    'notes' => 'Started automatically when the vehicle status changed to '.str_replace('_', ' ', $vehicle->status).'.',
                    'recorded_by' => auth()->id(),
                ]);
            }

            return;
        }

        if ($open) {
            $open->update(['ends_on' => today()->max($open->starts_on)]);
        }
    }
}
