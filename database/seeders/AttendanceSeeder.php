<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Factory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userIds = User::query()
            ->where('status', 'active')
            ->whereIn('user_type', ['employee', 'driver'])
            ->pluck('id')
            ->all();

        $factories = Factory::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'name', 'latitude', 'longitude']);

        if (empty($userIds) || $factories->isEmpty()) {
            $this->command?->warn('AttendanceSeeder skipped: missing prerequisite records (employee/driver users or factories with coordinates).');

            return;
        }

        $existingRecords = AttendanceRecord::query()->count();
        if ($existingRecords >= 200) {
            $this->command?->info("AttendanceSeeder skipped: {$existingRecords} attendance records already exist.");

            return;
        }

        $sampleUserIds = collect($userIds)->shuffle()->take(min(25, count($userIds)))->values()->all();
        $days = 21;
        $today = Carbon::today();

        $created = 0;

        DB::transaction(function () use ($sampleUserIds, $factories, $days, $today, &$created) {
            foreach ($sampleUserIds as $index => $userId) {
                // Each user is anchored to one "home" factory, like a real
                // employee reporting to the same site most days.
                $homeFactory = $factories[$index % $factories->count()];

                for ($daysAgo = 1; $daysAgo <= $days; $daysAgo++) {
                    $workDate = (clone $today)->subDays($daysAgo);

                    // Bangladesh's work week runs Sunday–Thursday.
                    if (in_array($workDate->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true)) {
                        continue;
                    }

                    // ~15% absenteeism, like real attendance data.
                    if (random_int(1, 100) <= 15) {
                        continue;
                    }

                    if (AttendanceRecord::where('user_id', $userId)->whereDate('work_date', $workDate->toDateString())->exists()) {
                        continue;
                    }

                    $this->seedWorkday($userId, $workDate, $homeFactory);
                    $created++;
                }
            }
        });

        $this->command?->info("AttendanceSeeder completed: generated {$created} attendance days for reports.");
    }

    private function seedWorkday(int $userId, Carbon $workDate, Factory $homeFactory): void
    {
        $source = $this->weightedChoice(['self_service', 'biometric_device', 'manual'], [70, 25, 5]);
        $breakCount = $this->weightedChoice(['0', '1', '2', '3'], [30, 40, 20, 10]);
        $onSiteAtCheckIn = random_int(1, 100) <= 75;
        $onSiteAtCheckOut = random_int(1, 100) <= 90;

        $checkInAt = $workDate->copy()->setTime(random_int(7, 9), [0, 15, 30, 45][array_rand([0, 15, 30, 45])]);
        $shiftMinutes = random_int(1, 100) <= 20
            ? random_int(510, 600) // occasional overtime day
            : random_int(465, 500);

        $record = AttendanceRecord::create([
            'user_id' => $userId,
            'work_date' => $workDate->toDateString(),
            'status' => 'checked_out',
        ]);

        $record->checkIn([
            'event_time' => $checkInAt,
            'source' => $source,
            'latitude' => $this->jitter($homeFactory->latitude),
            'longitude' => $this->jitter($homeFactory->longitude),
            'factory_id' => $onSiteAtCheckIn ? $homeFactory->id : null,
            // Marks this event as seed-generated so it can be found/cleaned up later:
            // AttendanceEvent::where('metadata->seed_source', 'AttendanceSeeder')
            'metadata' => ['seed_source' => 'AttendanceSeeder'],
        ]);

        $cursor = $checkInAt->copy();
        $breakSlots = (int) $breakCount > 0 ? $this->breakOffsets((int) $breakCount) : [];

        foreach ($breakSlots as [$startOffsetMinutes, $durationMinutes]) {
            $breakStart = $checkInAt->copy()->addMinutes($startOffsetMinutes);
            $breakEnd = $breakStart->copy()->addMinutes($durationMinutes);

            $record->startBreak([
                'event_time' => $breakStart,
                'source' => $source,
                'latitude' => $this->jitter($homeFactory->latitude),
                'longitude' => $this->jitter($homeFactory->longitude),
                'factory_id' => random_int(1, 100) <= 75 ? $homeFactory->id : null,
            ]);

            $record->endBreak([
                'event_time' => $breakEnd,
                'source' => $source,
            ]);

            $cursor = $breakEnd;
        }

        $checkOutAt = $checkInAt->copy()->addMinutes($shiftMinutes);
        if ($checkOutAt->lessThan($cursor)) {
            $checkOutAt = $cursor->copy()->addMinutes(30);
        }

        $record->checkOut([
            'event_time' => $checkOutAt,
            'source' => $source,
            'factory_id' => $onSiteAtCheckOut ? $homeFactory->id : null,
            'location_name' => $onSiteAtCheckOut ? null : 'Client site visit',
        ]);

        // ~7% of days carry a flagged anomaly (e.g. a manually corrected punch).
        if (random_int(1, 100) <= 7) {
            $record->update(['has_anomaly' => true]);
        }
    }

    /**
     * @return array<int, array{0: int, 1: int}> list of [minutes-after-check-in, duration-minutes]
     */
    private function breakOffsets(int $count): array
    {
        return match ($count) {
            1 => [[240, random_int(30, 60)]],
            2 => [[240, random_int(30, 45)], [390, random_int(10, 20)]],
            3 => [[120, random_int(10, 15)], [240, random_int(30, 45)], [390, random_int(10, 20)]],
            default => [],
        };
    }

    private function jitter(?float $coordinate): ?float
    {
        if ($coordinate === null) {
            return null;
        }

        return round($coordinate + (random_int(-80, 80) / 100000), 7);
    }

    private function weightedChoice(array $values, array $weights): string
    {
        $total = array_sum($weights);
        $rand = random_int(1, $total);
        $running = 0;

        foreach ($values as $index => $value) {
            $running += $weights[$index];
            if ($rand <= $running) {
                return $value;
            }
        }

        return $values[array_key_last($values)];
    }
}
