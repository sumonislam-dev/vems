<?php

namespace App\Console\Commands;

use App\Models\Factory;
use App\Models\Zone;
use Illuminate\Console\Command;

class BackfillFactoryZones extends Command
{
    protected $signature = 'vems:backfill-factory-zones {--dry : Only show diagnostics, do not write changes}';

    protected $description = 'Assign zone_id to factories with no zone yet, by matching their city against a zone code\'s known cities';

    public function handle(): int
    {
        $factories = Factory::whereNull('zone_id')->whereNotNull('city')->get(['id', 'name', 'city']);

        if ($factories->isEmpty()) {
            $this->info('No factories need a zone backfill (either none are missing one, or none have a city set).');

            return self::SUCCESS;
        }

        $matched = 0;
        $unmatched = [];

        foreach ($factories as $factory) {
            $zoneId = Zone::idForCity($factory->city);

            if (! $zoneId) {
                $unmatched[] = $factory;

                continue;
            }

            $this->line(" - #{$factory->id} {$factory->name} (city: {$factory->city}) -> zone #{$zoneId}");

            if (! $this->option('dry')) {
                $factory->update(['zone_id' => $zoneId]);
            }

            $matched++;
        }

        if ($unmatched) {
            $this->warn('Left unmapped (unrecognized city, assign manually):');
            foreach ($unmatched as $factory) {
                $this->line(" - #{$factory->id} {$factory->name} (city: {$factory->city})");
            }
        }

        if ($this->option('dry')) {
            $this->comment("DRY RUN: would have backfilled {$matched} factories. No changes written.");
        } else {
            $this->info("Backfilled {$matched} factories.");
        }

        return self::SUCCESS;
    }
}
