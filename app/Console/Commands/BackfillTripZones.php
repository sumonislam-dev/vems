<?php

namespace App\Console\Commands;

use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Console\Command;

class BackfillTripZones extends Command
{
    protected $signature = 'vems:backfill-trip-zones {--dry : Only show diagnostics, do not write changes}';

    protected $description = "Fill in each trip's zone_id from its assigned vehicle, or from its requester when no vehicle is assigned, for trips created before the zone snapshot existed";

    public function handle(): int
    {
        $trips = Trip::whereNull('zone_id')->get(['id', 'trip_number', 'vehicle_id', 'requested_by']);

        // Same rule as TripObserver::zoneFor(): vehicle's zone, else requester's
        $vehicleZones = Vehicle::withoutGlobalScopes()->whereIn('id', $trips->pluck('vehicle_id')->filter())->pluck('zone_id', 'id');
        $userZones = User::withoutGlobalScopes()->whereIn('id', $trips->pluck('requested_by')->filter())->pluck('zone_id', 'id');

        $candidates = $trips
            ->map(function (Trip $trip) use ($vehicleZones, $userZones) {
                $trip->backfill_zone_id = $trip->vehicle_id
                    ? $vehicleZones[$trip->vehicle_id] ?? null
                    : $userZones[$trip->requested_by] ?? null;
                $trip->backfill_source = $trip->vehicle_id ? 'vehicle' : 'requester';

                return $trip;
            })
            ->filter(fn (Trip $trip) => $trip->backfill_zone_id !== null);

        if ($candidates->isEmpty()) {
            $this->info('No trips need a zone backfill.');

            return self::SUCCESS;
        }

        foreach ($candidates as $trip) {
            $this->line(" - #{$trip->id} {$trip->trip_number} -> zone #{$trip->backfill_zone_id} (from {$trip->backfill_source})");

            if (! $this->option('dry')) {
                // A query-builder update, not $trip->update(...): this is a one-time
                // historical backfill, not a real vehicle reassignment, so it must not
                // trigger Trip's audit-log boot hook or TripObserver's assignment-history logic.
                $trip->newQuery()->whereKey($trip->id)->update(['zone_id' => $trip->backfill_zone_id]);
            }
        }

        if ($this->option('dry')) {
            $this->comment("DRY RUN: would have backfilled {$candidates->count()} trips. No changes written.");
        } else {
            $this->info("Backfilled {$candidates->count()} trips.");
        }

        return self::SUCCESS;
    }
}
