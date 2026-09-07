<?php

namespace App\Jobs;

use App\Models\AttendanceEvent;
use App\Services\LocationResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Off the request/response cycle: only dispatched when matchKnown() found
 * nothing nearby, i.e. the case that needs the (slow, sometimes blocked)
 * Nominatim call — so a check-in/break action never waits on it.
 */
class ResolveAttendanceEventLocation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 15;

    public function __construct(private readonly int $attendanceEventId)
    {
    }

    public function handle(LocationResolver $resolver): void
    {
        $event = AttendanceEvent::find($this->attendanceEventId);

        if (! $event || $event->factory_id || $event->location_name) {
            return;
        }

        if ($event->latitude === null || $event->longitude === null) {
            return;
        }

        $resolved = $resolver->resolve((float) $event->latitude, (float) $event->longitude);

        if ($resolved['location_name'] || $resolved['factory_id']) {
            $event->update([
                'location_name' => $resolved['location_name'],
                'factory_id' => $resolved['factory_id'],
            ]);
        }
    }
}
