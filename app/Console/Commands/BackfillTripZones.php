<?php

namespace App\Console\Commands;

use App\Models\Trip;
use Illuminate\Console\Command;

class BackfillTripZones extends Command
{
    protected $signature = 'vems:backfill-trip-zones {--dry : Only show diagnostics, do not write changes}';

    protected $description = "Copy each trip's zone_id from its currently assigned vehicle, for trips created before the zone snapshot existed";

    public function handle(): int
    {
        $trips = Trip::whereNull('zone_id')->whereNotNull('vehicle_id')->with('vehicle:id,zone_id')->get(['id', 'trip_number', 'vehicle_id']);

        $candidates = $trips->filter(fn ($trip) => $trip->vehicle?->zone_id !== null);

        if ($candidates->isEmpty()) {
            $this->info('No trips need a zone backfill.');

            return self::SUCCESS;
        }

        foreach ($candidates as $trip) {
            $zoneId = $trip->vehicle->zone_id;
            $this->line(" - #{$trip->id} {$trip->trip_number} -> zone #{$zoneId}");

            if (! $this->option('dry')) {
                // A query-builder update, not $trip->update(...): this is a one-time
                // historical backfill, not a real vehicle reassignment, so it must not
                // trigger Trip's audit-log boot hook or TripObserver's assignment-history logic.
                $trip->newQuery()->whereKey($trip->id)->update(['zone_id' => $zoneId]);
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
