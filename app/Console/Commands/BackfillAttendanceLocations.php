<?php

namespace App\Console\Commands;

use App\Models\AttendanceEvent;
use App\Services\LocationResolver;
use Illuminate\Console\Command;

class BackfillAttendanceLocations extends Command
{
    protected $signature = 'vems:backfill-attendance-locations {--dry : Only show diagnostics, do not write changes}';

    protected $description = 'Resolve factory_id/location_name for existing attendance events that only have raw lat/lng, via LocationResolver';

    public function handle(LocationResolver $resolver): int
    {
        $query = AttendanceEvent::query()
            ->whereNull('factory_id')
            ->whereNull('location_name')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        $total = (clone $query)->count();
        $this->line("Attendance events with raw coordinates and no resolved location: {$total}");

        if ($total === 0) {
            $this->info('Nothing to backfill.');

            return self::SUCCESS;
        }

        if ($this->option('dry')) {
            $sample = (clone $query)->limit(10)->get(['id', 'event_type', 'event_time', 'latitude', 'longitude']);
            $this->info('Sample events needing backfill (max 10):');
            foreach ($sample as $event) {
                $this->line(" - ID {$event->id} | {$event->event_type} | {$event->event_time} | {$event->latitude},{$event->longitude}");
            }
            $this->comment('DRY RUN: No changes written.');

            return self::SUCCESS;
        }

        $counts = ['factory' => 0, 'stop' => 0, 'nominatim' => 0, 'unresolved' => 0];
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(100, function ($events) use ($resolver, &$counts, $bar) {
            foreach ($events as $event) {
                $resolved = $resolver->resolve((float) $event->latitude, (float) $event->longitude);

                if ($resolved['location_name'] || $resolved['factory_id']) {
                    $event->update([
                        'location_name' => $resolved['location_name'],
                        'factory_id' => $resolved['factory_id'],
                    ]);
                    $counts[$resolved['source']]++;
                } else {
                    $counts['unresolved']++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('Backfill complete:');
        $this->line(" - Matched to a factory: {$counts['factory']}");
        $this->line(" - Matched to a route stop: {$counts['stop']}");
        $this->line(" - Resolved via Nominatim: {$counts['nominatim']}");
        $this->line(" - Still unresolved (coordinates only): {$counts['unresolved']}");

        return self::SUCCESS;
    }
}
