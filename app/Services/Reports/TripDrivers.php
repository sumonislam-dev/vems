<?php

namespace App\Services\Reports;

use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Who drove each trip: the trip's own driver if set, otherwise whoever was
 * assigned to its vehicle on the trip date (vehicle_driver_assignments),
 * falling back to the vehicle's current driver when there's no history.
 */
class TripDrivers
{
    /** @var array<int, int|null> trip id => driver id */
    private array $driverIds = [];

    /** @var Collection<int, User> */
    private Collection $drivers;

    /**
     * @param  Collection<int, Trip>  $trips
     */
    public function __construct(Collection $trips)
    {
        $vehicleIds = $trips->pluck('vehicle_id')->filter()->unique()->values();

        $assignments = VehicleDriverAssignment::whereIn('vehicle_id', $vehicleIds)->get()->groupBy('vehicle_id');
        $currentDrivers = Vehicle::withoutGlobalScopes()->whereIn('id', $vehicleIds)->pluck('driver_id', 'id');

        foreach ($trips as $trip) {
            $this->driverIds[$trip->id] = $trip->driver_id
                ?? $this->assignedOn($assignments->get($trip->vehicle_id, collect()), $trip->scheduled_date)
                ?? ($currentDrivers[$trip->vehicle_id] ?? null);
        }

        $this->drivers = User::withoutGlobalScopes()
            ->whereIn('id', array_filter(array_unique($this->driverIds)))
            ->get(['id', 'name', 'vendor_id'])
            ->keyBy('id');
    }

    /**
     * Set a driver_name attribute on each trip, for exports.
     *
     * @param  Collection<int, Trip>  $trips
     * @return Collection<int, Trip>
     */
    public static function attachNames(Collection $trips): Collection
    {
        $drivers = new self($trips);

        return $trips->each(fn (Trip $trip) => $trip->setAttribute('driver_name', $drivers->for($trip)?->name));
    }

    public function idFor(Trip $trip): ?int
    {
        $id = $this->driverIds[$trip->id] ?? null;

        return $id === null ? null : (int) $id;
    }

    public function for(Trip $trip): ?User
    {
        $id = $this->idFor($trip);

        return $id ? $this->drivers->get($id) : null;
    }

    /**
     * @param  Collection<int, VehicleDriverAssignment>  $assignments
     */
    private function assignedOn(Collection $assignments, mixed $date): ?int
    {
        if (! $date || $assignments->isEmpty()) {
            return null;
        }

        $day = Carbon::parse($date);
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        return $assignments
            ->filter(fn ($a) => (! $a->started_at || $a->started_at <= $end) && (! $a->ended_at || $a->ended_at >= $start))
            ->sortByDesc('started_at')
            ->first()?->driver_id;
    }
}
